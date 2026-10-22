<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El super admin es global y no pertenece a ningún negocio.
     * Se desvincula para que no herede datos/contexto de un negocio.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->update(['business_id' => null]);
    }

    public function down(): void
    {
        // No se revierte: no hay forma segura de saber a qué negocio pertenecía.
    }
};
