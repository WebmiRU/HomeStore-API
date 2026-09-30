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
    /**
     * Остатки расходуемых свойств сразу по многим предметам.
     *
     * Для списка предметов: считать остатки по одному значит сделать три запроса
     * на предмет, а на странице их 15–20. Здесь три запроса на всю страницу,
     * и формула та же, что у одиночного total — иначе в списке и в карточке
     * показывались бы разные числа.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array<int, array{total: float, remaining: float, norm: float}>>
     */
    public function totalsForItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

        if ($itemIds === []) {
            return [];
        }

        $settings = DB::table('item_partial_property')
            ->whereIn('item_id', $itemIds)
            ->orderBy('sort')
            ->get();

        if ($settings->isEmpty()) {
            return [];
        }

        $propertyIds = $settings->pluck('property_id')->map(fn ($id) => (int) $id)->unique()->values();

        $rows = [];
        $items = [];

        foreach (Item::query()->whereIn('id', $itemIds)->get(['id', 'quantity']) as $item) {
            $items[(int) $item->id] = $item;
        }

        // Норма и остаток — по два запроса на страницу, а не на предмет.
        foreach (DB::table('item_property')->whereIn('item_id', $itemIds)->whereIn('property_id', $propertyIds)->get() as $row) {
            $rows[(int) $row->item_id][(int) $row->property_id] = ['norm' => (float) $row->value];
        }

        foreach (ItemPartialRemaining::query()->whereIn('item_id', $itemIds)->get() as $row) {
            $rows[(int) $row->item_id][(int) $row->property_id]['remaining'] = (float) $row->remaining;
        }

        $out = [];

        foreach ($settings as $setting) {
            $itemId = (int) $setting->item_id;
            $propertyId = (int) $setting->property_id;
            $norm = $rows[$itemId][$propertyId]['norm'] ?? null;

            // Без нормы расходовать нечего — та же причина, что и в карточке.
            if ($norm === null || $itemId === 0) {
                continue;
            }

            $remaining = $rows[$itemId][$propertyId]['remaining'] ?? $norm;
            $item = $items[$itemId] ?? null;
            $quantity = $item === null ? 1 : (int) ($item->quantity ?? 1);

            $out[$itemId][$propertyId] = [
                'norm'      => $norm,
                'remaining' => $remaining,
                'total'     => $quantity <= 0 ? 0.0 : ($quantity - 1) * $norm + max(0.0, $remaining),
            ];
        }

        return $out;
    }

    /**
     * Сколько штук помещается в запас по одной норме.
     *
     * Считается делением с остатком, а не через `ceil(запас / норма − допуск)`.
     * Допуск здесь маскировал ошибку, а не гасил её: 40 001 мл при норме
     * 10 000 — это четыре целые штуки и миллилитр в пятой, то есть пять. С
     * вычитанием 1e-4 из 4.0001 получалось ровно 4, и чем крупнее числа, тем
     * сильнее съедалась целая штука. Для целых величин деление точно, а
     * остаток считается вычитанием: счёт от деления с остатком у дробных норм
     * не определён.
     */
    public function piecesInStock(float $stock, float $norm): int
    {
        if ($stock <= 0 || $norm <= 0) {
            return 0;
        }

        $whole = (int) floor($stock / $norm);
        $rest = $stock - $whole * $norm;

        return $rest > 0 ? $whole + 1 : $whole;
    }

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
     * @param  Collection<int, ItemPartialProperty>  $settings
     * @return array{quantity: int, remainders: array<int, float>, emptied: bool}
     */
    public function spend(Item $item, int $propertyId, float $amount, Collection $settings): array
    {
        return $this->apply($item, $propertyId, -$amount, $settings, true);
    }

    /**
     * Пополняет расход по одному свойству.
     *
     * Пополнение симметрично списанию: возвращается столько же, сколько забрали,
     * и целые штуки появляются сами, когда запас перешагивает через норму.
     * Пополнение полностью списанного предмета возвращает его на склад: человек
     * принёс новую бутылку, и бутылка на месте.
     *
     * @param  Collection<int, ItemPartialProperty>  $settings
     * @return array{quantity: int, remainders: array<int, float>, emptied: bool}
     */
    public function replenish(Item $item, int $propertyId, float $amount, Collection $settings): array
    {
        return $this->apply($item, $propertyId, $amount, $settings, false);
    }

    /**
     * Расход по одному свойству: списание — это расход со знаком минус,
     * пополнение — с плюсом, арифметика одна.
     *
     * ## Как считается
     *
     * Число штук выводится из суммарного запаса, а не наоборот. Запас по
     * свойству — сколько осталось всего, вместе со всеми целыми штуками:
     *
     *   запас = (штуки - 1) × норма + остаток текущей штуки
     *
     * Списали 1100 у двух бутылок по 1000 — запас стал 900, и по нему видно,
     * что целых штук больше нет: одна ушла целиком, вторая неполная.
     *
     * Штук столько, сколько ещё держит любое из отмеченных свойств: пустой
     * мешок — это когда нечего есть, поэтому штука живёт, пока в ней есть рис,
     * даже если картошка давно кончилась.
     *
     * ## Почему не поштучно
     *
     * Расход, идущий по штукам («взял 1100: съел целую бутылку и 100 из
     * следующей»), расходится с остатком: у мешка списали весь объём при живом
     * рисе — и объём показывался равным целой следующей штуке, хотя списать его
     * было уже нельзя. Считая от запаса, остаток и число штук всегда сходятся:
     * сколько показано, столько и можно списать.
     *
     * @param  Collection<int, ItemPartialProperty>  $settings
     * @param  bool  $isWriteoff  списание (true) или пополнение (false)
     * @return array{quantity: int, remainders: array<int, float>, emptied: bool}
     */
    private function apply(
        Item $item,
        int $propertyId,
        float $signed,
        Collection $settings,
        bool $isWriteoff,
    ): array {
        $norm = $this->requiredNorm($item, $propertyId);
        $amount = abs($signed);

        if ($amount <= 0) {
            throw new \InvalidArgumentException(
                $isWriteoff
                    ? 'Списание должно быть больше нуля'
                    : 'Пополнение должно быть больше нуля'
            );
        }

        $quantity = (int) ($item->quantity ?? 1);
        $remainders = $this->remainders($item, $settings);
        $remainders[$propertyId] ??= $norm;

        // Запасы всех расходуемых свойств: у изменяемого он станет другим,
        // остальные остаются как есть.
        $stocks = [];

        foreach ($remainders as $id => $remaining) {
            $own = $this->norm($item, (int) $id) ?? $norm;
            $stocks[$id] = $quantity > 0
                ? ($quantity - 1) * $own + max(0.0, (float) $remaining)
                : 0.0;
        }

        $after = $stocks[$propertyId] + $signed;

        if ($isWriteoff && $after < -self::TOLERANCE) {
            throw new \InvalidArgumentException(sprintf(
                '%s: списать нечего — по свойству осталось %s, просят %s',
                $this->subject($item),
                $this->formatAmount(max(0.0, $stocks[$propertyId])),
                $this->formatAmount($amount)
            ));
        }

        $stocks[$propertyId] = max(0.0, $after);

        // Число штук — по отмеченным свойствам: штука держится, пока держится
        // хоть одно из них. Неотмеченные в расчёт не входят: они расходуются,
        // но на жизнь штуки не влияют.
        $quantity = 0;
        $reasons = 0;

        foreach ($settings as $setting) {
            $id = (int) $setting->property_id;
            $own = $this->norm($item, $id);

            if (! $setting->is_full_reason || $own === null || $own <= 0 || ! array_key_exists($id, $stocks)) {
                continue;
            }

            $reasons += 1;
            $held = $this->piecesInStock(max(0.0, $stocks[$id]), $own);
            $quantity = max($quantity, $held);
        }

        // Отмеченных нет — штуками не управляем: остаётся то, что было.
        if ($reasons === 0) {
            $quantity = max(0, (int) ($item->quantity ?? 1));
        }

        $emptied = $quantity <= 0;

        if ($emptied) {
            $quantity = 0;
            $remainders = array_map(fn () => 0.0, $remainders);
        } else {
            // Остаток текущей штуки выводится из запаса: всё, что сверх целых
            // штук, осталось в той, которая расходуется. Запас может быть
            // неполным по нескольким штукам сразу — у мешка списали объём из
            // обеих, — и тогда та штука, что осталась, просто неполная.
            $rebuilt = [];

            foreach ($stocks as $id => $stock) {
                $own = $this->norm($item, (int) $id) ?? $norm;
                $rebuilt[$id] = $this->clampToNorm($stock - ($quantity - 1) * $own, $own);
            }

            $remainders = $rebuilt;
        }

        return [
            'quantity'   => $quantity,
            'remainders' => $remainders,
            'emptied'    => $emptied,
        ];
    }

    /**
     * Норма свойства, без которой расход невозможен.
     *
     * Ноль и пустое значение означают, что расходуемого по свойству нет: списать
     * «ноль граммов» нельзя, и предмет без нормы молча уедал бы штуки.
     */
    public function requiredNorm(Item $item, int $propertyId): float
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
     * Остаток не выходит за пределы штуки.
     *
     * Больше нормы он быть не может: столько в штуке не помещается. Меньше нуля
     * тоже: если запаса не хватило на все штуки, значит часть штук неполная, и
     * текущая штука просто пуста по этому свойству.
     */
    private function clampToNorm(float $remaining, float $norm): float
    {
        if ($remaining <= self::TOLERANCE) {
            return 0.0;
        }

        return min($remaining, $norm);
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
    /**
     * Смена нормы расходуемого свойства при уже имеющемся остатке.
     *
     * Норма — это не описание предмета, а делитель, по которому считаются
     * штуки. Меняя её, человек меняет либо число штук, либо сам запас, и что
     * именно — угадать нельзя: то ли он ошибся при вводе (написал 1000 вместо
     * 100), то ли ошибка в другом месте (в ящике оказалось не 10 литров, а
     * 10 бутылок по 100 мл). Поэтому решение принимает он, а не сервер.
     *
     * recalculate — сохраняется объём: сколько миллилитров было, столько и
     * остаётся, а штуки пересчитываются под новую норму.
     * keep — сохраняется число штук: сколько штук было, столько и остаётся, а
     * запас пересчитывается под новую норму. Остаток внутри штуки при этом
     * упирается в норму: он и есть «неполная штука», а быть больше целой не
     * может.
     *
     * @return array{quantity: int, remaining: float, stock: float}
     */
    public function applyNormChange(Item $item, int $propertyId, float $newNorm, string $mode): array
    {
        $settings = $this->settings($item);
        $quantity = (int) ($item->quantity ?? 1);

        // Метод вызывается ДО записи нового значения, иначе старая норма уже
        // неотличима от новой и сравнивать не с чем.
        $oldNorm = $this->norm($item, $propertyId) ?? $newNorm;
        $remainders = $this->remainders($item, $settings);
        $remaining = $remainders[$propertyId] ?? $oldNorm;
        $stock = $quantity <= 0 ? 0.0 : ($quantity - 1) * $oldNorm + max(0.0, $remaining);

        if ($quantity > 0 && $mode === 'recalculate') {
            $quantity = $this->piecesInStock(max(0.0, $stock), $newNorm);
            $remaining = $this->clampToNorm($stock - ($quantity - 1) * $newNorm, $newNorm);
        } else {
            // Штуки прежние, а остаток упирается в новую норму: он и есть
            // «неполная штука», и быть больше целой он не может.
            $remaining = $this->clampToNorm($remaining, $newNorm);
        }

        $remainders[$propertyId] = $remaining;
        $this->persist($item, $remainders, $quantity);

        return [
            'quantity'  => $quantity,
            'remaining' => $remaining,
            'stock'     => $quantity <= 0 ? 0.0 : ($quantity - 1) * $newNorm + max(0.0, $remaining),
        ];
    }

    /**
     * Норма свойства так, как она записана в карточке.
     */
    public function currentNorm(Item $item, int $propertyId): ?float
    {
        return $this->norm($item, $propertyId);
    }

    /**
     * Что получится при смене норм сразу у нескольких свойств.
     *
     * Считается набором, а не по одному свойству, и это не удобство, а
     * необходимость: число штук у предмета одно и считается оно как большее
     * из отмеченных свойств. Пересчитать «Объём» отдельно от «Вес» значило бы
     * показать человеку два несовместимых будущих и оставить ему выбор между
     * величинами, которых одновременно не бывает.
     *
     * Запас в единицах измерения при смене нормы не меняется: норма — делитель,
     * а не количество. Меняется либо число штук, либо сам запас, в зависимости
     * от режима — поэтому оба и показываются.
     *
     * @param  array<int, float>  $changes  новая норма по каждому изменяемому свойству
     * @return array{quantity: int, properties: array<int, array{stock: float, remaining: float}>}
     */
    public function previewNormChange(Item $item, array $changes, string $mode): array
    {
        $settings = $this->settings($item);
        $quantity = (int) ($item->quantity ?? 1);
        $remainders = $this->remainders($item, $settings);

        /*
         * Запас в единицах измерения сменой нормы не трогается: норма —
         * делитель, а не количество. Считаем его один раз по старой норме, и
         * дальше он служит основанием для обоих вариантов.
         */
        $stocks = [];
        $norms = [];

        foreach ($settings as $setting) {
            $propertyId = (int) $setting->property_id;
            $oldNorm = $this->norm($item, $propertyId);

            if ($oldNorm === null || $oldNorm <= 0) {
                continue;
            }

            $remaining = $remainders[$propertyId] ?? $oldNorm;
            $stocks[$propertyId] = $quantity <= 0
                ? 0.0
                : ($quantity - 1) * $oldNorm + max(0.0, $remaining);
            $norms[$propertyId] = (float) ($changes[$propertyId] ?? $oldNorm);
        }

        // Сколько штук набирается по каждому отмеченному свойству. Показателем
        // пустого предмета считается и то свойство, которое в этом наборе не
        // менялось: оно тоже держит предмет, и его вклад в число штук отменять
        // нельзя.
        $held = [];

        foreach ($settings as $setting) {
            $propertyId = (int) $setting->property_id;

            if (! $setting->is_full_reason || ! isset($stocks[$propertyId])) {
                continue;
            }

            $held[$propertyId] = $quantity <= 0
                ? 0
                : ($mode === 'recalculate'
                    ? $this->piecesInStock($stocks[$propertyId], $norms[$propertyId])
                    : $quantity);
        }

        $newQuantity = $held === [] ? $quantity : max($held);

        /*
         * Сохранить штуки можно не всегда. Если остаток не помещается в них —
         * например, 5 штук по 10 000 мл, когда на руках 4 500, — то «штуки
         * прежние» означают не остаток прежним, а его раздувание с 4 500 до
         * 40 000. Молча увеличивать запас нельзя, поэтому такой вариант
         * помечается невозможным, и человек либо выбирает другой, либо вписывает
         * фактические числа.
         */
        $fits = true;

        foreach ($stocks as $propertyId => $stock) {
            if ($newQuantity > 0 && $stock < ($newQuantity - 1) * $norms[$propertyId]) {
                $fits = false;
            }
        }

        $properties = [];

        foreach ($stocks as $propertyId => $stock) {
            $remainingNew = $newQuantity <= 0
                ? 0.0
                : $this->clampToNorm($stock - ($newQuantity - 1) * $norms[$propertyId], $norms[$propertyId]);

            $properties[$propertyId] = [
                'stock'     => $newQuantity <= 0 ? 0.0 : ($newQuantity - 1) * $norms[$propertyId] + $remainingNew,
                'remaining' => $remainingNew,
                'fits'      => $fits,
            ];
        }

        return [
            'quantity'   => max(0, $newQuantity),
            'properties' => $properties,
        ];
    }

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
