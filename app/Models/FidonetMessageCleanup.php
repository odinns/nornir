<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $canonical_message_id
 * @property string|null $cleaned_authored_text
 */
class FidonetMessageCleanup extends Model
{
    protected $table = 'fidonet_message_cleanup';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_test_like' => 'boolean',
        ];
    }
}
