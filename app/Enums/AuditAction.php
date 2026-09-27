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
    case ItemRestored = 'item.restored';

    case StoreCreated = 'store.created';
    case StoreUpdated = 'store.updated';
    case StoreDeleted = 'store.deleted';
    case StoreRestored = 'store.restored';

    case WarehouseCreated = 'warehouse.created';
    case WarehouseUpdated = 'warehouse.updated';
    case WarehouseDeleted = 'warehouse.deleted';
    case WarehouseRestored = 'warehouse.restored';

    case LabelPresetCreated = 'label_preset.created';
    case LabelPresetUpdated = 'label_preset.updated';
    case LabelPresetDeleted = 'label_preset.deleted';
    case LabelPresetRestored = 'label_preset.restored';

    case LabelListCreated = 'label_list.created';
    case LabelListUpdated = 'label_list.updated';
    case LabelListDeleted = 'label_list.deleted';
    case LabelListRestored = 'label_list.restored';

    case AccessGrantCreated = 'access_grant.created';
    case AccessGrantUpdated = 'access_grant.updated';
    case AccessGrantDeleted = 'access_grant.deleted';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserRestored = 'user.restored';

    case CategoryCreated = 'category.created';
    case CategoryUpdated = 'category.updated';
    case CategoryDeleted = 'category.deleted';
    case CategoryRestored = 'category.restored';

    case PropertyCreated = 'property.created';
    case PropertyUpdated = 'property.updated';
    case PropertyDeleted = 'property.deleted';
    case PropertyRestored = 'property.restored';

    case PropertyGroupCreated = 'property_group.created';
    case PropertyGroupUpdated = 'property_group.updated';
    case PropertyGroupDeleted = 'property_group.deleted';
    case PropertyGroupRestored = 'property_group.restored';

    case DictionaryCreated = 'dictionary.created';
    case DictionaryUpdated = 'dictionary.updated';
    case DictionaryDeleted = 'dictionary.deleted';
    case DictionaryRestored = 'dictionary.restored';

    case DictionaryValueCreated = 'dictionary_value.created';
    case DictionaryValueUpdated = 'dictionary_value.updated';
    case DictionaryValueDeleted = 'dictionary_value.deleted';
    case DictionaryValueRestored = 'dictionary_value.restored';

    case UnitCreated = 'unit.created';
    case UnitUpdated = 'unit.updated';
    case UnitDeleted = 'unit.deleted';
    case UnitRestored = 'unit.restored';

    case VendorCreated = 'vendor.created';
    case VendorUpdated = 'vendor.updated';
    case VendorDeleted = 'vendor.deleted';
    case VendorRestored = 'vendor.restored';

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