<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaSenderSession extends Model
{
    use HasFactory;

    protected $table = 'notifications.wa_sender_sessions';

    protected $fillable = [
        'schema_name',
        'name',
        'phone_number',
        'status',
        'account_protection',
        'log_messages',
        'read_incoming_messages',
        'webhook_url',
        'webhook_enabled',
        'webhook_events',
        'api_key',
        'webhook_secret',
        'wasender_session_id',
    ];

    protected $casts = [
        'account_protection' => 'boolean',
        'log_messages' => 'boolean',
        'read_incoming_messages' => 'boolean',
        'webhook_enabled' => 'boolean',
        'webhook_events' => 'array',
    ];

    protected $hidden = [
        'webhook_secret',
    ];
}
