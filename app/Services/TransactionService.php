<?php
// app/Services/TransactionService.php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    /**
     * Get account transactions with filters
     */
    public function getAccountTransactions(User $user, int $accountId, array $filters = []): array
    {
        $account = Account::findOrFail($accountId);

        // Check if user has access to this account
        if (!$account->owners()->where('user_id', $user->id)->exists()) {
            throw new ModelNotFoundException('Compte non trouvé.');
        }

        $query = Transaction::where('account_id', $accountId)
            ->with('transfer');

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        // Order by date
        $query->latest();

        // Paginate
        $perPage = $filters['per_page'] ?? 20;
        
        return $query->paginate($perPage)->toArray();
    }

    /**
     * Get transaction details
     */
    public function getTransactionDetails(User $user, int $transactionId): Transaction
    {
        $transaction = Transaction::with(['account', 'transfer'])
            ->findOrFail($transactionId);

        // Check if user has access to this transaction
        if (!$transaction->account->owners()->where('user_id', $user->id)->exists()) {
            throw new ModelNotFoundException('Transaction non trouvée.');
        }

        return $transaction;
    }

    /**
     * Apply monthly fees for current accounts
     */
    public function applyMonthlyFees(float $feeAmount = 50): void
    {
        $accounts = Account::where('type', Account::TYPE_COURANT)
            ->where('status', Account::STATUS_ACTIVE)
            ->get();

        foreach ($accounts as $account) {
            if ($account->balance >= $feeAmount) {
                // Apply fee
                $account->balance -= $feeAmount;
                $account->save();

                Transaction::createFee($account, $feeAmount, false);
            } else {
                // Block account and record failed fee
                $account->status = Account::STATUS_BLOCKED;
                $account->blocked_reason = 'Solde insuffisant pour les frais mensuels';
                $account->save();

                Transaction::createFee($account, $feeAmount, true);
            }
        }
    }

    /**
     * Apply monthly interests for savings and minor accounts
     */
    public function applyMonthlyInterests(): void
    {
        $accounts = Account::whereIn('type', [Account::TYPE_EPARGNE, Account::TYPE_MINEUR])
            ->where('status', Account::STATUS_ACTIVE)
            ->whereNotNull('interest_rate')
            ->get();

        foreach ($accounts as $account) {
            if ($account->balance > 0) {
                $monthlyInterest = $account->balance * ($account->interest_rate / 100 / 12);
                $account->balance += $monthlyInterest;
                $account->save();

                Transaction::createInterest($account, $monthlyInterest);
            }
        }
    }
}