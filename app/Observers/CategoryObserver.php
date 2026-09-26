<?php

namespace App\Observers;

use App\Enums\AuditAction;

class CategoryObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::CategoryCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::CategoryUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::CategoryDeleted;
    }
}
