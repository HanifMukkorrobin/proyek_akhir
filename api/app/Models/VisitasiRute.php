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

    public const UPDATED_AT = 'diubah_pada';

    protected $fillable = [
        'visitasi_rute_id',
        'visitasi_rencana_id',
        'provider',
        'metode_kalkulasi',
        'service',
        'profile',
        'osrm_profile',
        'distance_meters',
        'total_jarak_km',
        'duration_seconds',
        'total_estimasi_menit',
        'weight',
        'geometry',
        'legs',
        'waypoints',
        'osrm_response',
        'hasil_osrm_raw',
        'parameter_input',
        'status',
        'error_message',
    ];

    protected $casts = [
        'distance_meters' => 'float',
        'duration_seconds' => 'float',
        'weight' => 'float',
        'geometry' => 'array',
        'legs' => 'array',
        'waypoints' => 'array',
        'osrm_response' => 'array',
        'hasil_osrm_raw' => 'array',
        'parameter_input' => 'array',
    ];

    public function rencana(): BelongsTo
    {
        return $this->belongsTo(VisitasiRencana::class, 'visitasi_rencana_id', 'visitasi_rencana_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(VisitasiRuteDetail::class, 'visitasi_rute_id', 'visitasi_rute_id');
    }
}
