<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPartialProperty;
use App\Models\ItemPartialRemaining;
use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Частичное списание предмета по его числовым свойствам.
 *
 * Обычное свойство — это описание («диаметр 8 мм»). Расходуемое — это запас:
 * у бутылки сиропа «Объём, 1000 мл» означает, что в одной бутылке 1000 мл, и
 * списать можно двести, не выпивая бутылку.
 *
 * ## Как считается остаток
 *
 * Значение свойства — норма на одну штуку. Сколько всего осталось, показывают
 * две величины: целые штуки (это item.quantity) и остаток внутри текущей штуки
 * (item_partial_remaining.remaining). Итог:
 *
 *   (quantity - 1) × норма + remaining
 *
 * Две бутылки по 1000 мл: quantity 2, remaining 1000, всего 2000. Списали 1100 —
 * одна бутылка ушла целиком, в второй осталось 900: quantity 1, remaining 900.
 *
 * ## Когда предмет считается списанным
 *
 * Штука — это неделимая единица расхода. Она заканчивается, когда обнулились
 * все свойства, отмеченные как повод для полного списания: отмечено одно —
 * опустело оно; отмечены картошка и рис — опустели оба, потому что в мешке они
 * лежат рядом и пустой мешок это когда нечего есть. После этого предмет
 * переходит на следующую штуку: количество уменьшается на один, а расходуемые
 * свойства снова полны по своей норме.
 *
 * Из этого следует ограничение, о котором стоит знать: пока штука не опустела
 * по всем отмеченным свойствам, следующая штука не начнёт расходоваться. Если
 * отмечены картошка и рис, а картошка уже кончилась, добить её нельзя, пока
 * рис не исчерпан — мешок один, и он не пуст, пока в нём что-то есть. В этом
 * смысл отметки: она отвечает за то, когда штуку можно считать вычеркнутой.
 *
 * Неотмеченные свойства расходуются, обнуляются и на списание целого предмета
 * не влияют.
 *
 * ## Чего сервис не делает
 *
 * Не трогает количество сам по себе списанием «штуками»: у предмета с
 * частичным списанием такого способа нет вовсе, количество меняется само,
 * когда опустевает штука.
 */
class PartialWriteoff
{
    /**
     * Разница, ниже которой число считаем нулём.
     *
     * Остатки — двойные числа, и после многократного вычитания 0.1 накапливается
     * ошибка порядка 1e-15. Сравнивать с нулём как есть нельзя: штука, которая
     * давно опустела, «полупуста» и предмет никогда не спишется.
     */
    public const TOLERANCE = 0.000001;

    /**
     * Предохранитель от зацикливания.
     *
     * Списание идёт поштучно, и на очень большой сумме шагов будет много. Но
     * число штук у предмета — целое и небольшое, а лишний шаг означает ошибку в
     * расчёте, а не законный делёж. Обрываемся с ошибкой, а не крутимся вечно.
     */
    private const MAX_STEPS = 10000;

    /** Настройки расхода предмета с порядком предложения. */
    public function settings(Item $item): Collection
    {
        return ItemPartialProperty::query()
            ->where('item_id', $item->id)
            ->orderBy('sort')
            ->orderBy('property_id')
            ->get();
    }

    /**
     * Норма свойства у предмета: сколько единиц в одной штуке.
     *
     * Берётся из значения свойства. Свойств с несколькими значениями у одного
     * предмета быть не может — при записи они схлопываются, — так что строка
     * всегда одна.
     */
    public function norm(Item $item, int $propertyId): ?float
    {
        $row = DB::table('item_property')
            ->where('item_id', $item->id)
            ->where('property_id', $propertyId)
            ->first();

        if ($row === null || $row->value === null) {
            return null;
        }

        return (float) $row->value;
    }

    /**
     * Остаток внутри текущей штуки.
     *
     * Записи может не быть: остаток не трогали, и текущая штука полна. Это
     * важно для предмета, который только что получит настройку: он появляется
     * с полными штуками, а не с нулями.
     */
    public function remaining(Item $item, int $propertyId, float $norm): float
    {
        $row = ItemPartialRemaining::query()
            ->where('item_id', $item->id)
            ->where('property_id', $propertyId)
            ->first();

        return $row === null ? $norm : (float) $row->remaining;
    }

