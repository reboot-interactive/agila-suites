<?php

namespace Extensions\pedallion\Models;

use Illuminate\Database\Eloquent\Model;

class PedallionSetting extends Model
{
    protected $table = 'pedallion_settings';

    protected $fillable = [
        'base_url',
        'api_key',
        'enabled',
        'logging_enabled',
        'sync_last_days',
        'last_category_sync_at',
        'last_manufacturer_sync_at',
        'last_product_sync_at',
        'last_order_sync_at',
        'last_stock_push_at',
    ];

    protected $casts = [
        'enabled'                   => 'boolean',
        'logging_enabled'           => 'boolean',
        'sync_last_days'            => 'integer',
        'last_category_sync_at'     => 'datetime',
        'last_manufacturer_sync_at' => 'datetime',
        'last_product_sync_at'      => 'datetime',
        'last_order_sync_at'        => 'datetime',
        'last_stock_push_at'        => 'datetime',
    ];

    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = encrypt($value);
    }

    public function getApiKeyAttribute($value)
    {
        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function setRawApiKey(string $value): void
    {
        $this->attributes['api_key'] = $value;
    }
}
