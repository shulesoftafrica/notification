<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsSession extends Model
{
    use HasFactory;

    protected $table = 'notifications.sms_sessions';

    protected $fillable = [
        'schema_name',
        'sender_name',
        'provider',
        'status',
    ];

}