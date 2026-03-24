<?php

namespace App\Http\Controllers;

use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index($accountId, Request $request)
    {
        $user = Auth::user();
        
        $filters = $request->only(['type', 'start_date', 'end_date', 'min_amount', 'max_amount', 'per_page']);
        
        $transactions = $this->transactionService->getAccountTransactions($user, $accountId, $filters);
        
        return response()->json([
            'transactions' => $transactions
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();
        $transaction = $this->transactionService->getTransactionDetails($user, $id);
        
        return response()->json([
            'transaction' => $transaction
        ]);
    }
}