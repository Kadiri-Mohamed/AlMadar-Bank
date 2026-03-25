<?php

namespace App\Jobs;

use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateMonthlyInterestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(TransactionService $transactionService): void
    {
        try {
            Log::info('Début du calcul des intérêts mensuels');

            $transactionService->applyMonthlyInterests();

            Log::info('Calcul des intérêts mensuels terminé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors du calcul des intérêts mensuels: ' . $e->getMessage());
            throw $e;
        }
    }
}
