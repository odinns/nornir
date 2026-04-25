<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $source_type
 * @property string $access_mode
 * @property string $source_locator
 * @property array<string, mixed> $scope_snapshot
 * @property array<string, mixed>|null $importer_options
 */
class IntakeRecord extends Model
{
    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'source_locator' => 'string',
            'scope_snapshot' => 'array',
            'importer_options' => 'array',
        ];
    }
}
