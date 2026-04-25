<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $identifier
 * @property string|null $display_name
 */
class AppleMessagesParticipant extends Model
{
    protected $table = 'apple_messages_participants';

    protected $guarded = [];
}
