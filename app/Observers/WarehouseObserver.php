<?php

namespace App\Observers;

use App\Enums\AuditAction;

class WarehouseObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::WarehouseCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::WarehouseUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::WarehouseDeleted;
    }
}