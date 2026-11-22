<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garages', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['bicycle', 'car', 'electric']);
            $table->unsignedSmallInteger('number');
            $table->decimal('daily_price', 12, 2);
            $table->decimal('kwh_price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['type', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
