<?php

namespace Extensions\pettycash\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PettyCashUserRole extends Model
{
    protected $table = 'petty_cash_user_roles';

    protected $fillable = ['user_id', 'role'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
