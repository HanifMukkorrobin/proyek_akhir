<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diubah_pada';

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'user_id',
        'nama',
        'username',
        'email',
        'password',
        'usergroup_id',
        'mahasiswa_id',
        'status_aktif',
        'last_login_pada',
        'dibuat_oleh_user_id',
        'diubah_oleh_user_id',
        'dihapus_oleh_user_id',
    ];

    protected $hidden = [
        'password',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'mahasiswa_id')->withTrashed();
    }

    public function usergroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'usergroup_id', 'usergroup_id')->withTrashed();
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(AuthToken::class, 'user_id', 'user_id');
    }
}
