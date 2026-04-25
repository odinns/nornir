<?php

declare(strict_types=1);

namespace App\Search\Builders;

use App\Data\Search\SearchDocumentData;
use App\Models\MediaFile;
use App\Search\Builders\Concerns\BuildsSearchDocuments;
use App\Search\SourceSearchDocumentBuilder;

final class MediaFileSearchDocumentBuilder implements SourceSearchDocumentBuilder
{
    use BuildsSearchDocuments;

    public function sourceType(): string
    {
        return 'media';
    }

    public function build(): iterable
    {
        foreach (MediaFile::query()->lazyById() as $file) {
            yield new SearchDocumentData(
                sourceType: 'media',
                sourceTable: 'media_files',
                sourceId: (string) $file->source_file_id,
                title: $file->basename,
                body: $this->joinText([$file->event_label, $file->normalized_file_type, $file->extension]),
                occurredAt: $file->fs_created_at ?? $file->fs_modified_at,
                urlOrLocator: $file->directory_full_path.'/'.$file->basename,
                metadata: [
                    'volume_label' => $file->volume_label,
                    'size_bytes' => $file->size_bytes,
                    'duplicate_key' => $file->duplicate_key,
                ],
            );
        }
    }
}
