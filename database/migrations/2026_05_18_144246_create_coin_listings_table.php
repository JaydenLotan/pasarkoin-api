<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_listings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->decimal('price', 15, 2);

            $table->string('coin_origin')->nullable(); // Example: Indonesia, Dutch East Indies
            $table->string('coin_year')->nullable();   // Some old coins may have uncertain year
            $table->string('material')->nullable();    // Example: silver, copper, nickel
            $table->string('condition')->nullable();   // Example: fine, very fine, mint

            $table->string('status')->default('pending');
            // pending, approved, rejected, sold, inactive

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_listings');
    }
};