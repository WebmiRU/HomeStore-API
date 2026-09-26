<?php

namespace App\Observers;

use App\Enums\AuditAction;

class DictionaryObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::DictionaryCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::DictionaryUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::DictionaryDeleted;
    }
}
