<?php

namespace App\Enums;

/**
 * Направление складской операции. Значения совпадают с CHECK-ограничением
 * колонки stock_operation.direction.
 *
 * Направлений ровно два: возврат списанного — это пополнение, а возврат
 * пополненного — списание. «Это был возврат» означает не значение флага,
 * а наличие ссылки reversed_operation_id.
 */
enum StockDirection: string
{
    case Writeoff = 'writeoff';
    case Replenish = 'replenish';

    public function opposite(): self
    {
        return $this === self::Writeoff ? self::Replenish : self::Writeoff;
    }

    /** Знак дельты остатка при таком направлении. */
    public function sign(): int
    {
        return $this === self::Replenish ? 1 : -1;
    }

    public function label(): string
    {
        return $this === self::Replenish ? 'Пополнение' : 'Списание';
    }

    /** Глагол прошедшего времени для уведомлений: «Списано», «Пополнено». */
    public function pastLabel(): string
    {
        return $this === self::Replenish ? 'Пополнено' : 'Списано';
    }

    /** Значение, которое понимает существующий эндпоинт POST /operation. */
    public function auditType(): string
    {
        return $this === self::Replenish ? 'operation.replenish' : 'operation.writeoff';
    }

    public static function fromAuditType(string $type): self
    {
        return $type === 'operation.replenish' ? self::Replenish : self::Writeoff;
    }
}
