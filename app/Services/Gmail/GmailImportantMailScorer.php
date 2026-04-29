<?php

declare(strict_types=1);

namespace App\Services\Gmail;

use App\Models\GmailMessage;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * @phpstan-type GmailImportantMailRules array{priority_senders: list<string>, priority_domains: list<string>, priority_labels: list<string>, ignore_senders: list<string>, ignore_domains: list<string>, ignore_subject_keywords: list<string>}
 * @phpstan-type GmailImportantMailCandidate array{message_id: string, thread_id: string, from: string, to: string, cc: string, subject: string, received_at: CarbonImmutable|null, labels: list<string>, snippet: string, body_plain: string, body_html: string, headers: array<string, string>}
 * @phpstan-type ScoredGmailImportantMailItem array{message_id: string, thread_id: string, from: string, to: string, cc: string, subject: string, received_at: string, urgency: string, reason: string, next_action: string, confidence: float, labels: list<string>, snippet: string, body_plain: string, body_html: string, _score: int}
 */
class GmailImportantMailScorer
{
    /**
     * @param  array<string, mixed>  $message
     * @param  GmailImportantMailRules  $rules
     * @return ScoredGmailImportantMailItem|null
     */
    public function scoreApiMessage(
        array $message,
        string $accountEmail,
        ?GmailTriageDateRange $range,
        array $rules,
    ): ?array {
        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
        $headers = $this->extractHeaders($payload);

        $candidate = [
            'message_id' => (string) ($message['id'] ?? ''),
            'thread_id' => (string) ($message['threadId'] ?? ''),
            'from' => trim((string) ($headers['From'] ?? '')),
            'to' => trim((string) ($headers['To'] ?? '')),
            'cc' => trim((string) ($headers['Cc'] ?? '')),
            'subject' => trim((string) ($headers['Subject'] ?? '')),
            'received_at' => $this->resolveApiReceivedAt($message),
            'labels' => array_values(array_filter(
                $message['labelIds'] ?? [],
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            )),
            'snippet' => (string) ($message['snippet'] ?? ''),
            'body_plain' => $this->extractBodyText($payload),
            'body_html' => '',
            'headers' => $headers,
        ];

        return $this->scoreCandidate($candidate, $accountEmail, $rules, $range);
    }

    /**
     * @param  GmailImportantMailRules  $rules
     * @return ScoredGmailImportantMailItem|null
     */
    public function scoreCanonicalMessage(GmailMessage $message, string $accountEmail, array $rules): ?array
    {
        $headers = [];

        foreach ($message->raw_headers ?? [] as $header) {
            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;

            if (is_string($name) && is_string($value) && $name !== '') {
                $headers[$name] = $value;
            }
        }

        $candidate = [
            'message_id' => $message->message_id,
            'thread_id' => $message->thread->thread_id,
            'from' => trim((string) $message->from_header),
            'to' => trim((string) $message->to_header),
            'cc' => trim((string) $message->cc_header),
            'subject' => trim((string) $message->subject),
            'received_at' => $message->message_received_at,
            'labels' => array_values($message->labels
                ->pluck('label_id')
                ->filter(static fn (mixed $label): bool => is_string($label) && $label !== '')
                ->all()),
            'snippet' => (string) $message->snippet,
            'body_plain' => (string) $message->body_plain,
            'body_html' => (string) $message->body_html,
            'headers' => $headers,
        ];

        return $this->scoreCandidate($candidate, $accountEmail, $rules, null);
    }

    /**
     * @param  list<ScoredGmailImportantMailItem>  $items
     * @return list<ScoredGmailImportantMailItem>
     */
    public function rank(array $items): array
    {
        usort($items, function (array $left, array $right): int {
            $scoreComparison = $right['_score'] <=> $left['_score'];

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strcmp($right['received_at'], $left['received_at']);
        });

        return $items;
    }

    /**
     * @return GmailImportantMailRules
     */
    public function loadRules(?string $rulesPath): array
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

