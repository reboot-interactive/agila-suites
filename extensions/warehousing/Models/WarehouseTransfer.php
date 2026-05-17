<?php

namespace Extensions\warehousing\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseTransfer extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'reference',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'note',
        'voided_by_reference',
        'user_id',
        'user_name',
    ];

    public static function generateReference(): string
    {
        $prefix = 'TR' . now()->format('ym');

        $maxSeq = (int) static::where('reference', 'like', 'TR%-%')
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(reference, "-", -1) AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        $seq = $maxSeq + 1;

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(WarehouseTransferItem::class);
    }
}
