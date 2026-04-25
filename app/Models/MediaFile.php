<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $source_file_id
 * @property string $volume_label
 * @property string|null $volume_mount_path
 * @property string $directory_full_path
 * @property string|null $event_label
 * @property string|null $event_date
 * @property string $basename
 * @property string|null $extension
 * @property string $normalized_file_type
 * @property int|null $size_bytes
 * @property CarbonImmutable|null $fs_created_at
 * @property CarbonImmutable|null $fs_modified_at
 * @property string|null $duplicate_key
 */
class MediaFile extends Model
{
    protected $table = 'media_files';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_file_id' => 'integer',
            'size_bytes' => 'integer',
            'fs_created_at' => 'immutable_datetime',
            'fs_modified_at' => 'immutable_datetime',
        ];
    }
}
