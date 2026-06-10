<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeRencana();
        $this->normalizePeserta();
        $this->normalizeRute();
        $this->normalizeRuteDetail();
    }

    public function down(): void
    {
        // No-op: this migration only adds compatibility columns for existing visitasi schemas.
    }

    private function normalizeRencana(): void
    {
        if (!Schema::hasTable('visitasi_rencana')) {
            return;
        }

        Schema::table('visitasi_rencana', function (Blueprint $table) {
            if (!Schema::hasColumn('visitasi_rencana', 'nama')) {
                $table->string('nama', 255)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'dosen_user_id')) {
                $table->uuid('dosen_user_id')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'titik_awal_nama')) {
                $table->string('titik_awal_nama', 255)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'titik_akhir_nama')) {
                $table->string('titik_akhir_nama', 255)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'titik_akhir_latitude')) {
                $table->decimal('titik_akhir_latitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'titik_akhir_longitude')) {
                $table->decimal('titik_akhir_longitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'profile')) {
                $table->string('profile', 50)->default('driving');
            }
            if (!Schema::hasColumn('visitasi_rencana', 'optimize_order')) {
                $table->boolean('optimize_order')->default(true);
            }
            if (!Schema::hasColumn('visitasi_rencana', 'catatan')) {
                $table->text('catatan')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'diubah_oleh_user_id')) {
                $table->string('diubah_oleh_user_id', 36)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rencana', 'dihapus_oleh_user_id')) {
                $table->string('dihapus_oleh_user_id', 36)->nullable();
            }
        });

        if (Schema::hasColumn('visitasi_rencana', 'nama_rencana')) {
            DB::statement("UPDATE visitasi_rencana SET nama = COALESCE(nama, nama_rencana)");
        }
        if (Schema::hasColumn('visitasi_rencana', 'deskripsi')) {
            DB::statement("UPDATE visitasi_rencana SET catatan = COALESCE(catatan, deskripsi)");
        }
        if (Schema::hasColumn('visitasi_rencana', 'titik_awal_label')) {
            DB::statement("UPDATE visitasi_rencana SET titik_awal_nama = COALESCE(titik_awal_nama, titik_awal_label)");
        }
        if (Schema::hasColumn('visitasi_rencana', 'jenis_kendaraan')) {
            DB::statement("UPDATE visitasi_rencana SET kendaraan = COALESCE(kendaraan, jenis_kendaraan)");
        }
        if (Schema::hasColumn('visitasi_rencana', 'dosen_id')) {
            DB::statement("UPDATE visitasi_rencana SET dosen_user_id = COALESCE(dosen_user_id, dosen_id)");
        }

        DB::statement("UPDATE visitasi_rencana SET nama = COALESCE(nama, 'Simulasi Visitasi'), titik_awal_nama = COALESCE(titik_awal_nama, 'Titik Keberangkatan'), profile = COALESCE(profile, 'driving'), optimize_order = COALESCE(optimize_order, true), kendaraan = COALESCE(kendaraan, 'mobil')");
    }

    private function normalizePeserta(): void
    {
        if (!Schema::hasTable('visitasi_peserta')) {
            return;
        }

        Schema::table('visitasi_peserta', function (Blueprint $table) {
            if (!Schema::hasColumn('visitasi_peserta', 'urutan_input')) {
                $table->unsignedInteger('urutan_input')->nullable();
            }
            if (!Schema::hasColumn('visitasi_peserta', 'urutan_rute')) {
                $table->unsignedInteger('urutan_rute')->nullable();
            }
            if (!Schema::hasColumn('visitasi_peserta', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('visitasi_peserta', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('visitasi_peserta', 'status_lokasi')) {
                $table->string('status_lokasi', 100)->nullable();
            }
            if (!Schema::hasColumn('visitasi_peserta', 'diubah_pada')) {
                $table->timestamp('diubah_pada')->nullable();
            }
        });

        if (Schema::hasColumn('visitasi_peserta', 'urutan')) {
            DB::statement("UPDATE visitasi_peserta SET urutan_input = COALESCE(urutan_input, urutan), urutan_rute = COALESCE(urutan_rute, urutan)");
        }

        DB::statement("UPDATE visitasi_peserta SET urutan_input = COALESCE(urutan_input, 0)");
    }

    private function normalizeRute(): void
    {
        if (!Schema::hasTable('visitasi_rute')) {
            return;
        }

        Schema::table('visitasi_rute', function (Blueprint $table) {
            if (!Schema::hasColumn('visitasi_rute', 'provider')) {
                $table->string('provider', 50)->default('osrm');
            }
            if (!Schema::hasColumn('visitasi_rute', 'service')) {
                $table->string('service', 30)->default('trip');
            }
            if (!Schema::hasColumn('visitasi_rute', 'profile')) {
                $table->string('profile', 50)->default('driving');
            }
            if (!Schema::hasColumn('visitasi_rute', 'distance_meters')) {
                $table->decimal('distance_meters', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute', 'duration_seconds')) {
                $table->decimal('duration_seconds', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute', 'weight')) {
                $table->decimal('weight', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute', 'geometry')) {
                $table->json('geometry')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute', 'legs')) {
                $table->json('legs')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute', 'waypoints')) {
                $table->json('waypoints')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute', 'osrm_response')) {
                $table->json('osrm_response')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute', 'diubah_pada')) {
                $table->timestamp('diubah_pada')->nullable();
            }
        });

        if (Schema::hasColumn('visitasi_rute', 'metode_kalkulasi')) {
            DB::statement("UPDATE visitasi_rute SET provider = COALESCE(provider, 'osrm'), service = COALESCE(service, metode_kalkulasi)");
        }
        if (Schema::hasColumn('visitasi_rute', 'osrm_profile')) {
            DB::statement("UPDATE visitasi_rute SET profile = COALESCE(profile, osrm_profile)");
        }
        if (Schema::hasColumn('visitasi_rute', 'total_jarak_km')) {
            DB::statement("UPDATE visitasi_rute SET distance_meters = COALESCE(distance_meters, total_jarak_km * 1000)");
        }
        if (Schema::hasColumn('visitasi_rute', 'total_estimasi_menit')) {
            DB::statement("UPDATE visitasi_rute SET duration_seconds = COALESCE(duration_seconds, total_estimasi_menit * 60)");
        }
        if (Schema::hasColumn('visitasi_rute', 'hasil_osrm_raw')) {
            DB::statement("UPDATE visitasi_rute SET osrm_response = COALESCE(osrm_response, hasil_osrm_raw)");
        }

        DB::statement("UPDATE visitasi_rute SET provider = COALESCE(provider, 'osrm'), service = COALESCE(service, 'trip'), profile = COALESCE(profile, 'driving')");
    }

    private function normalizeRuteDetail(): void
    {
        if (!Schema::hasTable('visitasi_rute_detail')) {
            return;
        }

        Schema::table('visitasi_rute_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('visitasi_rute_detail', 'mahasiswa_id')) {
                $table->string('mahasiswa_id')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'urutan')) {
                $table->unsignedInteger('urutan')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'tipe')) {
                $table->string('tipe', 30)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'nama')) {
                $table->string('nama', 255)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'leg_index')) {
                $table->unsignedInteger('leg_index')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'distance_meters')) {
                $table->decimal('distance_meters', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'duration_seconds')) {
                $table->decimal('duration_seconds', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'steps')) {
                $table->json('steps')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'dibuat_pada')) {
                $table->timestamp('dibuat_pada')->nullable();
            }
            if (!Schema::hasColumn('visitasi_rute_detail', 'diubah_pada')) {
                $table->timestamp('diubah_pada')->nullable();
            }
        });

        if (Schema::hasColumn('visitasi_rute_detail', 'urutan_kunjungan')) {
            DB::statement("UPDATE visitasi_rute_detail SET urutan = COALESCE(urutan, urutan_kunjungan)");
        }
        if (Schema::hasColumn('visitasi_rute_detail', 'tipe_titik')) {
            DB::statement("UPDATE visitasi_rute_detail SET tipe = COALESCE(tipe, tipe_titik)");
        }
        if (Schema::hasColumn('visitasi_rute_detail', 'label')) {
            DB::statement("UPDATE visitasi_rute_detail SET nama = COALESCE(nama, label)");
        }
        if (Schema::hasColumn('visitasi_rute_detail', 'jarak_dari_sebelumnya_km')) {
            DB::statement("UPDATE visitasi_rute_detail SET distance_meters = COALESCE(distance_meters, jarak_dari_sebelumnya_km * 1000)");
        }
        if (Schema::hasColumn('visitasi_rute_detail', 'estimasi_ke_sini_menit')) {
            DB::statement("UPDATE visitasi_rute_detail SET duration_seconds = COALESCE(duration_seconds, estimasi_ke_sini_menit * 60)");
        }

        DB::statement("UPDATE visitasi_rute_detail SET urutan = COALESCE(urutan, 0), tipe = COALESCE(tipe, 'mahasiswa'), nama = COALESCE(nama, 'Titik Rute')");
    }
};
