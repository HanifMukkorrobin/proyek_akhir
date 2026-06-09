<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitasiRencana extends Model
{
    use SoftDeletes;

    protected $table = 'visitasi_rencana';

    protected $primaryKey = 'visitasi_rencana_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diubah_pada';

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'visitasi_rencana_id',
        'dosen_id',
        'dibuat_oleh_user_id',
        'nama_rencana',
        'deskripsi',
        'titik_awal_latitude',
        'titik_awal_longitude',
        'titik_awal_label',
        'jenis_kendaraan',
        'lewat_tol',
        'perkiraan_total_jarak_km',
        'perkiraan_total_menit',
        'status',
    ];

    protected $casts = [
        'lewat_tol' => 'boolean',
        'titik_awal_latitude' => 'float',
        'titik_awal_longitude' => 'float',
        'perkiraan_total_jarak_km' => 'float',
        'perkiraan_total_menit' => 'integer',
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosen_id', 'user_id');
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(VisitasiPeserta::class, 'visitasi_rencana_id', 'visitasi_rencana_id')
            ->whereNull('dihapus_pada')
            ->orderBy('urutan')
            ->orderBy('prioritas');
    }

    public function rute(): HasMany
    {
        return $this->hasMany(VisitasiRute::class, 'visitasi_rencana_id', 'visitasi_rencana_id')
            ->orderByDesc('dibuat_pada');
    }

    public function ruteTermakhir(): HasMany
    {
        return $this->hasMany(VisitasiRute::class, 'visitasi_rencana_id', 'visitasi_rencana_id')
            ->where('status', 'success')
            ->orderByDesc('dibuat_pada')
            ->limit(1);
    }
}
