<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booking is charged (one session) when it is completed, a no-show or
     * cancelled late. A charge without a package is a session the client owes.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('client_package_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->dateTime('charged_at')->nullable()->after('cancelled_by');
            $table->boolean('charge_waived')->default(false)->after('charged_at');

            $table->index(['client_id', 'charged_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'charged_at']);
            $table->dropConstrainedForeignId('client_package_id');
            $table->dropColumn(['charged_at', 'charge_waived']);
        });
    }
};
