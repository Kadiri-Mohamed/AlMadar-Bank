<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->enum('status', ['ACTIVE', 'BLOCKED', 'CLOSED'])->default('ACTIVE');
            $table->enum('type', ['COURANT', 'EPARGNE', 'MINEUR']);
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('overdraft_limit', 15, 2)->nullable();
            $table->integer('monthly_withdrawal_count')->default(0);
            $table->text('blocked_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
