<?php

namespace App\Observers;

use App\Enums\AuditAction;

class LabelPresetObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::LabelPresetCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::LabelPresetUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::LabelPresetDeleted;
    }
}