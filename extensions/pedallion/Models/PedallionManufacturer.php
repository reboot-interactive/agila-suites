<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionManufacturer extends Model
{
    protected $table = 'pedallion_manufacturers';

    protected $fillable = [
        'pedallion_manufacturer_id',
        'name',
    ];

    protected $casts = [
        'pedallion_manufacturer_id' => 'integer',
    ];
}
