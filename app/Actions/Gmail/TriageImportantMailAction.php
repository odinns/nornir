<?php

declare(strict_types=1);

namespace App\Actions\Gmail;

use App\Services\Gmail\GmailClientFactory;
use App\Services\Gmail\GmailTriageDateRange;
use App\Services\Gmail\GmailTriageDateRangeParser;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class TriageImportantMailAction
{
    public function __construct(
        private readonly GmailClientFactory $gmailClientFactory,
        private readonly GmailTriageDateRangeParser $dateRangeParser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        string $credentialsPath,
        ?string $since,
        ?string $on,
        ?string $window,
        ?int $limit = null,
        ?string $query = null,
        ?string $rulesPath = null,
    ): array {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('The limit must be at least 1.');
        }

        $client = $this->gmailClientFactory->make($credentialsPath, '');
        $accountEmail = strtolower($client->getAccountEmail());
        $range = $this->dateRangeParser->parse($since, $on, $window);
        $rules = $this->loadRules($rulesPath);
        $gmailQuery = $this->buildQuery($range, $query);

        $pageToken = null;
        $inspectedCount = 0;
        $items = [];

        do {
            $page = $client->listMessages($gmailQuery, $pageToken);
            $pageToken = $page['nextPageToken'];

            foreach ($page['messages'] as $stub) {
                if ($limit !== null && $inspectedCount >= $limit) {
                    $pageToken = null;
                    break;
                }

                $message = $client->getMessage($stub['id']);
                $item = $this->triageMessage($message, $accountEmail, $range, $rules);
                $inspectedCount++;

                if ($item !== null) {
                    $items[] = $item;
                }
            }
        } while ($pageToken !== null);

        usort($items, function (array $left, array $right): int {
            $scoreComparison = $right['_score'] <=> $left['_score'];

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strcmp($right['received_at'], $left['received_at']);
        });

        $rankedItems = array_map(function (array $item): array {
            unset($item['_score']);

            return $item;
        }, $items);

        return [
            'account_email' => $accountEmail,
            'query' => $gmailQuery,
            'window' => [
                'start' => $range->start->toIso8601String(),
                'end' => $range->end?->toIso8601String(),
            ],
            'inspected_count' => $inspectedCount,
            'matched_count' => count($rankedItems),
            'items' => $rankedItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, list<string>>  $rules
     * @return array<string, mixed>|null
     */
    private function triageMessage(
        array $message,
        string $accountEmail,
        GmailTriageDateRange $range,
        array $rules,
    ): ?array {
        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
        $headers = $this->extractHeaders($payload);
        $subject = trim((string) ($headers['Subject'] ?? ''));
        $fromHeader = trim((string) ($headers['From'] ?? ''));
        $senderEmail = $this->extractEmailAddress($fromHeader);

        if ($senderEmail === '' || $senderEmail === $accountEmail) {
            return null;
        }

        $receivedAt = $this->resolveReceivedAt($message);

        if ($receivedAt === null || $receivedAt->lt($range->start) || ($range->end !== null && $receivedAt->gt($range->end))) {
            return null;
        }

        $labels = array_values(array_filter(
            $message['labelIds'] ?? [],
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        $normalizedLabels = array_map('strtolower', $labels);
        $body = $this->extractBodyText($payload);
        $analysisText = strtolower(trim(implode("\n", array_filter([
            $subject,
            (string) ($message['snippet'] ?? ''),
            $body,
        ]))));
        $bodyAndSnippetText = strtolower(trim(implode("\n", array_filter([
            (string) ($message['snippet'] ?? ''),
            $body,
        ]))));

        $automated = $this->looksAutomated($senderEmail, $subject, $headers, $normalizedLabels, $rules);
        $score = 0;
        $reasons = [];
        $hasPriorityOverride = false;

        if (in_array($senderEmail, $rules['priority_senders'], true)) {
            $score += 3;
            $reasons[] = 'priority sender';
            $hasPriorityOverride = true;
        }

        $senderDomain = $this->extractDomain($senderEmail);

        if ($senderDomain !== '' && in_array($senderDomain, $rules['priority_domains'], true)) {
            $score += 2;
            $reasons[] = 'priority domain';
            $hasPriorityOverride = true;
        }

        if (array_intersect($normalizedLabels, $rules['priority_labels']) !== []) {
            $score += 1;
            $reasons[] = 'priority label';
        }

        if ($this->containsDirectQuestion($bodyAndSnippetText)) {
            $score += 4;
            $reasons[] = 'direct question';
        }

        if ($this->containsFollowUp($analysisText)) {
            $score += 3;
            $reasons[] = 'follow-up';
        }

        if ($this->containsUrgency($analysisText)) {
            $score += 3;
            $reasons[] = 'time-sensitive';
        }

        if ($this->containsScheduling($analysisText)) {
            $score += 2;
            $reasons[] = 'scheduling or travel';
        }

        if ($this->containsBusinessAdmin($analysisText)) {
            $score += 2;
            $reasons[] = 'money or admin';
        }

        if (in_array('unread', $normalizedLabels, true)) {
            $score += 1;
        }

        if (in_array('important', $normalizedLabels, true) || in_array('starred', $normalizedLabels, true)) {
            $score += 1;
        }

        if ($receivedAt->gte(CarbonImmutable::now($receivedAt->getTimezone())->subDay())) {
            $score += 1;
        }

        if ($automated && ! $hasPriorityOverride) {
            return null;
        }

        if ($score < 3) {
            return null;
        }

        $urgency = $this->determineUrgency($score, $analysisText);

        return [
            'message_id' => (string) ($message['id'] ?? ''),
            'thread_id' => (string) ($message['threadId'] ?? ''),
            'from' => $fromHeader,
            'subject' => $subject,
            'received_at' => $receivedAt->toIso8601String(),
            'urgency' => $urgency,
            'reason' => implode(', ', array_slice(array_values(array_unique($reasons)), 0, 3)),
            'next_action' => $this->determineNextAction($analysisText, $urgency),
            'confidence' => round(min(0.99, 0.35 + ($score * 0.08)), 2),
            '_score' => $score,
        ];
    }

    private function buildQuery(GmailTriageDateRange $range, ?string $query): string
    {
        $parts = [
            'in:inbox',
            'after:'.$range->start->startOfDay()->format('Y/m/d'),
        ];

        if ($range->end !== null) {
            $parts[] = 'before:'.$range->end->addDay()->startOfDay()->format('Y/m/d');
        }

        $normalizedQuery = trim((string) $query);

        if ($normalizedQuery !== '') {
            $parts[] = $normalizedQuery;
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function extractHeaders(array $payload): array
    {
        $result = [];

        foreach ($payload['headers'] ?? [] as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;

            if (! is_string($name) || ! is_string($value) || $name === '') {
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }

    private function extractEmailAddress(string $header): string
    {
        if (preg_match('/<([^>]+)>/', $header, $matches) === 1) {
            return strtolower(trim($matches[1]));
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $header, $matches) === 1) {
            return strtolower(trim($matches[0]));
        }

        return '';
    }

    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);

        return isset($parts[1]) ? strtolower($parts[1]) : '';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveReceivedAt(array $message): ?CarbonImmutable
    {
        $internalDate = $message['internalDate'] ?? null;

        if (is_numeric($internalDate)) {
            return CarbonImmutable::createFromTimestampMs((int) $internalDate, date_default_timezone_get());
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractBodyText(array $payload): string
    {
        $direct = $this->decodeBodyData($payload['body']['data'] ?? null);

        if ($direct !== null && $direct !== '') {
            return $direct;
        }

        foreach ($payload['parts'] ?? [] as $part) {
            if (! is_array($part)) {
                continue;
            }

            $body = $this->extractBodyText($part);

            if ($body !== '') {
                return $body;
            }
        }

        return '';
    }

    private function decodeBodyData(mixed $data): ?string
    {
        if (! is_string($data) || $data === '') {
            return null;
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : trim($decoded);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<string>  $labels
     * @param  array<string, list<string>>  $rules
     */
    private function looksAutomated(
        string $senderEmail,
        string $subject,
        array $headers,
        array $labels,
        array $rules,
    ): bool {
        if (in_array($senderEmail, $rules['ignore_senders'], true)) {
            return true;
        }

        $senderDomain = $this->extractDomain($senderEmail);

        if ($senderDomain !== '' && in_array($senderDomain, $rules['ignore_domains'], true)) {
            return true;
        }

        if ($labels !== [] && array_intersect($labels, ['category_promotions', 'category_updates', 'category_forums']) !== []) {
            return true;
        }

        if (isset($headers['List-Unsubscribe'])) {
            return true;
        }

        if (preg_match('/(?:^|[._-])(no-?reply|donotreply|mailer-daemon|notifications?)(?:@|$)/i', $senderEmail) === 1) {
            return true;
        }

        $normalizedSubject = strtolower($subject);

        foreach ($rules['ignore_subject_keywords'] as $keyword) {
            if ($keyword !== '' && str_contains($normalizedSubject, $keyword)) {
                return true;
            }
        }

        return preg_match('/\b(newsletter|digest|unsubscribe|promo|promotion)\b/i', $normalizedSubject) === 1;
    }

    private function containsDirectQuestion(string $text): bool
    {
        return preg_match('/\?\s*$/m', $text) === 1
            || preg_match('/\b(please confirm|can you|could you|would you|do you have|are you able|let me know|reply|respond)\b/i', $text) === 1
            || preg_match('/\b(what do you think|does this work for you|is this okay|can we|could we)\b/i', $text) === 1
            || preg_match('/\b(confirm|approve|reply|respond)\b.{0,25}\b(today|tomorrow|asap|soon)\b/i', $text) === 1;
    }

    private function containsFollowUp(string $text): bool
    {
        return preg_match('/\b(following up|follow up|checking in|circling back|gentle reminder|bumping this)\b/i', $text) === 1;
    }

    private function containsUrgency(string $text): bool
    {
        return preg_match('/\b(today|tomorrow|urgent|asap|deadline|by eod|end of day|before \d{1,2}(?::\d{2})?)\b/i', $text) === 1;
    }

    private function containsScheduling(string $text): bool
    {
        return preg_match('/\b(meeting|schedule|reschedule|calendar|invite|flight|train|hotel|travel)\b/i', $text) === 1;
    }

    private function containsBusinessAdmin(string $text): bool
    {
        return preg_match('/\b(invoice|payment|contract|agreement|legal|tax|bank|signature|sign)\b/i', $text) === 1;
    }

    private function determineUrgency(int $score, string $text): string
    {
        if ($score >= 7 || preg_match('/\b(today|urgent|asap|before \d{1,2}(?::\d{2})?)\b/i', $text) === 1) {
            return 'today';
        }

        if ($score >= 4) {
            return 'soon';
        }

        return 'watch';
    }

    private function determineNextAction(string $text, string $urgency): string
    {
        if ($this->containsDirectQuestion($text) || $this->containsFollowUp($text)) {
            return $urgency === 'today' ? 'Reply today.' : 'Reply soon.';
        }

        if ($this->containsScheduling($text)) {
            return 'Read and confirm the logistics.';
        }

        if ($this->containsBusinessAdmin($text)) {
            return 'Review the details and respond if needed.';
        }

        return $urgency === 'today' ? 'Read today.' : 'Read soon.';
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadRules(?string $rulesPath): array
    {
        $defaults = [
            'priority_senders' => ['haveforeningenkildebo@gmail.com'],
            'priority_domains' => ['ase.dk'],
            'priority_labels' => ['important', 'starred'],
            'ignore_senders' => [],
            'ignore_domains' => [],
            'ignore_subject_keywords' => [
                'newsletter',
                'digest',
                'job alert',
                'job alerts',
                'apply now',
                'looked at your profile',
                'weekly digest',
                'big savings',
                'price drop',
            ],
        ];

        $path = is_string($rulesPath) ? trim($rulesPath) : '';

        if ($path === '') {
            return $defaults;
        }

        if (! is_file($path)) {
            throw new InvalidArgumentException("Priority rules file not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Priority rules file is not valid JSON: {$path}");
        }

        foreach ($defaults as $key => $fallback) {
            $values = $decoded[$key] ?? $fallback;

            if (! is_array($values)) {
                $defaults[$key] = $fallback;

                continue;
            }

            $defaults[$key] = array_values(array_unique(array_map(
                static fn (string $value): string => strtolower(trim($value)),
                array_filter($values, static fn (mixed $value): bool => is_string($value) && trim($value) !== ''),
            )));
        }

        return $defaults;
    }
}
