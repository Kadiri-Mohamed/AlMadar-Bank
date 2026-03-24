<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    // Type constants
    const TYPE_COURANT = 'COURANT';
    const TYPE_EPARGNE = 'EPARGNE';
    const TYPE_MINEUR = 'MINEUR';

    // Status constants
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_BLOCKED = 'BLOCKED';
    const STATUS_CLOSED = 'CLOSED';

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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCourant(): bool
    {
        return $this->type === self::TYPE_COURANT;
    }

    public function isEpargne(): bool
    {
        return $this->type === self::TYPE_EPARGNE;
    }

    public function isMineur(): bool
    {
        return $this->type === self::TYPE_MINEUR;
    }

    public function canWithdraw(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->isEpargne() && $this->monthly_withdrawal_count >= 3) {
            return false;
        }

        if ($this->isMineur() && $this->monthly_withdrawal_count >= 2) {
            return false;
        }

        return true;
    }

    public function hasSufficientBalanceForWithdrawal($amount): bool
    {
        if ($this->isCourant() && $this->overdraft_limit) {
            return $this->balance + $this->overdraft_limit >= $amount;
        }

        return $this->balance >= $amount;
    }

    public function canBeClosed(): bool
    {
        if ($this->balance != 0) {
            return false;
        }

        if ($this->isMineur()) {
            $guardianConsent = $this->coOwnerships()
                ->whereHas('user', function ($query) {
                    $query->whereHas('wards', function ($q) {
                        $q->where('minor_id', $this->owners()->first()->id ?? 0);
                    });
                })
                ->where('accepted_closure', true)
                ->exists();

            if (!$guardianConsent) {
                return false;
            }
        }

        return true;
    }

    public function getGuardian()
    {
        if (!$this->isMineur()) {
            return null;
        }

        $minor = $this->owners()->first();
        return $minor ? $minor->guardian()->first()?->guardian : null;
    }
}
