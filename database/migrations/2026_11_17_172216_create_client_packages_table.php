<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A package sold to a client. Name, sessions and price are copied from the
     * template so later template changes do not alter what the client bought.
     */
    public function up(): void
    {
        Schema::create('client_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sessions_total');
            $table->date('starts_on');
            $table->date('expires_on')->nullable();
            $table->decimal('price', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'expires_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_packages');
    }
};