    /** Сколько всего осталось по свойству, со всеми целыми штуками. */
    public function total(Item $item, int $propertyId, float $norm, ?int $quantity = null): float
    {
        $quantity = $quantity ?? (int) ($item->quantity ?? 1);

        if ($quantity <= 0) {
            return 0.0;
        }

        return ($quantity - 1) * $norm + $this->remaining($item, $propertyId, $norm);
    }

    /**
     * Все остатки расходуемых свойств: ключ — id свойства, значение — сколько
     * осталось внутри текущей штуки. Полная штука отдаёт свою норму.
     *
     * @return array<int, float>
     */
    public function remainders(Item $item, Collection $settings): array
    {
        $remainders = [];

        foreach ($settings as $setting) {
            $norm = $this->norm($item, (int) $setting->property_id);

            if ($norm === null) {
                continue;
            }

            $remainders[(int) $setting->property_id] = $this->remaining($item, (int) $setting->property_id, $norm);
        }

        return $remainders;
    }

    /**
     * Списывает расход по одному свойству.
     *
     * @param  Collection<int, ItemPartialProperty>  $settings  все настройки предмета: нужны,
     *                                                            чтобы опустела ли штука по всем
     *                                                            отмеченным свойствам, а не по одному
     * @return array{quantity: int, remainders: array<int, float>, emptied: bool} остатки текущей штуки и признак того, что штука кончилась
     */
    public function spend(Item $item, int $propertyId, float $amount, Collection $settings): array
    {
        $norm = $this->requiredNorm($item, $propertyId);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Списание должно быть больше нуля');
        }

