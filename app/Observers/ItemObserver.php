<?php

namespace App\Observers;

use App\Enums\AuditAction;

class ItemObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::ItemCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::ItemUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::ItemDeleted;
    }
}