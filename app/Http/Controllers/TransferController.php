<?php

namespace App\Http\Controllers;

use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransferController extends Controller
{
    protected $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'source_account_id' => 'required|exists:accounts,id',
            'destination_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255'
        ]);
        
        $transfer = $this->transferService->initiateTransfer($user, $validated);
        
        return response()->json([
            'message' => 'Virement initié avec succès',
            'transfer' => $transfer
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::user();
        $transfer = $this->transferService->getTransferDetails($user, $id);
        
        return response()->json([
            'transfer' => $transfer
        ]);
    }
}