<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $primaryKey = 'log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'log_id',
        'user_id',
        'username',
        'nama_user',
        'usergroup_kode',
        'modul',
        'aksi',
        'target_tipe',
        'target_id',
        'status',
        'status_code',
        'method',
        'path',
        'deskripsi',
        'response_message',
        'ip_address',
        'user_agent',
        'request_payload',
        'metadata',
        'duration_ms',
        'dibuat_pada',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'metadata' => 'array',
        'dibuat_pada' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }
}
