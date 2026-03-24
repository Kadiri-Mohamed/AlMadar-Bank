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

    public static function createFromTransfer(Transfer $transfer): self
    {
        // Debit transaction on source account
        $debitTransaction = self::create([
            'amount' => -$transfer->amount,
            'type' => 'TRANSFER',
            'status' => $transfer->status,
            'description' => "Virement vers le compte {$transfer->destinationAccount->account_number}",
            'account_id' => $transfer->source_account_id,
            'transfer_id' => $transfer->id,
        ]);

        // Credit transaction on destination account
        self::create([
            'amount' => $transfer->amount,
            'type' => 'TRANSFER',
            'status' => $transfer->status,
            'description' => "Virement depuis le compte {$transfer->sourceAccount->account_number}",
            'account_id' => $transfer->destination_account_id,
            'transfer_id' => $transfer->id,
        ]);

        return $debitTransaction;
    }

    public static function createFee(Account $account, float $amount, bool $failed = false): self
    {
        return self::create([
            'amount' => -$amount,
            'type' => $failed ? 'FEE_FAILED' : 'FEE',
            'status' => $failed ? 'FAILED' : 'COMPLETED',
            'description' => 'Frais de tenue de compte mensuels',
            'account_id' => $account->id,
        ]);
    }

    public static function createInterest(Account $account, float $amount): self
    {
        return self::create([
            'amount' => $amount,
            'type' => 'INTEREST',
            'status' => 'COMPLETED',
            'description' => "Intérêts mensuels ({$account->interest_rate}%)",
            'account_id' => $account->id,
        ]);
    }
}
