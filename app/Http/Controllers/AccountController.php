<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    protected $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function index()
    {
        $user = Auth::user();
        $accounts = $this->accountService->getUserAccounts($user);
        
        return response()->json([
            'accounts' => $accounts
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'type' => 'required|in:COURANT,EPARGNE,MINEUR',
            'guardian_id' => 'required_if:type,MINEUR|exists:users,id',
            'interest_rate' => 'nullable|numeric',
            'overdraft_limit' => 'nullable|numeric',
        ]);
        
        $account = $this->accountService->createAccount($user, $validated);
        
        return response()->json([
            'message' => 'Compte créé avec succès',
            'account' => $account
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();
        $account = $this->accountService->getAccountDetails($user, $id);
        
        return response()->json([
            'account' => $account
        ]);
    }

    public function addCoOwner(Request $request, $id)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        
        $account = $this->accountService->addCoOwner($user, $id, $validated['user_id']);
        
        return response()->json([
            'message' => 'Co-titulaire ajouté avec succès',
            'account' => $account
        ]);
    }

    public function removeCoOwner($id, $userId)
    {
        $user = Auth::user();
        $account = $this->accountService->removeCoOwner($user, $id, $userId);
        
        return response()->json([
            'message' => 'Co-titulaire retiré avec succès',
            'account' => $account
        ]);
    }

    public function assignGuardian(Request $request, $id)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'guardian_id' => 'required|exists:users,id'
        ]);
        
        $account = $this->accountService->assignGuardian($user, $id, $validated['guardian_id']);
        
        return response()->json([
            'message' => 'Tuteur assigné avec succès',
            'account' => $account
        ]);
    }

    public function convert($id)
    {
        $user = Auth::user();
        $account = $this->accountService->convertMinorAccount($user, $id);
        
        return response()->json([
            'message' => 'Compte converti avec succès',
            'account' => $account
        ]);
    }

    public function requestClosure($id)
    {
        $user = Auth::user();
        $result = $this->accountService->requestClosure($user, $id);
        
        return response()->json($result);
    }
}