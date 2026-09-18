<?php

namespace App\Enums;

/**
 * Перечень действий, фиксируемых в журнале audit_log.
 * Значения совпадают с элементами PG-типа audit_action (single source of truth).
 */
enum AuditAction: string
{
    case ItemCreated = 'item.created';
    case ItemUpdated = 'item.updated';
    case ItemDeleted = 'item.deleted';

    case StoreCreated = 'store.created';
    case StoreUpdated = 'store.updated';
    case StoreDeleted = 'store.deleted';

    case WarehouseCreated = 'warehouse.created';
    case WarehouseUpdated = 'warehouse.updated';
    case WarehouseDeleted = 'warehouse.deleted';

    case LabelPresetCreated = 'label_preset.created';
    case LabelPresetUpdated = 'label_preset.updated';
    case LabelPresetDeleted = 'label_preset.deleted';

    case LabelListCreated = 'label_list.created';
    case LabelListUpdated = 'label_list.updated';
    case LabelListDeleted = 'label_list.deleted';

    case AccessGrantCreated = 'access_grant.created';
    case AccessGrantUpdated = 'access_grant.updated';
    case AccessGrantDeleted = 'access_grant.deleted';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';

    case OperationReplenish = 'operation.replenish';
    case OperationWriteoff = 'operation.writeoff';

    case ImageAttached = 'image.attached';
    case ImageDetached = 'image.detached';
    case ImageAltUpdated = 'image.alt_updated';
    case ImageReordered = 'image.reordered';

    case LabelGenerate = 'label.generate';

    case AuthLogin = 'auth.login';
    case AuthLogout = 'auth.logout';
}