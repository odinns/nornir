<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntakeRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope_snapshot' => 'array',
            'importer_options' => 'array',
        ];
    }
}
