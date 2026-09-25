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
}