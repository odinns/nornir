<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $canonical_key
 * @property CarbonImmutable|null $published_at
 * @property string|null $title
 * @property string|null $content
 */
class FacebookComment extends Model
{
    protected $table = 'facebook_comments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'raw_comment' => 'array',
        ];
    }
}
