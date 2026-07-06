<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->string('type'); // Dùng string để linh hoạt thay vì enum MySQL cũ, nhưng sẽ được quy định ở code (deposit, transfer_out, transfer_in, refund)
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->uuid('reference_id')->index(); // Dùng uuid
            $table->foreignId('counterpart_wallet_id')->nullable()->constrained('wallets')->onDelete('set null');
            $table->string('status')->default('completed'); // Dùng string (pending, completed, failed)
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['wallet_id', 'type']);
            $table->index(['wallet_id', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
