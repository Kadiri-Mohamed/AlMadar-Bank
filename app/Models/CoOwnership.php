<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoOwnership extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'accepted_closure',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
