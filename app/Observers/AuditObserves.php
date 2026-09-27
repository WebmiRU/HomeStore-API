<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Обзерверы фиксируют CRUD-события в журнале.
 * Базовый класс не регистрируется сам — используются конкретные наследники.
 */
abstract class AuditObserves
{
    protected AuditLogService $logs;

    public function __construct()
    {
        $this->logs = app(AuditLogService::class);
    }

    abstract protected function actionForCreated(): AuditAction;

    abstract protected function actionForUpdated(): AuditAction;

    abstract protected function actionForDeleted(): AuditAction;

    public function created(Model $model): void
    {
        $this->logs->recordForModel(
            $this->actionForCreated(),
            $model,
            $this->logs->payloadForCreated($model),
        );
    }

    public function updated(Model $model): void
    {
        $payload = $this->logs->payloadForUpdated($model);

        if (empty($payload['changes'])) {
            return;
        }

        $this->logs->recordForModel($this->actionForUpdated(), $model, $payload);
    }

    public function deleting(Model $model): void
    {
        $this->logs->recordForModel(
            $this->actionForDeleted(),
            $model,
            $this->logs->payloadForDeleted($model),
        );
    }

    /**
     * Восстановление из корзины — тоже событие журнала.
     *
     * Без него по истории нельзя отличить «вернули удалённое» от «завели
     * заново то же самое»: обе истории выглядели бы одинаково, удаление
     * плюс создание. Событие у сущностей, которые удаляются физически,
     * отсутствует — и тогда запись просто не пишется.
     *
     * Восстановление не трогает updated_at (строка не менялась, только
     * снят deleted_at), поэтому снапшот берём такой же, как при создании:
     * иначе в журнале было бы пусто.
     */
    public function restored(Model $model): void
    {
        $action = $this->logs->restoredActionFor($model);

        if ($action === null) {
            return;
        }

        $this->logs->recordForModel(
            $action,
            $model,
            $this->logs->payloadForCreated($model),
        );
    }
}