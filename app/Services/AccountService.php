<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CoOwnership;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * Create a new account
     */
    public function createAccount(User $user, array $data): Account
    {
        return DB::transaction(function () use ($user, $data) {
            // Validate account type rules
            $this->validateAccountCreation($user, $data);

            // Create account
            $account = Account::create([
                'type' => $data['type'],
                'interest_rate' => $data['interest_rate'] ?? ($data['type'] === Account::TYPE_EPARGNE ? 3.5 : null),
                'overdraft_limit' => $data['overdraft_limit'] ?? ($data['type'] === Account::TYPE_COURANT ? 1000 : null),
                'status' => Account::STATUS_ACTIVE,
            ]);

            // Add user as owner
            $account->owners()->attach($user->id, ['accepted_closure' => false]);

            // Handle minor account
            if ($data['type'] === Account::TYPE_MINEUR) {
                if (!isset($data['guardian_id'])) {
                    throw ValidationException::withMessages([
                        'guardian_id' => ['Un compte mineur necessite un tuteur.'],
                    ]);
                }

                $guardian = User::findOrFail($data['guardian_id']);
                
                if (!$guardian->isAdult()) {
                    throw ValidationException::withMessages([
                        'guardian_id' => ['Le tuteur doit etre majeur.'],
                    ]);
                }

                Guardian::create([
                    'minor_id' => $user->id,
                    'guardian_id' => $guardian->id,
                ]);

                // Add guardian as co-owner
                $account->owners()->attach($guardian->id, ['accepted_closure' => false]);
            }

            return $account->load('owners');
        });
    }

    /**
     * Validate account creation rules
     */
    private function validateAccountCreation(User $user, array $data): void
    {
        // Check if user is minor and trying to create non-minor account
        if ($user->isMinor() && $data['type'] !== Account::TYPE_MINEUR) {
            throw ValidationException::withMessages([
                'type' => ['Un mineur ne peut ouvrir qu\'un compte mineur.'],
            ]);
        }

        // Check if adult trying to create minor account for themselves
        if ($user->isAdult() && $data['type'] === Account::TYPE_MINEUR) {
            throw ValidationException::withMessages([
                'type' => ['Un adulte ne peut pas ouvrir un compte mineur pour lui-meme.'],
            ]);
        }
    }

    /**
     * Get user accounts
     */
    public function getUserAccounts(User $user): array
    {
        return $user->accounts()->with(['owners', 'transactions' => function($query) {
            $query->latest()->limit(10);
        }])->get()->toArray();
    }

    /**
     * Get account details
     */
    public function getAccountDetails(User $user, int $accountId): Account
    {
        $account = Account::with(['owners', 'transactions' => function($query) {
            $query->latest()->limit(50);
        }])->findOrFail($accountId);

        // Check if user has access to this account
        if (!$account->owners()->where('user_id', $user->id)->exists()) {
            throw new ModelNotFoundException('Compte non trouve.');
        }

        return $account;
    }

    /**
     * Add co-owner to account
     */
    public function addCoOwner(User $user, int $accountId, int $newOwnerId): Account
    {
        return DB::transaction(function () use ($user, $accountId, $newOwnerId) {
            $account = Account::findOrFail($accountId);

            // Check if user is owner
            if (!$account->owners()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'account' => ['Vous n\'etes pas titulaire de ce compte.'],
                ]);
            }

            // Check if account is active
            if (!$account->isActive()) {
                throw ValidationException::withMessages([
                    'account' => ['Ce compte n\'est pas actif.'],
                ]);
            }

            // Check if new owner is adult
            $newOwner = User::findOrFail($newOwnerId);
            if (!$newOwner->isAdult()) {
                throw ValidationException::withMessages([
                    'user' => ['Le nouveau titulaire doit etre majeur.'],
                ]);
            }

            // Check if already co-owner
            if ($account->owners()->where('user_id', $newOwnerId)->exists()) {
                throw ValidationException::withMessages([
                    'user' => ['Cet utilisateur est dejà titulaire du compte.'],
                ]);
            }

            // Add new co-owner
            $account->owners()->attach($newOwnerId, ['accepted_closure' => false]);

            return $account->load('owners');
        });
    }

    /**
     * Remove co-owner from account
     */
    public function removeCoOwner(User $user, int $accountId, int $ownerId): Account
    {
        return DB::transaction(function () use ($user, $accountId, $ownerId) {
            $account = Account::findOrFail($accountId);

            // Check if user is owner
            if (!$account->owners()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'account' => ['Vous n\'etes pas titulaire de ce compte.'],
                ]);
            }

            // Check if account is active
            if (!$account->isActive()) {
                throw ValidationException::withMessages([
                    'account' => ['Ce compte n\'est pas actif.'],
                ]);
            }

            // Can't remove last owner
            if ($account->owners()->count() <= 1) {
                throw ValidationException::withMessages([
                    'account' => ['Un compte doit avoir au moins un titulaire.'],
                ]);
            }

            // Remove co-owner
            $account->owners()->detach($ownerId);

            return $account->load('owners');
        });
    }

    /**
     * Assign guardian to minor account
     */
    public function assignGuardian(User $user, int $accountId, int $guardianId): Account
    {
        return DB::transaction(function () use ($user, $accountId, $guardianId) {
            $account = Account::findOrFail($accountId);

            // Check if account is minor type
            if (!$account->isMineur()) {
                throw ValidationException::withMessages([
                    'account' => ['Seul un compte mineur peut avoir un tuteur.'],
                ]);
            }

            // Check if user is the minor owner
            if (!$account->owners()->where('user_id', $user->id)->exists() || !$user->isMinor()) {
                throw ValidationException::withMessages([
                    'account' => ['Vous n\'etes pas le mineur titulaire de ce compte.'],
                ]);
            }

            $guardian = User::findOrFail($guardianId);

            // Check if guardian is adult
            if (!$guardian->isAdult()) {
                throw ValidationException::withMessages([
                    'guardian' => ['Le tuteur doit etre majeur.'],
                ]);
            }

            // Assign guardian
            Guardian::updateOrCreate(
                ['minor_id' => $user->id],
                ['guardian_id' => $guardian->id]
            );

            // Add guardian as co-owner if not already
            if (!$account->owners()->where('user_id', $guardianId)->exists()) {
                $account->owners()->attach($guardianId, ['accepted_closure' => false]);
            }

            return $account->load('owners');
        });
    }

    /**
     * Convert minor account to courant
     */
    public function convertMinorAccount(User $user, int $accountId): Account
    {
        return DB::transaction(function () use ($user, $accountId) {
            $account = Account::findOrFail($accountId);

            // Check if account is minor type
            if (!$account->isMineur()) {
                throw ValidationException::withMessages([
                    'account' => ['Ce compte n\'est pas un compte mineur.'],
                ]);
            }

            // Check if user is the owner
            if (!$account->owners()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'account' => ['Vous n\'etes pas titulaire de ce compte.'],
                ]);
            }

            // Check if user is now adult
            if ($user->isMinor()) {
                throw ValidationException::withMessages([
                    'user' => ['Vous devez etre majeur pour convertir ce compte.'],
                ]);
            }

            // Get guardian
            $guardian = $user->guardian;
            if (!$guardian) {
                throw ValidationException::withMessages([
                    'guardian' => ['Aucun tuteur trouve pour ce compte.'],
                ]);
            }

            // Check guardian consent
            $coOwnership = CoOwnership::where('account_id', $accountId)
                ->where('user_id', $guardian->guardian_id)
                ->first();

            if (!$coOwnership || !$coOwnership->accepted_closure) {
                throw ValidationException::withMessages([
                    'guardian' => ['Le tuteur doit accepter la conversion.'],
                ]);
            }

            // Convert account
            $account->update([
                'type' => Account::TYPE_COURANT,
                'overdraft_limit' => 1000,
                'interest_rate' => null,
            ]);

            return $account->fresh();
        });
    }

    /**
     * Request account closure
     */
    public function requestClosure(User $user, int $accountId): array
    {
        return DB::transaction(function () use ($user, $accountId) {
            $account = Account::findOrFail($accountId);

            // Check if user is owner
            if (!$account->owners()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'account' => ['Vous n\'etes pas titulaire de ce compte.'],
                ]);
            }

            // Check if balance is zero
            if ($account->balance != 0) {
                throw ValidationException::withMessages([
                    'account' => ['Le solde doit etre de 0 pour cloturer le compte.'],
                ]);
            }

            // Mark closure acceptance for this user
            $coOwnership = CoOwnership::where('account_id', $accountId)
                ->where('user_id', $user->id)
                ->first();

            if ($coOwnership) {
                $coOwnership->update(['accepted_closure' => true]);
            }

            // Check if all owners have accepted closure
            $allAccepted = $account->coOwnerships()
                ->where('accepted_closure', false)
                ->count() === 0;

            return [
                'account' => $account,
                'all_accepted' => $allAccepted,
                'message' => $allAccepted ? 'Tous les titulaires ont accepte la cloture.' : 'En attente d\'acceptation des autres titulaires.',
            ];
        });
    }
}