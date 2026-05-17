<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionProductGroup extends Model
{
    protected $table = 'pedallion_product_groups';

    protected $fillable = [
        'name',
        'catalog_category_ids',
        'manufacturer_ids',
        'pedallion_category_id',
        'condition',
    ];

    protected $casts = [
        'catalog_category_ids'  => 'array',
        'manufacturer_ids'      => 'array',
        'pedallion_category_id' => 'integer',
    ];

    public function groupProducts()
    {
        return $this->hasMany(PedallionProductGroupProduct::class, 'pedallion_product_group_id');
    }
}
