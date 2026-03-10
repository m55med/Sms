<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->text('raw_message');
            $table->string('provider')->default('unknown');
            $table->string('type')->default('unknown'); // received, sent, unknown
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->json('suspicion_reasons')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
