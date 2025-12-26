<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('bill_image_path')->nullable(); // Bill proof is optional
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_proof_image_path'); // Payment proof is required
            $table->decimal('payment_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'partial'])->default('pending');
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
