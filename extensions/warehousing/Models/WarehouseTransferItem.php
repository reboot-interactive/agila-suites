<?php

namespace Extensions\warehousing\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseTransferItem extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'warehouse_transfer_id',
        'product_id',
        'product_option_value_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'product_option_value_id' => 'integer',
    ];

    public function transfer()
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }
}
