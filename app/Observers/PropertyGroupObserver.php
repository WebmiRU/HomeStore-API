<?php

namespace App\Observers;

use App\Enums\AuditAction;

class PropertyGroupObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::PropertyGroupCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::PropertyGroupUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::PropertyGroupDeleted;
    }
}
