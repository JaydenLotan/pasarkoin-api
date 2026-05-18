<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('coin_listing_id')
                ->constrained('coin_listings')
                ->cascadeOnDelete();

            $table->decimal('total_price', 15, 2);

            $table->string('status')->default('pending');
            // pending, confirmed, shipped, completed, cancelled

            $table->string('buyer_name');
            $table->string('buyer_phone')->nullable();
            $table->text('shipping_address');

            $table->text('seller_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};