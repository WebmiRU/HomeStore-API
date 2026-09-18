<?php

namespace App\Observers;

use App\Enums\AuditAction;

class UserProfileObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::UserCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::UserUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::UserDeleted;
    }
}