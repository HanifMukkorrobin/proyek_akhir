<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitasiRuteDetail extends Model
{
    protected $table = 'visitasi_rute_detail';

    protected $primaryKey = 'visitasi_rute_detail_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diubah_pada';

    protected $fillable = [
        'visitasi_rute_detail_id',
        'visitasi_rute_id',
        'visitasi_rencana_id',
        'visitasi_peserta_id',
        'mahasiswa_id',
        'urutan',
        'urutan_kunjungan',
        'tipe',
        'tipe_titik',
        'nama',
        'label',
        'latitude',
        'longitude',
        'leg_index',
        'distance_meters',
        'jarak_dari_sebelumnya_km',
        'duration_seconds',
        'estimasi_ke_sini_menit',
        'estimasi_kumulatif_menit',
        'geometri_polyline',
        'steps',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_meters' => 'float',
        'duration_seconds' => 'float',
        'steps' => 'array',
    ];

    public function rute(): BelongsTo
    {
        return $this->belongsTo(VisitasiRute::class, 'visitasi_rute_id', 'visitasi_rute_id');
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(VisitasiPeserta::class, 'visitasi_peserta_id', 'visitasi_peserta_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'mahasiswa_id')->withTrashed();
    }
}
