<?php

namespace App\Observers;

use App\Enums\AuditAction;

class StoreObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::StoreCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::StoreUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::StoreDeleted;
    }
}