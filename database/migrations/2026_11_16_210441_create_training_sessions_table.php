<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A time slot on the trainer's calendar. Personal sessions hold one booking,
     * group sessions hold up to `capacity`. Times are stored in UTC.
     */
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->uuid('recurrence_id')->nullable()->index();
            $table->timestamps();

            $table->index(['trainer_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
