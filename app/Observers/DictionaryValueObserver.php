<?php

namespace App\Observers;

use App\Enums\AuditAction;

class DictionaryValueObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::DictionaryValueCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::DictionaryValueUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::DictionaryValueDeleted;
    }
}
