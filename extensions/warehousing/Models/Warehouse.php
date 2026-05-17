<?php

namespace Extensions\warehousing\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_default',
        'is_sellable',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_sellable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeSellable($query)
    {
        return $query->where('is_sellable', true);
    }

    public function inventory()
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function transfersFrom()
    {
        return $this->hasMany(WarehouseTransfer::class, 'from_warehouse_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(WarehouseTransfer::class, 'to_warehouse_id');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
