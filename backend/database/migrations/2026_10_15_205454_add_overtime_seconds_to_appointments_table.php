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
        Schema::table('appointments', function (Blueprint $table) {
            // Segundos de más que duró la cita respecto a su hora de fin programada.
            // Null para citas antiguas/sin registro: el front no muestra nada.
            $table->unsignedInteger('overtime_seconds')->nullable()->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('overtime_seconds');
        });
    }
};
