<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionOrderStatusMap extends Model
{
    protected $table = 'pedallion_order_status_map';

    protected $fillable = [
        'pedallion_status',
        'pedallion_status_label',
        'context',
        'order_status_id',
    ];

    protected $casts = [
        'order_status_id' => 'integer',
    ];
}
