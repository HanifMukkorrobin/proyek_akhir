<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitasiPeserta extends Model
{
    protected $table = 'visitasi_peserta';

    protected $primaryKey = 'visitasi_peserta_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diubah_pada';

    protected $fillable = [
        'visitasi_peserta_id',
        'visitasi_rencana_id',
        'mahasiswa_id',
        'urutan_input',
        'urutan',
        'urutan_rute',
        'prioritas',
        'latitude',
        'longitude',
        'status_lokasi',
        'catatan',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function rencana(): BelongsTo
    {
        return $this->belongsTo(VisitasiRencana::class, 'visitasi_rencana_id', 'visitasi_rencana_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'mahasiswa_id')->withTrashed();
    }
}
