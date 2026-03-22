<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'amount',
        'type',
        'status',
        'description',
        'account_id',
        'transfer_id',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
