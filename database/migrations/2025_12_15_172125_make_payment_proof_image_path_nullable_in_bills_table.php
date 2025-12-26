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
            // Make payment_proof_image_path nullable to support AI Helper (no image upload required)
            $table->string('payment_proof_image_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Revert to NOT nullable
            $table->string('payment_proof_image_path')->nullable(false)->change();
        });
    }
};
