<?php

namespace App\Observers;

use App\Enums\AuditAction;

class AccessGrantObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::AccessGrantCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::AccessGrantUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::AccessGrantDeleted;
    }
}