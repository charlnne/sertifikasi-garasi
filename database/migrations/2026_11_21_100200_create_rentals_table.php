<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('garage_id')->constrained()->restrictOnDelete();
            $table->enum('rental_type', ['daily', 'monthly']);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('extension_days')->default(0);
            $table->decimal('extension_cost', 12, 2)->default(0);
            $table->string('payment_proof');
            $table->enum('payment_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('extension_payment_proof')->nullable();
            $table->timestamps();

            $table->index(['garage_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
