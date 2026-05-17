<?php

namespace Extensions\pettycash\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PettyCashTransaction extends Model
{
    protected $table = 'petty_cash_transactions';

    protected $fillable = [
        'user_id', 'type', 'amount', 'category',
        'description', 'transaction_date', 'created_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
