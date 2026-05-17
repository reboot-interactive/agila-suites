<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionProductLink extends Model
{
    protected $table = 'pedallion_product_links';

    protected $fillable = [
        'product_id',
        'pedallion_sku',
        'sync_status',
        'sync_error',
        'last_pushed_at',
    ];

    protected $casts = [
        'product_id'     => 'integer',
        'last_pushed_at' => 'datetime',
    ];
}
