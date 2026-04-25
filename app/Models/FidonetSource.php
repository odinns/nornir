<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FidonetSource extends Model
{
    protected $table = 'fidonet_sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope_snapshot' => 'array',
        ];
    }
}
