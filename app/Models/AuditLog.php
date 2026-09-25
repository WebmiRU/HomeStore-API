<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Журнал действий (append-only).
 *
 * Каждая запись ссылается ровно на одну сущность-цель (одна из
 * item_id/store_id/warehouse_id/label_preset_id/label_list_id/access_grant_id/
 * target_user_id) — это гарантирует CHECK-ограничение audit_log_single_target.
 */
class AuditLog extends Model
{
    protected $table = 'audit_log';

    public const UPDATED_AT = null;
    public const CREATED_AT = 'created_at';

    protected $fillable = [
        'actor_id',
        'owner_id',
        'item_id',
        'store_id',
        'warehouse_id',
        'label_preset_id',
        'label_list_id',
        'access_grant_id',
        'target_user_id',
        'action',
        'payload',
        'client_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'payload' => 'array',
        ];
    }

    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'actor_id');
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'owner_id');
    }
}