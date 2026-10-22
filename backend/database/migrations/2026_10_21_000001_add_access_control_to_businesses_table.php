<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Control de acceso a la plataforma a nivel de negocio.
     *
     * - is_active: si el negocio puede usar la plataforma y mostrarse en el
     *   catálogo público.
     * - suspended_at: cuándo fue suspendido (auditoría).
     * - suspension_reason: motivo opcional de la suspensión.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
            $table->timestamp('suspended_at')->nullable()->after('is_active');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'suspended_at', 'suspension_reason']);
        });
    }
};
