<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle por trabajador de cada liquidación.
     *
     * El nombre y el porcentaje de comisión se guardan como "foto" del momento:
     * si luego se edita o elimina el empleado, el historial no cambia.
     */
    public function up(): void
    {
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            // Puede ser null (citas sin empleado asignado -> todo para el negocio).
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->string('employee_name');
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->unsignedInteger('appointments_count')->default(0);
            $table->decimal('gross_generated', 12, 2)->default(0);
            $table->decimal('commission_payout', 12, 2)->default(0);
            $table->decimal('business_net', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
    }
};
