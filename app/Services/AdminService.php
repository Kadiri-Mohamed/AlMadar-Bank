<?php
// app/Services/AdminService.php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdminService
{
    /**
     * Get all accounts (admin only)
     */
    public function getAllAccounts(array $filters = []): array
    {
        $query = Account::with(['owners', 'transactions' => function($q) {
            $q->latest()->limit(5);
        }]);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('account_number', 'like', '%' . $filters['search'] . '%')
                ->orWhereHas('owners', function($q) use ($filters) {
                    $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage)->toArray();
    }

    /**
     * Block account (admin only)
     */
    public function blockAccount(int $accountId, string $reason): Account
    {
        $account = Account::findOrFail($accountId);

        if ($account->isBlocked()) {
            throw ValidationException::withMessages([
                'account' => ['Ce compte est déjà bloqué.'],
            ]);
        }

        if ($account->isClosed()) {
            throw ValidationException::withMessages([
                'account' => ['Ce compte est déjà clôturé.'],
            ]);
        }

        $account->status = Account::STATUS_BLOCKED;
        $account->blocked_reason = $reason;
        $account->save();

        return $account->load('owners');
    }

    /**
     * Unblock account (admin only)
     */
    public function unblockAccount(int $accountId): Account
    {
        $account = Account::findOrFail($accountId);

        if (!$account->isBlocked()) {
            throw ValidationException::withMessages([
                'account' => ['Ce compte n\'est pas bloqué.'],
            ]);
        }

        $account->status = Account::STATUS_ACTIVE;
        $account->blocked_reason = null;
        $account->save();

        return $account->load('owners');
    }

    /**
     * Close account (admin only)
     */
    public function closeAccount(int $accountId): Account
    {
        $account = Account::findOrFail($accountId);

        if ($account->isClosed()) {
            throw ValidationException::withMessages([
                'account' => ['Ce compte est déjà clôturé.'],
            ]);
        }

        // Check if account can be closed
        if (!$account->canBeClosed()) {
            throw ValidationException::withMessages([
                'account' => ['Ce compte ne peut pas être clôturé (solde non nul ou consentement manquant).'],
            ]);
        }

        $account->status = Account::STATUS_CLOSED;
        $account->save();

        return $account->load('owners');
    }

    /**
     * Get all users (admin only)
     */
    public function getAllUsers(array $filters = []): array
    {
        $query = User::query();

        if (isset($filters['search'])) {
            $query->where('first_name', 'like', '%' . $filters['search'] . '%')
                ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                ->orWhere('email', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['is_admin'])) {
            $query->where('is_admin', $filters['is_admin']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage)->toArray();
    }
}