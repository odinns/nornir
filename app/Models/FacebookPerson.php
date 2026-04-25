<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $display_name
 */
class FacebookPerson extends Model
{
    protected $table = 'facebook_people';

    protected $guarded = [];
}
