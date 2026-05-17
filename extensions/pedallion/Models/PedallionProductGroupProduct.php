<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionProductGroupProduct extends Model
{
    protected $table = 'pedallion_product_group_products';

    protected $fillable = [
        'pedallion_product_group_id',
        'product_id',
    ];

    protected $casts = [
        'pedallion_product_group_id' => 'integer',
        'product_id'                  => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(PedallionProductGroup::class, 'pedallion_product_group_id');
    }
}
