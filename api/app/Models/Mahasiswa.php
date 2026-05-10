<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mahasiswa extends Model
{
    use SoftDeletes;

    protected $table = 'mahasiswa';

    protected $primaryKey = 'mahasiswa_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diubah_pada';

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'mahasiswa_id',
        'nama',
        'latitude',
        'longitude',
        'wilayah_id',
        'alamat',
        'is_valid_address',
        'geocoding_status',
        'dibuat_oleh_user_id',
        'diubah_oleh_user_id',
        'dihapus_oleh_user_id',
    ];

    protected $casts = [
        'is_valid_address' => 'boolean',
    ];

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'wilayah_id', 'wilayah_id')->withTrashed();
    }
}