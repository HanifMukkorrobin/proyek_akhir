<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "CREATE INDEX IF NOT EXISTS mahasiswa_wilayah_id_active_idx
            ON mahasiswa (wilayah_id)
            WHERE dihapus_pada IS NULL AND wilayah_id IS NOT NULL"
        );

        DB::statement(
            "CREATE INDEX IF NOT EXISTS mahasiswa_wilayah_id_active_pattern_idx
            ON mahasiswa (wilayah_id varchar_pattern_ops)
            WHERE dihapus_pada IS NULL AND wilayah_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS mahasiswa_wilayah_id_active_pattern_idx');
        DB::statement('DROP INDEX IF EXISTS mahasiswa_wilayah_id_active_idx');
    }
};
