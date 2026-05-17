<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionOrderProduct extends Model
{
    protected $table = 'pedallion_order_products';

    protected $fillable = [
        'pedallion_order_id',
        'pedallion_sku',
        'product_name',
        'quantity',
        'price',
        'total',
        'product_id',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'price'      => 'decimal:2',
        'total'      => 'decimal:2',
        'product_id' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(PedallionOrder::class, 'pedallion_order_id');
    }
}