    /**
     * @param  GmailImportantMailCandidate  $candidate
     * @param  GmailImportantMailRules  $rules
     * @return ScoredGmailImportantMailItem|null
     */
    private function scoreCandidate(array $candidate, string $accountEmail, array $rules, ?GmailTriageDateRange $range): ?array
    {
        $senderEmail = $this->extractEmailAddress($candidate['from']);

        if ($senderEmail === '' || $senderEmail === strtolower($accountEmail)) {
            return null;
        }

        $receivedAt = $candidate['received_at'];

        if (! $receivedAt instanceof CarbonImmutable) {
            return null;
        }

        if ($range instanceof GmailTriageDateRange && ($receivedAt->lt($range->start) || ($range->end instanceof CarbonImmutable && $receivedAt->gt($range->end)))) {
            return null;
        }

        $normalizedLabels = array_map(strtolower(...), $candidate['labels']);
        $analysisText = strtolower(trim(implode("\n", array_filter([
            $candidate['subject'],
            $candidate['snippet'],
            $candidate['body_plain'],
        ]))));
        $directPromptText = $this->directPromptText($candidate);

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

        if ($this->looksAutomated($candidate, $senderEmail, $normalizedLabels, $rules) && ! $hasPriorityOverride) {
            return null;
        }

        if (array_intersect($normalizedLabels, $rules['priority_labels']) !== []) {
            $score += 1;
            $reasons[] = 'priority label';
        }

        if ($this->containsDirectPrompt($directPromptText)) {
            $score += 4;
            $reasons[] = 'direct prompt';
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

        if ($score < 3) {
            return null;
        }

        $urgency = $this->determineUrgency($score, $analysisText);

        return [
            'message_id' => $candidate['message_id'],
            'thread_id' => $candidate['thread_id'],
            'from' => $candidate['from'],
            'to' => $candidate['to'],
            'cc' => $candidate['cc'],
            'subject' => $candidate['subject'],
            'received_at' => $receivedAt->toIso8601String(),
            'urgency' => $urgency,
            'reason' => implode(', ', array_slice(array_values(array_unique($reasons)), 0, 3)),
            'next_action' => $this->determineNextAction($analysisText, $urgency, $directPromptText),
            'confidence' => round(min(0.99, 0.35 + ($score * 0.08)), 2),
            'labels' => $candidate['labels'],
            'snippet' => $candidate['snippet'],
            'body_plain' => $candidate['body_plain'],
            'body_html' => $candidate['body_html'],
            '_score' => $score,
        ];
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
            if (! is_string($name)) {
                continue;
            }
            if (! is_string($value)) {
                continue;
            }
            if ($name === '') {
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
    private function resolveApiReceivedAt(array $message): ?CarbonImmutable
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
     * @param  GmailImportantMailCandidate  $candidate
     * @param  list<string>  $labels
     * @param  GmailImportantMailRules  $rules
     */
    private function looksAutomated(
        array $candidate,
        string $senderEmail,
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

        $headers = $candidate['headers'];

        if ($labels !== [] && array_intersect($labels, ['category_promotions', 'category_updates', 'category_forums', 'category_social']) !== []) {
            return true;
        }

        if ($this->hasBulkHeaderSignal($headers)) {
            return true;
        }

        if (preg_match('/(?:^|[._-])(no-?reply|donotreply|mailer-daemon|notifications?)(?:@|$)/i', $senderEmail) === 1) {
            return true;
        }

        if ($this->looksLikeLegacyBulkMail($candidate, $senderEmail)) {
            return true;
        }

        $normalizedSubject = strtolower($candidate['subject']);

        foreach ($rules['ignore_subject_keywords'] as $keyword) {
            if ($keyword !== '' && str_contains($normalizedSubject, $keyword)) {
                return true;
            }
        }

        return preg_match('/\b(newsletter|digest|unsubscribe|promo|promotion)\b/i', $normalizedSubject) === 1;
    }

    /**
     * @param  GmailImportantMailCandidate  $candidate
     */
    private function looksLikeLegacyBulkMail(array $candidate, string $senderEmail): bool
    {
        $senderDomain = $this->extractDomain($senderEmail);
        $senderLocalPart = explode('@', $senderEmail)[0] ?? '';
        $senderIdentity = strtolower(implode(' ', array_filter([
            $candidate['from'],
            $senderEmail,
            $senderLocalPart,
            $senderDomain,
        ])));
        $subject = strtolower($candidate['subject']);
        $bodyText = strtolower(trim(implode("\n", array_filter([
            $candidate['snippet'],
            $candidate['body_plain'],
        ]))));

        $hasSenderCue = preg_match('/\b(newsletter|nyhedsbrev|mailrobot|product-announce|members|reply3|overlords)\b/i', $senderIdentity) === 1;
        $hasSubjectCue = preg_match('/\b(newsletter|digest|sale|deals?|discount|voucher|top 10 deals)\b|tilbud|nyhedsbrev|karrieremail/i', $subject) === 1;

        if ($hasSenderCue || $hasSubjectCue) {
            return true;
        }

        $hasWeakBodyCue = preg_match('/\b(unsubscribe|afmeld|view in browser|tip en ven)\b/i', $bodyText) === 1;

        if (! $hasWeakBodyCue) {
            return false;
        }

        return preg_match('/\b(weekly|monthly|weekend|campaign|offers?|updates?|jobs?|karriere|rejser?)\b/i', $subject) === 1;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }

        return $normalized;
    }

    /**
     * @param  GmailImportantMailCandidate  $candidate
     */
    private function directPromptText(array $candidate): string
    {
        return strtolower(trim(implode("\n", array_filter([
            $candidate['subject'],
            $candidate['snippet'],
            mb_substr($candidate['body_plain'], 0, 1000),
        ]))));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function hasBulkHeaderSignal(array $headers): bool
    {
        $normalizedHeaders = $this->normalizeHeaders($headers);

        if (array_intersect(array_keys($normalizedHeaders), ['list-unsubscribe', 'list-id', 'list-help', 'list-owner']) !== []) {
            return true;
        }

        if (in_array(strtolower(trim($normalizedHeaders['precedence'] ?? '')), ['bulk', 'list'], true)) {
            return true;
        }

        $autoSubmitted = strtolower(trim($normalizedHeaders['auto-submitted'] ?? ''));

        return $autoSubmitted !== '' && $autoSubmitted !== 'no';
    }

    private function containsDirectPrompt(string $text): bool
    {
        return preg_match('/\b(can|could|would|will)\s+you\b/i', $text) === 1
            || preg_match('/\b(please\s+(confirm|approve|reply|respond|review|send|sign|update)|do you have|are you able|let me know)\b/i', $text) === 1
            || preg_match('/\b(what do you think|does this work for you|is this okay|can we|could we)\b/i', $text) === 1
            || preg_match('/\b(confirm|approve|reply|respond|review|send|sign|update)\b.{0,50}\b(today|tomorrow|asap|soon|before \d{1,2}(?::\d{2})?)\b/i', $text) === 1
            || preg_match('/\b(today|tomorrow|asap|soon|before \d{1,2}(?::\d{2})?)\b.{0,50}\b(confirm|approve|reply|respond|review|send|sign|update)\b/i', $text) === 1;
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

    private function determineNextAction(string $text, string $urgency, string $directPromptText): string
    {
        if ($this->containsDirectPrompt($directPromptText) || $this->containsFollowUp($text)) {
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
}
