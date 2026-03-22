<?php
// app/Services/TransferService.php

namespace App\Services;

use App\Models\Account;
use App\Models\Transfer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    protected $maxDailyAmount = 10000; // MAD

    /**
     * Initiate a transfer
     */
    public function initiateTransfer(User $user, array $data): Transfer
    {
        return DB::transaction(function () use ($user, $data) {
            $sourceAccount = Account::findOrFail($data['source_account_id']);
            $destinationAccount = Account::findOrFail($data['destination_account_id']);

            // Validate transfer rules
            $this->validateTransfer($user, $sourceAccount, $destinationAccount, $data['amount']);

            // Create transfer
            $transfer = Transfer::create([
                'amount' => $data['amount'],
                'status' => Transfer::STATUS_PENDING,
                'reason' => $data['reason'] ?? null,
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'initiated_by' => $user->id,
            ]);

            // Execute transfer if possible
            if ($transfer->canBeExecuted()) {
                $this->executeTransfer($transfer);
            } else {
                $transfer->markAsFailed('Règles de virement non respectées.');
            }

            return $transfer->load(['sourceAccount', 'destinationAccount', 'initiator']);
        });
    }

    /**
     * Validate transfer rules
     */
    private function validateTransfer(User $user, Account $source, Account $destination, float $amount): void
    {
        // Check if accounts are active
        if (!$source->isActive()) {
            throw ValidationException::withMessages([
                'source_account' => ['Le compte source n\'est pas actif.'],
            ]);
        }

        if (!$destination->isActive()) {
            throw ValidationException::withMessages([
                'destination_account' => ['Le compte destination n\'est pas actif.'],
            ]);
        }

        // Check if transfer to same account
        if ($source->id === $destination->id) {
            throw ValidationException::withMessages([
                'account' => ['Le virement vers le même compte est interdit.'],
            ]);
        }

        // Check if user has access to source account
        if (!$source->owners()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'source_account' => ['Vous n\'êtes pas titulaire du compte source.'],
            ]);
        }

        // Check daily limit
        $todayTransfers = Transfer::where('source_account_id', $source->id)
            ->where('initiated_by', $user->id)
            ->whereDate('created_at', today())
            ->where('status', Transfer::STATUS_COMPLETED)
            ->sum('amount');

        if ($todayTransfers + $amount > $this->maxDailyAmount) {
            throw ValidationException::withMessages([
                'amount' => ['Limite journalière de ' . $this->maxDailyAmount . ' MAD dépassée.'],
            ]);
        }

        // Check if source is minor account and user is guardian
        if ($source->isMineur()) {
            $guardian = $source->getGuardian();
            if (!$guardian || $guardian->id !== $user->id) {
                throw ValidationException::withMessages([
                    'account' => ['Seul le tuteur peut initier des virements depuis un compte mineur.'],
                ]);
            }
        }

        // Check sufficient balance
        if (!$source->hasSufficientBalanceForWithdrawal($amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Solde insuffisant.'],
            ]);
        }

        // Check withdrawal limits
        if (!$source->canWithdraw()) {
            throw ValidationException::withMessages([
                'account' => ['Limite de retraits mensuels atteinte.'],
            ]);
        }
    }

    /**
     * Execute transfer
     */
    public function executeTransfer(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $sourceAccount = $transfer->sourceAccount;
            $destinationAccount = $transfer->destinationAccount;

            // Update balances
            $sourceAccount->balance -= $transfer->amount;
            $sourceAccount->save();

            $destinationAccount->balance += $transfer->amount;
            $destinationAccount->save();

            // Increment withdrawal count for source account
            if ($sourceAccount->isEpargne() || $sourceAccount->isMineur()) {
                $sourceAccount->monthly_withdrawal_count++;
                $sourceAccount->save();
            }

            // Mark transfer as completed
            $transfer->markAsCompleted();

            // Create transaction records
            Transaction::createFromTransfer($transfer);
        });
    }

    /**
     * Get transfer details
     */
    public function getTransferDetails(User $user, int $transferId): Transfer
    {
        $transfer = Transfer::with(['sourceAccount', 'destinationAccount', 'initiator', 'transaction'])
            ->findOrFail($transferId);

        // Check if user has access to this transfer
        if ($transfer->initiated_by !== $user->id && 
            !$transfer->sourceAccount->owners()->where('user_id', $user->id)->exists() &&
            !$transfer->destinationAccount->owners()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'transfer' => ['Vous n\'avez pas accès à ce virement.'],
            ]);
        }

        return $transfer;
    }

    /**
     * Reset monthly withdrawal counts (to be called by scheduler)
     */
    public function resetMonthlyWithdrawalCounts(): void
    {
        Account::whereIn('type', [Account::TYPE_EPARGNE, Account::TYPE_MINEUR])
            ->update(['monthly_withdrawal_count' => 0]);
    }
}