<?php

namespace Extensions\pettycash\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashCategory extends Model
{
    protected $table = 'petty_cash_categories';

    protected $fillable = ['name', 'sort_order', 'status'];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
