<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wilayah extends Model
{
    use SoftDeletes;

    protected $table = 'wilayah';

    protected $primaryKey = 'wilayah_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diubah_pada';

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'wilayah_id',
        'kode_dukcapil',
        'nama',
        'longitude',
        'latitude',
        'dibuat_oleh_user_id',
        'diubah_oleh_user_id',
        'dihapus_oleh_user_id',
    ];
}