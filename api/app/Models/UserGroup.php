<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserGroup extends Model
{
    use SoftDeletes;

    protected $table = 'usergroups';

    protected $primaryKey = 'usergroup_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diubah_pada';

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'usergroup_id',
        'kode',
        'nama',
        'deskripsi',
        'status_aktif',
        'dibuat_oleh_user_id',
        'diubah_oleh_user_id',
        'dihapus_oleh_user_id',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'usergroup_id', 'usergroup_id');
    }
}
