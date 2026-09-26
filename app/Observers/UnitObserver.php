<?php

namespace App\Observers;

use App\Enums\AuditAction;

class UnitObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::UnitCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::UnitUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::UnitDeleted;
    }
}
