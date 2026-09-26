<?php

namespace App\Observers;

use App\Enums\AuditAction;

class VendorObserver extends AuditObserves
{
    protected function actionForCreated(): AuditAction
    {
        return AuditAction::VendorCreated;
    }

    protected function actionForUpdated(): AuditAction
    {
        return AuditAction::VendorUpdated;
    }

    protected function actionForDeleted(): AuditAction
    {
        return AuditAction::VendorDeleted;
    }
}
