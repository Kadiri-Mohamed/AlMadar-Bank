<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'account_number',
        'balance',
        'status',
        'type',
        'interest_rate',
        'overdraft_limit',
        'monthly_withdrawal_count',
        'blocked_reason',
    ];

    public function owners()
    {
        return $this->belongsToMany(User::class, 'co_ownerships')
            ->withPivot('accepted_closure')
            ->withTimestamps();
    }

    public function coOwnerships()
    {
        return $this->hasMany(CoOwnership::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(Transfer::class, 'source_account_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(Transfer::class, 'destination_account_id');
    }
}
