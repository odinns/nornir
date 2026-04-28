<?php

declare(strict_types=1);

namespace App\Actions\Gmail;

use App\Services\Gmail\GmailClientFactory;
use App\Services\Gmail\GmailImportantMailScorer;
use App\Services\Gmail\GmailTriageDateRange;
use App\Services\Gmail\GmailTriageDateRangeParser;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * @phpstan-import-type ScoredGmailImportantMailItem from GmailImportantMailScorer
 *
 * @phpstan-type GmailTriageItem array{message_id: string, thread_id: string, from: string, subject: string, received_at: string, urgency: string, reason: string, next_action: string, confidence: float}
 * @phpstan-type GmailTriageResult array{account_email: string, query: string, window: array{start: string, end: string|null}, inspected_count: int, matched_count: int, items: list<GmailTriageItem>}
 */
class TriageImportantMailAction
{
    public function __construct(
        private readonly GmailClientFactory $gmailClientFactory,
        private readonly GmailTriageDateRangeParser $dateRangeParser,
        private readonly GmailImportantMailScorer $importantMailScorer,
    ) {}

    /**
     * @return GmailTriageResult
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
        $rules = $this->importantMailScorer->loadRules($rulesPath);
        $gmailQuery = $this->buildQuery($range, $query);

        $pageToken = null;
        $inspectedCount = 0;
        /** @var list<ScoredGmailImportantMailItem> $items */
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
                $item = $this->importantMailScorer->scoreApiMessage($message, $accountEmail, $range, $rules);
                $inspectedCount++;

                if ($item !== null) {
                    $items[] = $item;
                }
            }
        } while ($pageToken !== null);

        $items = $this->importantMailScorer->rank($items);

        /** @var list<GmailTriageItem> $rankedItems */
        $rankedItems = array_map(static function (array $item): array {
            return [
                'message_id' => $item['message_id'],
                'thread_id' => $item['thread_id'],
                'from' => $item['from'],
                'subject' => $item['subject'],
                'received_at' => $item['received_at'],
                'urgency' => $item['urgency'],
                'reason' => $item['reason'],
                'next_action' => $item['next_action'],
                'confidence' => $item['confidence'],
            ];
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

    private function buildQuery(GmailTriageDateRange $range, ?string $query): string
    {
        $parts = [
            'in:inbox',
            'after:'.$range->start->startOfDay()->format('Y/m/d'),
        ];

        if ($range->end instanceof CarbonImmutable) {
            $parts[] = 'before:'.$range->end->addDay()->startOfDay()->format('Y/m/d');
        }

        $normalizedQuery = trim((string) $query);

        if ($normalizedQuery !== '') {
            $parts[] = $normalizedQuery;
        }

        return implode(' ', $parts);
    }
}
