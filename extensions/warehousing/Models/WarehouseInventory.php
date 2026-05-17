<?php

namespace Extensions\warehousing\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseInventory extends Model
{
    protected $table = 'warehouse_inventory';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'product_option_value_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'product_option_value_id' => 'integer',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
