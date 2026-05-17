<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionApiLog extends Model
{
    protected $table = 'pedallion_api_logs';

    protected $fillable = [
        'method',
        'endpoint',
        'status_code',
        'request_body',
        'response_body',
        'duration_ms',
    ];

    protected $casts = [
        'request_body'  => 'array',
        'response_body' => 'array',
        'status_code'   => 'integer',
        'duration_ms'   => 'integer',
    ];
}
