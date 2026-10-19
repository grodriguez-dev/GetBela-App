<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de liquidaciones de comisiones ("pagos") por período.
     *
     * Cada fila es una foto de un cierre: qué rango se liquidó, cuánto se
     * facturó por servicios, cuánto se pagó en comisiones y cuánto quedó para
     * el negocio. El detalle por trabajador vive en `settlement_items`.
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // 'day' | 'week' | 'month'
            $table->enum('period_type', ['day', 'week', 'month']);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('appointments_count')->default(0);
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->decimal('total_commission', 12, 2)->default(0);
            $table->decimal('business_net', 12, 2)->default(0);
            $table->text('notes')->nullable();
            // Usuario que registró la liquidación.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
