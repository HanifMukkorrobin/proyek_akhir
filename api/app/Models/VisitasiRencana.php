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
        'nama',
        'nama_rencana',
        'dosen_user_id',
        'dosen_id',
        'titik_awal_nama',
        'titik_awal_label',
        'titik_awal_latitude',
        'titik_awal_longitude',
        'titik_akhir_nama',
        'titik_akhir_latitude',
        'titik_akhir_longitude',
        'profile',
        'kendaraan',
        'jenis_kendaraan',
        'optimize_order',
        'status',
        'catatan',
        'deskripsi',
        'perkiraan_total_jarak_km',
        'perkiraan_total_menit',
        'dibuat_oleh_user_id',
        'diubah_oleh_user_id',
        'dihapus_oleh_user_id',
    ];

    protected $casts = [
        'optimize_order' => 'boolean',
        'titik_awal_latitude' => 'float',
        'titik_awal_longitude' => 'float',
        'titik_akhir_latitude' => 'float',
        'titik_akhir_longitude' => 'float',
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosen_user_id', 'user_id')->withTrashed();
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(VisitasiPeserta::class, 'visitasi_rencana_id', 'visitasi_rencana_id');
    }

    public function rute(): HasMany
    {
        return $this->hasMany(VisitasiRute::class, 'visitasi_rencana_id', 'visitasi_rencana_id');
    }
}
