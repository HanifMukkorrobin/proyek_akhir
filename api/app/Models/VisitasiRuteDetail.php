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

    public $timestamps = false;

    protected $fillable = [
        'visitasi_rute_detail_id',
        'visitasi_rute_id',
        'visitasi_rencana_id',
        'visitasi_peserta_id',
        'tipe_titik',
        'urutan_kunjungan',
        'latitude',
        'longitude',
        'label',
        'estimasi_ke_sini_menit',
        'jarak_dari_sebelumnya_km',
        'estimasi_kumulatif_menit',
        'geometri_polyline',
    ];

    protected $casts = [
        'urutan_kunjungan' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'estimasi_ke_sini_menit' => 'integer',
        'jarak_dari_sebelumnya_km' => 'float',
        'estimasi_kumulatif_menit' => 'integer',
    ];

    public function rute(): BelongsTo
    {
        return $this->belongsTo(VisitasiRute::class, 'visitasi_rute_id', 'visitasi_rute_id');
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(VisitasiPeserta::class, 'visitasi_peserta_id', 'visitasi_peserta_id');
    }
}
