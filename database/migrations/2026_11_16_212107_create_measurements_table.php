<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Body measurements of a client on a given day. Weight in kg, girths in cm.
     */
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('body_fat', 4, 1)->nullable();
            $table->decimal('waist', 5, 1)->nullable();
            $table->decimal('chest', 5, 1)->nullable();
            $table->decimal('hips', 5, 1)->nullable();
            $table->decimal('arm', 5, 1)->nullable();
            $table->decimal('thigh', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'measured_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
