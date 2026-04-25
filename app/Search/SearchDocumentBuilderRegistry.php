<?php

declare(strict_types=1);

namespace App\Search;

use App\Search\Builders\AppleHealthSearchDocumentBuilder;
use App\Search\Builders\AppleMessagesSearchDocumentBuilder;
use App\Search\Builders\ChatGptSearchDocumentBuilder;
use App\Search\Builders\FacebookSearchDocumentBuilder;
use App\Search\Builders\FidonetSearchDocumentBuilder;
use App\Search\Builders\GmailSearchDocumentBuilder;
use App\Search\Builders\InstagramSearchDocumentBuilder;
use App\Search\Builders\LinkedinSearchDocumentBuilder;
use App\Search\Builders\MediaFileSearchDocumentBuilder;
use App\Search\Builders\TwitterSearchDocumentBuilder;
use App\Search\Builders\WaybackSearchDocumentBuilder;
use InvalidArgumentException;

final readonly class SearchDocumentBuilderRegistry
{
    /** @var list<SourceSearchDocumentBuilder> */
    private array $builders;

    public function __construct()
    {
        $this->builders = [
            new ChatGptSearchDocumentBuilder,
            new GmailSearchDocumentBuilder,
            new AppleMessagesSearchDocumentBuilder,
            new TwitterSearchDocumentBuilder,
            new LinkedinSearchDocumentBuilder,
            new FacebookSearchDocumentBuilder,
            new InstagramSearchDocumentBuilder,
            new FidonetSearchDocumentBuilder,
            new WaybackSearchDocumentBuilder,
            new MediaFileSearchDocumentBuilder,
            new AppleHealthSearchDocumentBuilder,
        ];
    }

    /**
     * @return list<string>
     */
    public function sourceTypes(): array
    {
        return array_map(
            static fn (SourceSearchDocumentBuilder $builder): string => $builder->sourceType(),
            $this->builders,
        );
    }

    /**
     * @return list<SourceSearchDocumentBuilder>
     */
    public function builders(?string $sourceType = null): array
    {
        if ($sourceType === null) {
            return $this->builders;
        }

        $builders = array_values(array_filter(
            $this->builders,
            static fn (SourceSearchDocumentBuilder $builder): bool => $builder->sourceType() === $sourceType,
        ));

        if ($builders === []) {
            throw new InvalidArgumentException("Unknown search source [{$sourceType}].");
        }

        return $builders;
    }
}
