<?php

namespace App\Observers;

use App\Enums\AuditAction;

class PropertyObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::PropertyCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::PropertyUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::PropertyDeleted;
    }
}
