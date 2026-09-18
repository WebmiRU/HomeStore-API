<?php

namespace App\Observers;

use App\Enums\AuditAction;

class LabelListObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::LabelListCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::LabelListUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::LabelListDeleted;
    }
}