        // Пустое количество — это не ноль, а «без количества»: предмет, которого
        // на складе одна штука и который поштучно не считают. Расходуется он
        // как одна штука, иначе первое же частичное списание сказало бы, что
        // расходуемого не осталось, хотя бутылка на складе есть.
        $quantity = (int) ($item->quantity ?? 1);

        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                $this->subject($item) . ': расходуемого уже не осталось'
            );
        }

        $remainders = $this->remainders($item, $settings);
        $remainders[$propertyId] ??= $norm;

        $left = $amount;
        $steps = 0;

        while ($left > self::TOLERANCE) {
            if (++$steps > self::MAX_STEPS) {
                throw new \InvalidArgumentException(
                    $this->subject($item) . ': расчёт списания не сходится'
                );
            }

            $inUnit = max(0.0, (float) $remainders[$propertyId]);

            if ($inUnit > self::TOLERANCE) {
                $take = min($left, $inUnit);
                $remainders[$propertyId] = $this->clampToNorm($inUnit - $take, $norm);
                $left -= $take;
            }

            if ($left <= self::TOLERANCE) {
                break;
            }

            // Текущей штукой по этому свойству больше нечего брать, а списать
            // осталось. Дальше расход пойдёт из следующей штуки, но перейти к
            // ней можно только опустев текущую целиком.
            if (! $this->unitIsSpent($remainders, $settings)) {
                break;
            }

            $quantity -= 1;

            if ($quantity <= 0) {
                $quantity = 0;
                $remainders = array_map(fn () => 0.0, $remainders);
                break;
            }

            // Новая штука полна: каждое расходуемое свойство отдаёт свою норму.
            $remainders = $this->fullUnit($item, $remainders, $norm);
        }

        // Штука могла опустеть ровно на последнем взятом остатке, и тогда цикл
        // выше уже не заходил — а штука кончилась, предмет должен уменьшиться.
        // Без этой проверки бутылку можно было бы списать ровно под ноль, и она
        // осталась бы на складе навсегда.
        if ($left <= self::TOLERANCE && $quantity > 0 && $this->unitIsSpent($remainders, $settings)) {
            $quantity -= 1;
            $remainders = $quantity > 0
                ? $this->fullUnit($item, $remainders, $norm)
                : array_map(fn () => 0.0, $remainders);
        }

        if ($left > self::TOLERANCE) {
            $available = $this->total($item, $propertyId, $norm, (int) ($item->quantity ?? 0));

            throw new \InvalidArgumentException(sprintf(
                '%s: списать нечего — по свойству осталось %s, просят %s',
                $this->subject($item),
                $this->formatAmount($available),
                $this->formatAmount($amount)
            ));
        }

        return [
            'quantity'   => $quantity,
            'remainders' => $remainders,
            'emptied'    => $quantity <= 0,
        ];
    }

    /**
     * Пополняет расход по одному свойству.
     *
     * Пополнение симметрично списанию: возвращается столько же, сколько забрали,
     * и целые штуки появляются сами, когда остаток перешагивает через норму.
     * Пополнение полностью списанного предмета возвращает его на склад: человек
     * принёс новую бутылку, и бутылка на месте.
     *
     * @param  Collection<int, ItemPartialProperty>  $settings
     * @return array{quantity: int, remainders: array<int, float>, emptied: bool}
     */
    public function replenish(Item $item, int $propertyId, float $amount, Collection $settings): array
    {
        $norm = $this->requiredNorm($item, $propertyId);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Пополнение должно быть больше нуля');
        }

        $quantity = (int) ($item->quantity ?? 1);
        $remainders = $this->remainders($item, $settings);
        $remainders[$propertyId] ??= $norm;

        $left = $amount;
        $steps = 0;

        while ($left > self::TOLERANCE) {
            if (++$steps > self::MAX_STEPS) {
                throw new \InvalidArgumentException(
                    $this->subject($item) . ': расчёт пополнения не сходится'
                );
            }

            if ($quantity <= 0) {
                // Предмета не было — принесли новую штуку. Она пустая, и
                // наполняется с нуля.
                $quantity = 1;
                $remainders = array_map(fn () => 0.0, $remainders);
            }

            $inUnit = min($norm, max(0.0, (float) $remainders[$propertyId]));
            $room = $norm - $inUnit;

            if ($room > self::TOLERANCE) {
                $put = min($left, $room);
                $remainders[$propertyId] = $this->clampToNorm($inUnit + $put, $norm);
                $left -= $put;
            }

            if ($left <= self::TOLERANCE) {
                break;
            }

            // Текущая штука наполнена по этому свойству, а положить осталось.
            // Следующая штука начнёт наполняться, только если нынешняя заполнена
            // по всем отмеченным свойствам: иначе часть её содержимого
            // потерялась бы при переходе.
            if (! $this->unitIsFull($item, $remainders, $settings)) {
                break;
            }

            // Переход на следующую штуку: она пустая.
            $quantity += 1;
            $remainders = array_map(fn () => 0.0, $remainders);
        }

        if ($left > self::TOLERANCE) {
            throw new \InvalidArgumentException(sprintf(
                '%s: пополнить не во что — штука не заполнена по отмеченным свойствам',
                $this->subject($item)
            ));
        }

        return [
            'quantity'   => $quantity,
            'remainders' => $remainders,
            'emptied'    => false,
        ];
    }

    /**
     * Норма свойства, без которой расход невозможен.
     *
     * Ноль и пустое значение означают, что расходуемого по свойству нет: списать
     * «ноль граммов» нельзя, и предмет без нормы молча уедал бы штуки.
     */
    private function requiredNorm(Item $item, int $propertyId): float
    {
        $norm = $this->norm($item, $propertyId);

        if ($norm === null || $norm <= 0) {
            throw new \InvalidArgumentException(sprintf(
                '%s: у расходуемого свойства не задано значение',
                $this->subject($item)
            ));
        }

        return $norm;
    }

    /**
     * Новая штука полна по всем расходуемым свойствам.
     *
     * У каждого своя норма: у мешка картошка 1000 и рис 5, и пересчёт по чужой
     * норме схлопнул бы картошку в 5.
     *
     * @param  array<int, float>  $remainders
     * @return array<int, float>
     */
    private function fullUnit(Item $item, array $remainders, float $fallback): array
    {
        $full = [];

        foreach (array_keys($remainders) as $propertyId) {
            $full[$propertyId] = $this->norm($item, (int) $propertyId) ?? $fallback;
        }

        return $full;
    }

    /**
     * Остаток не выходит за пределы штуки.
     *
     * Больше нормы он быть не может: столько в штуке не помещается. Меньше нуля
     * тоже: расход не выдаёт отрицательный остаток, недоеденное переходит в
     * следующую штуку.
     */
    private function clampToNorm(float $remaining, float $norm): float
    {
        if ($remaining <= self::TOLERANCE) {
            return 0.0;
        }

        return min($remaining, $norm);
    }

    /**
     * Опустела ли текущая штука по всем отмеченным свойствам.
     *
     * Отмеченных нет — штука не кончается никогда: нечего опустошать, и
     * предмет живёт, пока есть расход по неотмеченным.
     *
     * @param  array<int, float>  $remainders
     * @param  Collection<int, ItemPartialProperty>  $settings
     */
    private function unitIsSpent(array $remainders, Collection $settings): bool
    {
        $reasons = $settings->where('is_full_reason', true);

        if ($reasons->isEmpty()) {
            return false;
        }

        foreach ($reasons as $setting) {
            $remaining = $remainders[(int) $setting->property_id] ?? null;

            if ($remaining === null || $remaining > self::TOLERANCE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Заполнена ли текущая штука по всем отмеченным свойствам.
     *
     * Сравнивать надо с нормой каждого свойства: остаток в 5 у нормы 5 — это
     * полная штука, а остаток в 5 у нормы 1000 — пустая.
     *
     * @param  array<int, float>  $remainders
     * @param  Collection<int, ItemPartialProperty>  $settings
     */
    private function unitIsFull(Item $item, array $remainders, Collection $settings): bool
    {
        $reasons = $settings->where('is_full_reason', true);

        if ($reasons->isEmpty()) {
            return false;
        }

        foreach ($reasons as $setting) {
            $propertyId = (int) $setting->property_id;
            $remaining = $remainders[$propertyId] ?? null;
            $norm = $this->norm($item, $propertyId);

            if ($remaining === null || $norm === null) {
                return false;
            }

            if ($norm - $remaining > self::TOLERANCE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Сохраняет новые остатки и количество предмета.
     *
     * @param  array<int, float>  $remainders
     */
    public function persist(Item $item, array $remainders, int $quantity): void
    {
        DB::transaction(function () use ($item, $remainders, $quantity): void {
            foreach ($remainders as $propertyId => $remaining) {
                ItemPartialRemaining::query()->updateOrCreate(
                    ['item_id' => $item->id, 'property_id' => $propertyId],
                    ['remaining' => max(0.0, (float) $remaining)],
                );
            }

            $item->forceFill(['quantity' => $quantity])->save();
        });
    }

    /**
     * Приводит остатки в порядок после правки количества или значения
     * свойства.
     *
     * Количество, выставленное руками, меняет число штук, а остаток текущей
     * штуки остаётся прежним — и суммарный запас тот же. А вот правка самого
     * свойства меняет норму, и тогда остаток, записанный по старой норме,
     * окажется либо больше штуки, либо бессмысленно мал. В обоих случаях остаток
     * приводится к норме: штука полна, и человек видит то, что ввёл.
     *
     * @param  Collection<int, ItemPartialProperty>  $settings
     */
    public function resync(Item $item, Collection $settings): void
    {
        foreach ($settings as $setting) {
            $propertyId = (int) $setting->property_id;
            $norm = $this->norm($item, $propertyId);

            if ($norm === null || $norm <= 0) {
                continue;
            }

            $row = ItemPartialRemaining::query()
                ->where('item_id', $item->id)
                ->where('property_id', $propertyId)
                ->first();

            if ($row === null) {
                continue;
            }

            $remaining = (float) $row->remaining;

            if ($remaining > $norm + self::TOLERANCE) {
                $row->forceFill(['remaining' => $norm])->save();
            }
        }
    }

    /**
     * Убирает остатки свойств, которые больше не расходуются.
     *
     * Свойство могли убрать из настроек или перестать быть числовым: иначе в
     * предмете остался бы остаток свойства, которого в интерфейсе уже нет, а
     * расчёт по нему продолжал бы идти.
     *
     * @param  Collection<int, ItemPartialProperty>  $settings
     */
    public function forgetUnconfigured(Item $item, Collection $settings): void
    {
        $keep = $settings->pluck('property_id')->map(fn ($id) => (int) $id)->all();

        ItemPartialRemaining::query()
            ->where('item_id', $item->id)
            ->whereNotIn('property_id', $keep === [] ? [0] : $keep)
            ->delete();
    }

    /** Название предмета для сообщения об ошибке. */
    private function subject(Item $item): string
    {
        return sprintf('Предмет «%s»', $item->title);
    }

    /** Человеческое число в сообщении: 900, а не 900.0000000001. */
    private function formatAmount(float $amount): string
    {
        $rounded = round($amount, 3);

        return rtrim(rtrim(number_format($rounded, 3, ',', ' '), '0'), ',');
    }

    /**
     * Сохраняет настройки расхода по свойствам предмета.
     *
     * Числовыми считаются только целое и дробное: по тексту, «да/нет» и
     * значению из справочника расход невозможен, и такая настройка была бы
     * обещанием, которое списание не выполнит.
     *
     * Настройки и режим «Списывать по коду» взаимоисключающи: там единица —
     * код, здесь — запас свойства. Включены оба сразу, количество уменьшалось бы
     * двумя несовместимыми способами, поэтому включённый выключает второй.
     *
     * @param  array<int, array{property_id: mixed, step?: mixed, is_full_reason?: mixed, sort?: mixed}>  $rows
     * @throws \Illuminate\Validation\ValidationException
     */
    public function syncSettings(Item $item, array $rows): void
    {
        $numericIds = Property::query()
            ->whereIn('id', collect($rows)->pluck('property_id')->filter()->all())
            ->whereIn('type', ['int', 'float'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $errors = [];
        $prepared = [];

        foreach (array_values($rows) as $position => $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);

            if (! in_array($propertyId, $numericIds, true)) {
                $errors["partial_properties.{$position}"] = __('Частично списывать можно только целое и дробное число');

                continue;
            }

            $step = (float) ($row['step'] ?? 1);

            if ($step <= 0) {
                $errors["partial_properties.{$position}"] = __('Шаг списания должен быть больше нуля');

                continue;
            }

            $prepared[$propertyId] = [
                'step'           => $step,
                'is_full_reason' => (bool) ($row['is_full_reason'] ?? true),
                'sort'           => (int) ($row['sort'] ?? $position),
            ];
        }

        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($item, $prepared): void {
            // Записи с прежними настройками, которых больше нет в списке,
            // убираются: свойство может перестать расходоваться, и лишняя
            // настройка продолжала бы списывать по нему.
            ItemPartialProperty::query()
                ->where('item_id', $item->id)
                ->when($prepared !== [], fn ($q) => $q->whereNotIn('property_id', array_keys($prepared)))
                ->delete();

            foreach ($prepared as $propertyId => $values) {
                ItemPartialProperty::query()->updateOrCreate(
                    ['item_id' => $item->id, 'property_id' => $propertyId],
                    $values,
                );
            }

            // Остатки приводим в порядок под новый набор настроек: свойства,
            // которое больше не расходуется, в остатках не остаётся.
            $this->forgetUnconfigured($item, $this->settings($item));
            $this->resync($item, $this->settings($item));

            // Два режима сразу не работают: включённый выключает второй.
            $hasPartial = $prepared !== [];

            if ($hasPartial && $item->release_code_on_writeoff) {
                $item->forceFill(['release_code_on_writeoff' => false])->save();
            } elseif ($hasPartial) {
                $item->forceFill(['partial_writeoff' => true])->save();
            } elseif ($item->partial_writeoff) {
                $item->forceFill(['partial_writeoff' => false])->save();
            }
        });
    }

    /** Свойства, которые вообще можно расходовать частями. */
    public function spendableProperties(): Collection
    {
        return Property::query()
            ->whereIn('type', ['int', 'float'])
            ->orderBy('title')
            ->get();
    }
}
