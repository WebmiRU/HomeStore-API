<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\StockDirection;
use App\Http\Requests\StoreCorrectionRequest;
use App\Http\Requests\StoreOperationRequest;
use App\Http\Resources\StockOperationResource;
use App\Models\Code;
use App\Models\Item;
use App\Models\StockOperation;
use App\Models\Property;
use App\Services\AccessService;
use App\Services\AuditLogService;
use App\Services\CodedQuantity;
use App\Services\PartialWriteoff;
use App\Services\StockOperationService;
use App\Services\WriteoffCodeRelease;
use App\Support\CodeFormat;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationController extends Controller
{
    public function __construct(
        private readonly AuditLogService $logs,
        private readonly StockOperationService $stock,
        private readonly WriteoffCodeRelease $releasedCodes,
        private readonly CodedQuantity $codedQuantity,
        private readonly PartialWriteoff $partialWriteoff,
    ) {
    }

    public function store(StoreOperationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = $validated['type'];
        $payload = $validated['payload'];
        $direction = StockDirection::fromAuditType($type);
        $comment = $validated['comment'] ?? null;

        $appliedRows = [];
        $prepared = [];
        $operation = null;

        // Журнал пишется в той же транзакции, что и применение. Раньше он шёл
        // после: если между применением и записью что-то ломалось, остаток на
        // складе менялся, а в журнале движения не было — и вернуть такое
        // движение было нечем. Неразделимое применение с журналом и есть смысл
        // транзакции.
        //
        // Отказ расчёта — обычная ошибка проверки, а не поломка: человек вписал
        // больше, чем осталось. Такое возвращается кодом 422 с текстом, а не
        // 500 с трассировкой, за которой ничего не сделать.
        DB::transaction(function () use ($type, $payload, $direction, $comment, &$appliedRows, &$prepared, &$operation): void {
            try {
                $this->applyOperation($type, $payload, $appliedRows, $prepared);
                $operation = $this->record($type, $direction, $comment, $appliedRows);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['payload' => [$e->getMessage()]]);
            }
        });

        return $this->answer($type, $payload, $appliedRows, $operation);
    }

    /**
     * Запись операции и её строк в журнал, плюс журнал действий.
     *
     * @param  array<int, mixed>  $appliedRows
     */
    /**
     * Корректировка остатка фактическим.
     *
     * Человек знает, сколько товара на руках, и сверяет с тем, что показывает
     * система. Списаниями такое не поправить: подгонять остаток сотней мешков
     * ради одной ошибки в норме — значит наврать в журнале, а журнал для того и
     * ведётся. Поэтому здесь вводится факт, а дельту считает сервер.
     *
     * Само правило расчёта то же, что у обычной операции: если факт больше
     * остатка — это пополнение, меньше — списание. Разница только в том, что
     * человек не выбирает знак, и потому не может ошибиться в арифметике.
     *
     * Пометка «Корректировка» идёт в комментарий операции: без неё в движениях
     * видно списание, и человек решит, что что-то забрали со склада.
     */
    public function correction(StoreCorrectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $rows = $validated['payload'];
        $comment = trim((string) ($validated['comment'] ?? ''));
        $label = __('Корректировка');
        $appliedRows = [];
        $operation = null;

        DB::transaction(function () use ($rows, $comment, $label, &$appliedRows, &$operation): void {
            try {
                foreach ($rows as $row) {
                    $this->applyCorrection($row, $appliedRows);
                }

                $operation = $this->recordCorrection($comment, $label, $appliedRows);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['payload' => [$e->getMessage()]]);
            }
        });

        return (new StockOperationResource($operation->load(['author', 'rows'])))->response();
    }

    /**
     * Дельта по одной строке корректировки: обычный предмет — целыми штуками,
     * расходуемый — по фактическому остатку каждого указанного свойства.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, mixed>  $appliedRows
     */
    private function applyCorrection(array $row, array &$appliedRows): void
    {
        $item = Item::query()->whereKey((int) $row['item_id'])->lockForUpdate()->firstOrFail();

        // Корректировка меняет остаток так же, как обычная операция, и прав на
        // неё столько же: без права правки предмета остаток не трогаем.
        abort_unless(app(AccessService::class)->canEdit($item), 403, __('Недостаточно прав для правки предмета'));

        $code = (string) ($item->codes()->orderBy('id')->value('code') ?? '');
        $settings = $this->partialWriteoff->settings($item);
        $isPartial = $settings->isNotEmpty();

        if ($isPartial) {
            foreach ($row['properties'] ?? [] as $property) {
                $this->applyCorrectionToProperty($item, $settings, $code, $appliedRows, $row, $property);
            }

            return;
        }

        if (! array_key_exists('quantity', $row)) {
            return;
        }

        /*
         * У предмета, который живёт по кодам, количество равно числу его
         * кодов, и сервер пересчитывает его сам при каждом изменении. Записать
         * корректировку можно было бы, но через секунду она исчезла бы сама, и
         * в журнале осталась бы запись о движении, которого не было.
         */
        if ($item->release_code_on_writeoff) {
            throw new \InvalidArgumentException(sprintf(
                '%s: количество у предмета, который живёт по кодам, считается по кодам — поправьте число кодов',
                $item->title
            ));
        }

        $before = (int) ($item->quantity ?? 1);
        $after = (int) $row['quantity'];

        if ($after === $before) {
            return;
        }

        $appliedRows[] = [
            'code'     => $code,
            'item_id'  => $item->id,
            'owner_id' => $item->user_id,
            'title'    => $item->title,
            'delta'    => abs($after - $before),
            'before'   => $before,
            'after'    => $after,
            'is_writeoff' => $after < $before,
        ];

        $item->forceFill(['quantity' => $after])->save();
    }

    /**
     * @param  \Illuminate\Support\Collection  $settings
     * @param  array<int, mixed>  $appliedRows
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $property
     */
    private function applyCorrectionToProperty(
        Item $item,
        $settings,
        string $code,
        array &$appliedRows,
        array $row,
        array $property,
    ): void {
        $propertyId = (int) $property['property_id'];
        $actual = round((float) $property['actual'], 3);
        $norm = $this->partialWriteoff->requiredNorm($item, $propertyId);
        $current = $this->partialWriteoff->total($item, $propertyId, $norm);
        $delta = round($actual - $current, 3);

        if (abs($delta) < 0.0001) {
            return;
        }

        // Свойство могло быть расходуемым, а могло перестать: проверка настройки
        // нужна, иначе корректировкой можно было бы создать расход там, где
        // его не бывает.
        if (! $settings->contains('property_id', $propertyId)) {
            throw new \InvalidArgumentException(sprintf(
                '%s: свойство «%s» не расходуется частями',
                $item->title,
                $this->propertyTitle($propertyId)
            ));
        }

        $appliedRows = [
            ...$appliedRows,
            ...$this->applyParts($item, [[
                'property_id' => $propertyId,
                'amount'      => abs($delta),
            ]], $delta < 0 ? 'operation.writeoff' : 'operation.replenish', $code),
        ];
    }

    /**
     * @param  array<int, mixed>  $appliedRows
     */
    private function recordCorrection(string $comment, string $label, array $appliedRows): StockOperation
    {
        if ($appliedRows === []) {
            throw new \InvalidArgumentException(__('Фактический остаток совпадает с тем, что уже заведено'));
        }

        $direction = collect($appliedRows)->contains(fn ($row) => ($row['is_writeoff'] ?? false) || ($row['delta'] ?? 0) > 0)
            && $this->correctionIsWriteoff($appliedRows)
            ? StockDirection::Writeoff
            : StockDirection::Replenish;

        $parts = array_filter([$label, $comment !== '' ? $comment : null]);

        $operation = $this->stock->record($direction, implode(': ', $parts), $appliedRows);

        foreach ($appliedRows as $row) {
            $this->logs->record(
                $direction === StockDirection::Writeoff ? AuditAction::OperationWriteoff : AuditAction::OperationReplenish,
                'item_id',
                (int) $row['item_id'],
                (int) ($row['owner_id'] ?? 0),
                $this->journalPayload($row, $operation),
            );
        }

        return $operation;
    }

    /**
     * Направление операции: смешанную правку (по штукам плюс, по свойствам
     * минус) в одну операцию не свести, поэтому берётся то, что преобладает,
     * а точные дельты остаются в строках.
     *
     * @param  array<int, mixed>  $appliedRows
     */
    private function correctionIsWriteoff(array $appliedRows): bool
    {
        $spend = 0;
        $fill = 0;

        foreach ($appliedRows as $row) {
            $weight = isset($row['amount']) ? (float) $row['amount'] : (float) abs($row['delta'] ?? 0);
            $isSpend = ($row['is_writeoff'] ?? null) ?? ((int) ($row['before'] ?? 0) > (int) ($row['after'] ?? 0));

            $isSpend ? $spend += $weight : $fill += $weight;
        }

        return $spend > $fill;
    }

    private function record(
        string $type,
        StockDirection $direction,
        ?string $comment,
        array $appliedRows,
    ): StockOperation {
        // Журнал операций: отдельная запись на каждый предмет (видимость — владелец предмета).
        $action = $type === 'operation.replenish'
            ? AuditAction::OperationReplenish
            : AuditAction::OperationWriteoff;

        $operation = $this->stock->record($direction, $comment, $appliedRows);

        foreach ($appliedRows as $appliedRow) {
            $this->logs->record(
                $action,
                'item_id',
                (int) $appliedRow['item_id'],
                (int) ($appliedRow['owner_id'] ?? 0),
                $this->journalPayload($appliedRow, $operation),
            );
        }

        return $operation;
    }

    /**
     * Что пишется в журнал действий по строке операции.
     *
     * @param  array<string, mixed>  $appliedRow
     * @return array<string, mixed>
     */
    /**
     * Проверка и применение операции целиком.
     *
     * Отдельным методом, чтобы перехват ошибок расчёта не накрывал собой
     * транзакцию: catch внутри замыкания поймал бы и то, что сломалось внутри
     * самого DB::transaction, и проглотил бы ошибку базы.
     *
     * @param  array<int, mixed>  $appliedRows
     * @param  array<int, mixed>  $prepared
     */
    private function applyOperation(string $type, array $payload, array &$appliedRows, array &$prepared): void
    {
            // Предметы этой операции блокируются до проверок остатка.
            //
            // Две операции, отправленные одновременно с двух устройств, читают
            // остаток по очереди и обе видят одно и то же число: при остатке
            // 100 обе проверки «списать 60» проходят, и в журнал ложатся две
            // строки по 60, будто списали 120. Со склада уйдёт 60 — то есть
            // остаток верный, а журнал врёт, и разойтись они могут позже, при
            // возврате. С блокировкой вторая операция дождётся первой и увидит
            // уже изменённый остаток.
            //
            // Блокировки берутся по возрастанию id: в одинаковом порядке они не
            // могут столкнуться друг с другом, пока предметы идут в одной
            // операции вразнобой.
            $itemIds = [];

            foreach ($payload as $row) {
                [, $matches] = $this->resolveCode($row['code']);

                foreach ($matches as $match) {
                    if ($match->item_id !== null) {
                        $itemIds[(int) $match->item_id] = true;
                    }
                }
            }

            $locked = $itemIds === []
                ? collect()
                : Item::query()
                    ->whereIn('id', array_keys($itemIds))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            // Проход 1: проверяем ВСЕ строки и собираем все проблемы разом,
            // чтобы операция не «падала» с одним сообщением по первой же строке.
            $errors = [];

            // Сколько единиц предмета уже занято строками этой же операции.
            //
            // Один предмет может встретиться в операции несколько раз — при
            // списании по коду это обычное дело: отсканировали два разных
            // кода одного товара, и это две строки. Проверять каждую строку
            // по исходному остатку нельзя: обе видели бы «в наличии 2» и обе
            // прошли бы, а списали бы 2 из 2 — и в журнале легли бы строки с
            // одинаковым «было 2 → стало 1». Остаток считается на лету.
            $taken = [];

            foreach ($payload as $row) {
                [$storeMatch, $items] = $this->resolveCode($row['code']);

                if ($storeMatch !== null) {
                    $store = $storeMatch->store;
                    $errors[] = sprintf(
                        'Хранилище "%s" нельзя %s',
                        $store?->title ?? $row['code'],
                        $type === 'operation.replenish' ? 'пополнить' : 'списать'
                    );
                    continue;
                }

                if ($items->isEmpty()) {
                    $errors[] = sprintf('Код "%s" не найден или недоступен', $row['code']);
                    continue;
                }

                if ($items->count() > 1) {
                    // Коллизия: одинаковый код у нескольких предметов.
                    // Направляем выбор конкретного предмета на стороне клиента.
                    $itemId = (int) ($row['item_id'] ?? 0);

                    if ($itemId === 0 || !$items->has($itemId)) {
                        $errors[] = sprintf(
                            'Код "%s" привязан к нескольким предметам — укажите конкретный предмет',
                            $row['code']
                        );
                        continue;
                    }

                    $item = $items->get($itemId)->item;
                } else {
                    $item = $items->first()->item;
                }

                if (!$item) {
                    $errors[] = sprintf('Код "%s" не привязан к предмету', $row['code']);
                    continue;
                }

                // Остаток читается с заблокированной модели предмета: та, что
                // нашлась по коду, прочитана до блокировки и могла устареть, пока
                // операция ждала свою очередь.
                $item = $locked->get((int) $item->id) ?? $item;

                $settings = $this->partialWriteoff->settings($item);

                // Предмет, который расходуется частями, штуками не списывается
                // вовсе: количество у него меняется само, когда опустела штука,
                // и «списать 2 штуки» значило бы списать две нормы молока.
                // Поэтому у такой строки расход приходит по свойствам.
                if ($settings->isNotEmpty()) {
                    $parts = $this->normalizeParts($row, $item, $settings, $errors);

                    if ($parts === null) {
                        continue;
                    }

                    $prepared[] = [
                        'code'  => $row['code'],
                        'item'  => $item,
                        'parts' => $parts,
                    ];

                    continue;
                }

                if (! empty($row['parts'])) {
                    $errors[] = sprintf(
                        'Предмет "%s" не расходуется частями — списание штуками',
                        $item->title
                    );
                    continue;
                }

                $requested = (int) ($row['quantity'] ?? 0);

                // Списание по коду снимает ровно одну единицу: отсканированный
                // код и означает одну упаковку. Списать больше можно,
                // отсканировав остальные коды, — иначе снятое количество
                // разошлось бы с числом высвобождённых кодов, и откат не смог
                // бы их вернуть.
                //
                // Решает не пометка, а то, высвободится ли код на самом деле:
                // у предмета с единственным кодом высвобождать нечего, и его
                // количество должно уменьшаться так, как просил пользователь.
                $releasesCode = $type === 'operation.writeoff'
                    && $this->releasedCodes->willRelease($item, $row['code']);

                $delta = $releasesCode ? 1 : $requested;

                if ($type === 'operation.writeoff') {
                    // Остаток к моменту этой строки: исходный минус всё, что
                    // операция уже забрала у того же предмета.
                    $stock = $item->quantity === null ? 1 : (int) $item->quantity;
                    $left = $stock - ($taken[$item->id] ?? 0);

                    if ($left < $delta) {
                        $errors[] = sprintf(
                            'Недостаточно количества у предмета "%s" (в наличии %d)',
                            $item->title,
                            $stock
                        );
                        continue;
                    }

                    if ($item->quantity === null && $delta > 1) {
                        $errors[] = sprintf('У предмета "%s" один экземпляр', $item->title);
                        continue;
                    }
                }

                $taken[$item->id] = ($taken[$item->id] ?? 0) + $delta;

                $prepared[] = [
                    'code'      => $row['code'],
                    'item'      => $item,
                    'delta'     => $delta,
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'payload' => $errors,
                ]);
            }

            // Проход 2: применение уже проверенных строк.
            foreach ($prepared as $entry) {
                $item = $entry['item'];
                $code = $entry['code'];

                if (isset($entry['parts'])) {
                    // Расход по свойствам: списание и пополнение идут одной
                    // арифметикой, отличается только знак.
                    $appliedRows = array_merge(
                        $appliedRows,
                        $this->applyParts($item, $entry['parts'], $type, $code)
                    );

                    continue;
                }

                $delta = $entry['delta'];

                // Один предмет может встретиться в операции несколько раз —
                // при списании по коду это обычное дело: отсканировали два
                // кода одного товара. У каждой строки свой экземпляр модели, и
                // без обновления обе видели бы исходный остаток: в базе
                // списалось две единицы, а в журнале легли бы две строки
                // «было 5 → стало 4». decrement() обновляет базу, а вот
                // значение в модели — нет.
                $item->refresh();

                // Списание по коду: код, по которому пришла операция,
                // высвобождается и запоминается строкой операции, чтобы
                // откат сумел вернуть его предмету.
                $releasedCode = $type === 'operation.writeoff'
                    ? $this->releasedCodes->releaseFor($item, $code)
                    : null;

                if ($item->quantity === null) {
                    // Предмет без количественного учёта (единичный экземпляр):
                    // числится как «один в наличии», после операции переводится
                    // в учитываемое количество.
                    $before = 1;

                    if ($type === 'operation.replenish') {
                        $after = $before + $delta;
                        $item->update(['quantity' => $after]);
                    } else {
                        $after = $before - $delta;
                        $item->update(['quantity' => $after]);
                    }

                    $appliedRows[] = [
                        'code'     => $code,
                        'item_id'  => $item->id,
                        'title'    => $item->title,
                        'delta'    => $delta,
                        'before'   => $before,
                        'after'    => $after,
                        'owner_id' => $item->user_id,
                        'released_code_id' => $releasedCode?->id,
                    ];

                    continue;
                }

                $before = (int) $item->quantity;

                if ($type === 'operation.replenish') {
                    $item->increment('quantity', $delta);
                    $after = $before + $delta;
                } else {
                    $item->decrement('quantity', $delta);
                    $after = $before - $delta;
                }

                $appliedRows[] = [
                    'code'     => $code,
                    'item_id'  => $item->id,
                    'title'    => $item->title,
                    'delta'    => $delta,
                    'before'   => $before,
                    'after'    => $after,
                    'owner_id' => $item->user_id,
                    'released_code_id' => $releasedCode?->id,
                ];
            }
    }

    /**
     * Запись операции в журнал движений и ответ на неё.
     *
     * @param  array<int, mixed>  $appliedRows
     */
    /**
     * Что пишется в журнал действий по строке операции.
     *
     * @param  array<string, mixed>  $appliedRow
     * @return array<string, mixed>
     */
    private function journalPayload(array $appliedRow, StockOperation $operation): array
    {
        return [
            'code'   => $appliedRow['code'],
            'title'  => $appliedRow['title'],

            // В журнале дельта знаковая: списание — отрицательная.
            'delta'  => $appliedRow['after'] - $appliedRow['before'],
            'before' => $appliedRow['before'],
            'after'  => $appliedRow['after'],

            // Расход по свойству. Без него запись о списании 300 мл выглядела бы
            // как «было 2, стало 2»: количество штук у бутылки не изменилось,
            // а расход был.
            'property_id'     => $appliedRow['property_id'] ?? null,
            'property_title'  => $appliedRow['property_title'] ?? null,
            'amount'          => $appliedRow['amount'] ?? null,
            'property_before' => $appliedRow['property_before'] ?? null,
            'property_after'  => $appliedRow['property_after'] ?? null,

            // Комментарий и номер операции: в журнале действий списание и
            // пополнение остаются отдельными записями, а объяснение «куда
            // списали» хранится один раз — в самой операции.
            'operation_id' => $operation->id,
            'comment'      => $operation->comment,
        ];
    }

    /**
     * Ответ на применённую операцию.
     *
     * @param  array<int, mixed>  $appliedRows
     */
    private function answer(string $type, array $payload, array $appliedRows, StockOperation $operation): JsonResponse
    {
        return response()->json([
            'type'       => $type,
            'comment'    => $operation->comment,
            'operation'  => (new StockOperationResource($operation->load('rows.releasedCode')))->resolve(),
            'payload'    => $payload,
            'rows'       => $appliedRows,
        ]);
    }

    /**
     * Расход по свойствам строки операции: приводит к виду, который уже можно
     * применять, либо пишет в $errors и возвращает null.
     *
     * Проверяется здесь, а не в контроллере запроса, три вещи, которые про
     * формат запроса не узнать: свойство действительно расходуется у этого
     * предмета, расход положителен, и одно свойство не указано дважды.
     * Последнее важно для отката: две строки по одному свойству в одной
     * операции вернулись бы двумя шагами и разъехались по остаткам.
     *
     * @param  \Illuminate\Support\Collection<int, ItemPartialProperty>  $settings
     * @param  array<int, string>  $errors
     * @return array<int, array{property_id: int, amount: float}>|null
     */
    private function normalizeParts(array $row, Item $item, $settings, array &$errors): ?array
    {
        $raw = $row['parts'] ?? null;

        if (! is_array($raw) || $raw === []) {
            $errors[] = sprintf(
                'Предмет "%s" расходуется частями — укажите, сколько списать по каждому свойству',
                $item->title
            );

            return null;
        }

        $known = $settings->pluck('property_id')->map(fn ($id) => (int) $id)->all();
        $parts = [];
        $seen = [];

        foreach ($raw as $part) {
            $propertyId = (int) ($part['property_id'] ?? 0);
            $amount = (float) ($part['amount'] ?? 0);

            if (! in_array($propertyId, $known, true)) {
                $errors[] = sprintf(
                    'У предмета "%s" свойство с id %d не расходуется частями',
                    $item->title,
                    $propertyId
                );

                return null;
            }

            if (isset($seen[$propertyId])) {
                $errors[] = sprintf(
                    'Свойство с id %d указано дважды — объедините расход в одну сумму',
                    $propertyId
                );

                return null;
            }

            if ($amount <= 0) {
                $errors[] = sprintf(
                    'Расход по свойству с id %d должен быть больше нуля',
                    $propertyId
                );

                return null;
            }

            $seen[$propertyId] = true;
            $parts[] = ['property_id' => $propertyId, 'amount' => $amount];
        }

        return $parts;
    }

    /**
     * Применяет расход по свойствам и возвращает строки для журнала.
     *
     * Строк в журнале может оказаться больше, чем свойств в строке операции:
     * кроме расхода по свойству сюда попадает целая штука, если она при этом
     * списалась. Откат возвращает их независимо друг от друга — вернуть
     * частичный расход, не вернув штуку, законно: бутылку могли досахать
     * добрать и положить обратно нетронутой.
     *
     * @param  array<int, array{property_id: int, amount: float}>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function applyParts(Item $item, array $parts, string $type, string $code): array
    {
        $settings = $this->partialWriteoff->settings($item);
        $isWriteoff = $type === 'operation.writeoff';
        $rows = [];

        foreach ($parts as $part) {
            $propertyId = (int) $part['property_id'];
            $amount = (float) $part['amount'];

            $item->refresh();

            $before = (int) ($item->quantity ?? 0);
            $norm = $this->partialWriteoff->norm($item, $propertyId);
            $propertyBefore = $norm === null
                ? 0.0
                : $this->partialWriteoff->total($item, $propertyId, $norm);

            $result = $isWriteoff
                ? $this->partialWriteoff->spend($item, $propertyId, $amount, $settings)
                : $this->partialWriteoff->replenish($item, $propertyId, $amount, $settings);

            $this->partialWriteoff->persist($item, $result['remainders'], (int) $result['quantity']);

            $item->refresh();

            $rows[] = [
                'code'            => $code,
                'item_id'         => $item->id,
                'title'           => $item->title,
                'owner_id'        => $item->user_id,
                'property_id'     => $propertyId,
                'property_title'  => $this->propertyTitle($propertyId),
                'amount'          => $amount,
                'property_before' => $propertyBefore,
                'property_after'  => $this->partialWriteoff->total($item, $propertyId, (float) $norm),
                'is_writeoff'     => $isWriteoff,
                'delta'           => (int) $item->quantity - $before,
                'before'          => $before,
                'after'           => (int) $item->quantity,
            ];
        }

        return $rows;
    }

    /** Название свойства для журнала, один раз на операцию при выводе. */
    private function propertyTitle(int $propertyId): string
    {
        return Property::find($propertyId)?->title ?? (string) $propertyId;
    }

    /**
     * Разрешение кода: первое store-совпадение (скан кода хранилища) и
     * коллекция предметов, дедуплицированная по item_id, в порядке
     * «свои сначала, новые выше» (см. Code::scopeMatchesFor).
     *
     * @return array{0: ?Code, 1: \Illuminate\Support\Collection<int, Code>}
     */
    private function resolveCode(string $code): array
    {
        $codes = Code::with(['store', 'item'])
            ->whereIn('code', $this->codeCandidates($code))
            ->matchesFor((int) CurrentUser::id())
            ->get();

        $storeMatch = $codes->first(fn (Code $row) => $row->store_id !== null);

        $items = collect();

        foreach ($codes as $row) {
            if ($row->item_id === null || $row->item === null) {
                continue;
            }

            if (!$items->has($row->item_id)) {
                $items->put($row->item_id, $row);
            }
        }

        return [$storeMatch, $items];
    }

    private function codeCandidates(string $code): array
    {
        return CodeFormat::candidates($code);
    }
}
