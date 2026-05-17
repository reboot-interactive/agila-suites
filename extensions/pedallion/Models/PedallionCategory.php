<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionCategory extends Model
{
    protected $table = 'pedallion_categories';

    protected $fillable = [
        'pedallion_category_id',
        'parent_id',
        'name',
        'level',
        'leaf',
    ];

    protected $casts = [
        'pedallion_category_id' => 'integer',
        'parent_id'             => 'integer',
        'level'                 => 'integer',
        'leaf'                  => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'pedallion_category_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'pedallion_category_id');
    }
}
