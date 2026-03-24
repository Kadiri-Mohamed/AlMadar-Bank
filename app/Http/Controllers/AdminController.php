<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
        $this->middleware('admin'); // Tu devras créer ce middleware
    }

    public function accounts(Request $request)
    {
        $filters = $request->only(['type', 'status', 'search', 'per_page']);
        $accounts = $this->adminService->getAllAccounts($filters);
        
        return response()->json([
            'accounts' => $accounts
        ]);
    }

    public function blockAccount($id, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string'
        ]);
        
        $account = $this->adminService->blockAccount($id, $validated['reason']);
        
        return response()->json([
            'message' => 'Compte bloqué avec succès',
            'account' => $account
        ]);
    }

    public function unblockAccount($id)
    {
        $account = $this->adminService->unblockAccount($id);
        
        return response()->json([
            'message' => 'Compte débloqué avec succès',
            'account' => $account
        ]);
    }

    public function closeAccount($id)
    {
        $account = $this->adminService->closeAccount($id);
        
        return response()->json([
            'message' => 'Compte clôturé avec succès',
            'account' => $account
        ]);
    }

    public function users(Request $request)
    {
        $filters = $request->only(['search', 'is_admin', 'per_page']);
        $users = $this->adminService->getAllUsers($filters);
        
        return response()->json([
            'users' => $users
        ]);
    }
}