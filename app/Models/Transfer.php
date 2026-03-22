<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'amount',
        'status',
        'reason',
        'source_account_id',
        'destination_account_id',
        'initiated_by',
        'executed_at',
    ];

    const STATUS_PENDING = 'PENDING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_FAILED = 'FAILED';

    public function sourceAccount()
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationAccount()
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}
