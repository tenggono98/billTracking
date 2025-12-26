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
        Schema::table('bills', function (Blueprint $table) {
            // Make bill_image_path nullable (bill proof is optional)
            $table->string('bill_image_path')->nullable()->change();
            // Make payment_proof_image_path NOT nullable (payment proof is required)
            $table->string('payment_proof_image_path')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Revert changes
            $table->string('bill_image_path')->nullable(false)->change();
            $table->string('payment_proof_image_path')->nullable()->change();
        });
    }
};
