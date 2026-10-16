<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amplía el enum de estado con 'no_show' para marcar las citas confirmadas
     * que llegaron a su hora de finalización sin iniciarse.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled','in_progress','no_show') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Evitamos filas con un valor que el enum reducido no soporta.
        DB::table('appointments')->where('status', 'no_show')->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled','in_progress') NOT NULL DEFAULT 'pending'");
    }
};
