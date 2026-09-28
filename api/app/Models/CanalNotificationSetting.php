<?php

namespace App\Models;

use App\Enums\CanalNotificationTopic;
use Illuminate\Database\Eloquent\Model;

/**
 * Odchýlka člena tímu od predvolených notifikácií jeho roly (viď CanalRecipients).
 */
class CanalNotificationSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'topic' => CanalNotificationTopic::class,
        'enabled' => 'boolean',
    ];
}
