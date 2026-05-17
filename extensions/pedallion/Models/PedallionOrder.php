<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionOrder extends Model
{
    protected $table = 'pedallion_orders';

    protected $fillable = [
        'order_number',
        'status',
        'total',
        'currency',
        'buyer_name',
        'shipping_address',
        'order_date',
        'paid_at',
        'shipped_at',
        'raw_payload',
        'erp_order_id',
    ];

    protected $casts = [
        'total'        => 'decimal:2',
        'order_date'   => 'datetime',
        'paid_at'      => 'datetime',
        'shipped_at'   => 'datetime',
        'raw_payload'  => 'array',
        'erp_order_id' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(PedallionOrderProduct::class, 'pedallion_order_id');
    }
}
