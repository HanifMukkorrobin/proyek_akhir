<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitasiRute extends Model
{
    protected $table = 'visitasi_rute';

    protected $primaryKey = 'visitasi_rute_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = null;

    protected $fillable = [
        'visitasi_rute_id',
        'visitasi_rencana_id',
        'metode_kalkulasi',
        'osrm_profile',
        'total_jarak_km',
        'total_estimasi_menit',
        'parameter_input',
        'hasil_osrm_raw',
        'status',
        'error_message',
    ];

    protected $casts = [
        'total_jarak_km' => 'float',
        'total_estimasi_menit' => 'integer',
        'parameter_input' => 'array',
        'hasil_osrm_raw' => 'array',
    ];

    public function rencana(): BelongsTo
    {
        return $this->belongsTo(VisitasiRencana::class, 'visitasi_rencana_id', 'visitasi_rencana_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(VisitasiRuteDetail::class, 'visitasi_rute_id', 'visitasi_rute_id')
            ->orderBy('urutan_kunjungan');
    }
}
