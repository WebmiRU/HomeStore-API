<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Category;
use App\Models\Code;
use App\Models\Item;
use App\Models\Store;
use App\Services\AccessService;
use App\Services\CodedQuantity;
use App\Services\PartialWriteoff;
use App\Services\ItemPropertyService;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    /**
     * Строк на страницу списка предметов.
     *
     * Раньше бралось умолчание Laravel — 15. Строки стали выше: в списке есть
     * колонка с картинкой, и пятнадцать таких на экран не помещаются, а
     * половина страницы уезжает за край. Десять — это сколько влезает вместе
     * с заголовком и фильтром, без прокрутки.
     */
    private const PER_PAGE = 10;

    public function __construct(
        private readonly ItemPropertyService $propertyService,
        private readonly CodedQuantity $codedQuantity,
        private readonly PartialWriteoff $partialWriteoff,
        private readonly \App\Services\ImageAttach $images,
        private readonly \App\Services\InitialStock $initialStock,
        private readonly \App\Services\AuditLogService $logs,
    ) {}

    public function index(Request $request): ResourceCollection
    {
        // Значения свойств здесь не подгружаются: список предметов показывает
        // заголовки, а подтягивание ещё одной строки на каждое заполненное
        // поле на страницу в 15 предметов стоило бы дороже, чем весь остальной
        // список. В карточке предмета значения уже есть.
        // Поставщик, в отличие от свойств, подгружается: колонка
        // «Производитель» в списке показывает его названием и ссылкой на
        // карточку, а не только идентификатором.
        $query = Item::with(['code', 'store.parent', 'category', 'vendor', 'images', 'user']);

        // Фильтр по категории вместе с её вложенными. Своё условие — в
        // скобках: скоуп AccessibleByUser добавляет orWhere, и приписанный
        // после него and без скобок переписал бы смысл на «доступные по
        // складу подходят всегда», то есть фильтр просто не применился бы.
        if ($request->filled('category_id')) {
            $categoryId = $request->integer('category_id');

            $query->where(function (Builder $builder) use ($categoryId): void {
                $builder->where('category_id', $categoryId)
                    ->orWhereIn('category_id', Category::find($categoryId)?->descendantIds() ?? []);
            });
        }

        // Фильтр по производителю. В отличие от категории, у производителя нет
        // вложенности: один уровень, и всё, что помечено им, и есть его
        // предметы.
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->integer('vendor_id'));
        }

        $paged = $query->orderByDesc('id')->paginate($request->integer('per_page', self::PER_PAGE));

        // Остатки расходуемых свойств для всей страницы сразу: по одному на
        // предмет вышло бы три запроса на строку списка, а колонка
        // «Количество» у такого предмета показывает и штуки, и запас по
        // свойствам. Считается здесь, потому что ресурс без подготовленных
        // данных сошёл бы в ту же порчу на каждом предмете по отдельности.
        $totals = app(\App\Services\PartialWriteoff::class)
            ->totalsForItems($paged->getCollection()->modelKeys());

        foreach ($paged->getCollection() as $item) {
            $item->setRelation('partialStock', $totals[$item->id] ?? []);
        }

        return ItemResource::collection($paged);
    }

    public function get(Item $model): ItemResource
    {
        return new ItemResource($model->load($this->relations()));
    }

    public function post(StoreItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        abort_unless($this->canCreateItem($data), 403, __('Нет права на создание в этом складе'));

        $item = DB::transaction(function () use ($data) {
            $codes = $this->normalizeCodes($data);
            $properties = $this->normalizeProperties($data);
            $partial = $this->normalizePartialProperties($data);
            unset($data['code'], $data['codes'], $data['properties'], $data['partial_properties']);

            // Количество помеченного предмета считается по кодам, а не по
            // присланному с формы числу: клиент поле всё равно отправляет.
            if ($data['release_code_on_writeoff'] ?? false) {
                unset($data['quantity']);
            }

            $item = Item::create($data);

            $this->syncCodes($item, $codes ?? []);
            $this->codedQuantity->refreshAfterSync($item, $codes ?? []);

            if ($properties !== null) {
                $this->propertyService->sync($item, $properties);
            }

            // Настройки расхода — после значений свойств: сверка остатков с
            // нормой имеет смысл, когда норма уже записана.
            if ($partial !== null) {
                $this->partialWriteoff->syncSettings($item, $partial);
            }

            // Картинки грузились заранее, пока предмета ещё не было, и ждали
            // его id. Привязка — в той же транзакции: предмет без части своих
            // фотографий получился бы после ошибки, и человек увидел бы
            // неполную карточку без всяких объяснений.
            $this->images->attachTo($item, $data['images'] ?? []);

            // Количество из карточки — это появление товара на складе, и оно
            // записывается приходом. Пока оно было просто полем в таблице, о
            // нём не оставалось следа: ни стартовой точки на графике остатков,
            // ни строки в движениях предмета.
            $this->initialStock->recordFor($item, $item->quantity);

            return $item;
        });

        return (new ItemResource($this->loadForAnswer($item)))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateItemRequest $request, Item $model): ItemResource
    {
        abort_unless(app(AccessService::class)->canEdit($model), 403);

        DB::transaction(function () use ($request, $model) {
            $data = $request->validated();
            $codes = $this->normalizeCodes($data);
            $properties = $this->normalizeProperties($data);
            $partial = $this->normalizePartialProperties($data);
            // Решение по норме нужно только на время сохранения, поэтому
            // снимается вместе с прочими служебными полями — но сначала
            // читается, иначе к моменту проверки его уже не будет.
            $normDecisions = (array) ($data['partial_norms'] ?? []);
            $actualBalance = (array) ($data['partial_actual'] ?? []);

            unset(
                $data['code'],
                $data['codes'],
                $data['properties'],
                $data['partial_properties'],
                $data['images'],
                $data['partial_norms'],
                $data['partial_actual'],
            );

            // Количество помеченного предмета сервер считает сам по кодам.
            // Снимаем присланное ДО update: иначе оно записалось бы в базу и
            // осталось бы там, если бы коды в запросе не пришли вовсе и
            // пересчёт по ним не запустился.
            $data = $this->codedQuantity->stripQuantity($model, $data);

            /*
             * Количество у расходуемого предмета не правится: оно считается из
             * остатка свойств, и присланное число сервер всё равно пересчитал
             * бы по-своему — тихо и мимо формы. Раньше оно просто записывалось
             * в базу, откуда и брались расхождения вида «2 штуки и 1800 мл».
             */
            $isPartial = $this->partialWriteoff->settings($model)->isNotEmpty();
            $before = $model->quantity === null ? 1 : (int) $model->quantity;
            $after = $before;

            if (array_key_exists('quantity', $data)) {
                $after = $data['quantity'] === null ? 1 : (int) $data['quantity'];

                if ($isPartial) {
                    if ($after !== $before) {
                        throw ValidationException::withMessages([
                            'quantity' => [__('У предмета, который расходуется частями, количество считается по остатку свойств')],
                        ]);
                    }

                    unset($data['quantity']);
                }
            }

            // Смена нормы расходуемого свойства — не молчаливое дело: объём
            // остаётся прежним, а меняется либо число штук, либо сам запас.
            // Что именно — угадать нельзя (ошибка ввода или предыдущая ошибка в
            // учёте), поэтому решение приходит сверху, и без него сохранение
            // отклоняется.
            if ($properties !== null) {
                $properties = $this->applyNormDecisions($model, $properties, $normDecisions, $actualBalance);
            }

            $model->update($data);

            // Правка количества — движение остатка: было 3, стало 5, значит
            // пришли две штуки. Без операции в журнале осталась бы правка
            // карточки, неотличимая от списания.
            if ($after !== $before) {
                $this->initialStock->recordQuantityChange($model, $before, $after);
            }

            if ($properties !== null) {
                $this->propertyService->sync($model, $properties);
            }

            // Фактические числа применяются после записи новых норм: остаток
            // задаётся в единицах измерения, и старая норма к нему отношения не
            // имеет.
            if ($normDecisions !== []) {
                $this->applyActualBalance($model, $normDecisions, $actualBalance);

                // Модель в памяти держит значения, какими они были до правки:
                // норма выведена из факта, и человек ввёл 10 000, а в базу легло
                // 200. Ответ собирается из модели, поэтому без перечитывания
                // форма после сохранения показала бы введённое и человек решил
                // бы, что норма не применилась.
                $model->refresh();
                $model->unsetRelation('propertyValues');
            }

            if ($partial !== null) {
                $this->partialWriteoff->syncSettings($model, $partial);
            }

            if ($codes === null) {
                // Раздела codes в теле нет — коды не трогаем. Количество у
                // помеченного предмета всё равно сверяется с тем, что кодов
                // у него сейчас: пометку могли только что снять, а снятая
                // пометка означает, что количество снова вручную.
                $this->codedQuantity->refresh($model);

                return;
            }

            $this->syncCodes($model, $codes);
            $this->codedQuantity->refreshAfterSync($model, $codes);
        });

        return new ItemResource($this->loadForAnswer($model));
    }

    /**
     * Модель для ответа: обычные связи плюс список коллизий по кодам.
     *
     * Коллизии считаются только в ответе на сохранение, а не в relations():
     * карточка предмета и список предметов коллизий не показывают, и
     * лишний запрос на каждом чтении списка там просто не нужен.
     */
    private function loadForAnswer(Item $item): Item
    {
        $item->load($this->relations());
        $item->setRelation('conflicts', Code::conflictsFor($item));

        return $item;
    }

    /**
     * Значения свойств из тела запроса либо null, когда раздела properties
     * в теле нет вовсе.
     *
     * null — это «не трогать», а пустой массив — «очистить все значения».
     * Различать приходится потому, что PUT шлёт только изменённые поля, и
     * трактовка «нет ключа» как «пусто» стирала бы чужие заполненные поля при
     * любом частичном обновлении.
     *
     * @param  array<string, mixed>  $data  результат $request->validated()
     */
    private function normalizeProperties(array $data): ?array
    {
        if (! array_key_exists('properties', $data)) {
            return null;
        }

        return $this->propertyService->normalize($data['properties'] ?? []);
    }

    /**
     * Настройки частичного списания из тела запроса либо null, когда раздела
     * partial_properties в теле нет вовсе.
     *
     * Как и у значений свойств: null — «не трогать», пустой массив — «расход
     * частями выключить и почистить настройки».
     *
     * @param  array<string, mixed>  $data
     */
    private function normalizePartialProperties(array $data): ?array
    {
        if (! array_key_exists('partial_properties', $data)) {
            return null;
        }

        return array_values((array) ($data['partial_properties'] ?? []));
    }

    /**
     * Всё, что показывает ресурс предмета, одной строкой — иначе список,
     * карточка и ответ на сохранение расходились бы по составу полей, и
     * интерфейс ловил бы «поле пропало» там, где оно просто не грузилось.
     *
     * @return string[]
     */
    private function relations(): array
    {
        return ['code', 'codes', 'store.parent', 'category', 'vendor', 'images', 'user', 'propertyValues.property.unit', 'propertyValues.dictionaryValue', 'partialWriteoffProperties'];
    }

    private function canCreateItem(array $data): bool
    {
        if (empty($data['store_id'])) {
            return true;
        }

        $store = Store::find((int) $data['store_id']);

        if ($store === null) {
            return false;
        }

        if ($store->warehouse_id === null) {
            return true;
        }

        return $store->warehouse !== null && app(AccessService::class)->canCreate($store->warehouse);
    }

    /**
     * Коды из тела запроса — список строк без пустых и повторов.
     *
     * null — это «не трогать», а пустой массив — «очистить». Различать
     * приходится потому, что PUT шлёт только изменённые поля, и пустой
     * список в теле без ключа codes стёр бы коды при любом частичном
     * обновлении.
     *
     * Поле code — прежнее имя одного кода, оставлено для клиентов, которые
     * ещё шлют его вместо codes.
     *
     * @param  array<string, mixed>  $data  результат $request->validated()
     * @return list<string>|null
     */
    /**
     * Пересчёт остатков там, где у расходуемого свойства поменялось значение.
     *
     * Возвращает значения свойств, какими их надо записать: при «своих
     * числах» человек указал факт (сколько штук и сколько всего), и норма
     * выводится из него, а не остаётся введённой. Иначе введённые 10 000 мл
     * остались бы нормой при 200 бутылках по 200 мл, и остаток разошёлся бы с
     * реальностью на два порядка.
     *
     * @param  array<int, mixed>  $properties  нормализованные значения свойств
     * @param  array<int, string>  $decisions  выбор человека: recalculate | keep | custom
     * @param  array<string, mixed>  $actual  фактические числа при custom
     * @return array<int, mixed>
     */
    private function applyNormDecisions(Item $model, array $properties, array $decisions, array $actual = []): array
    {
        $settings = $this->partialWriteoff->settings($model);

        if ($settings->isEmpty()) {
            return $properties;
        }

        $incoming = [];

        foreach ($properties as $property) {
            $text = trim((string) ($property['value'] ?? ''));

            if ($text === '') {
                continue;
            }

            $incoming[(int) $property['property_id']] = (float) $text;
        }

        /*
         * Вписанные фактические числа проверяются всегда, даже когда норма не
         * менялась: человек может прийти и с одной только корректировкой
         * («на самом деле 5 штук и 4 000 мл»), и раньше это молча записывалось
         * как остаток 3 600 в текущей штуке — то есть система согласилась на
         * число, которое не помещается в заявленные штуки.
         */
        foreach ((array) ($actual['properties'] ?? []) as $propertyId => $total) {
            $propertyId = (int) $propertyId;
            $norm = $incoming[$propertyId] ?? $this->partialWriteoff->norm($model, $propertyId);

            if ($norm === null) {
                continue;
            }

            $this->assertFits($propertyId, (float) $norm, (float) $total, (int) ($actual['quantity'] ?? 0));
        }

        $pending = [];
        $needed = [];

        foreach ($settings as $setting) {
            $propertyId = (int) $setting->property_id;
            $newNorm = $incoming[$propertyId] ?? null;
            $oldNorm = $this->partialWriteoff->norm($model, $propertyId);

            if ($newNorm === null || $newNorm <= 0 || $oldNorm === null) {
                continue;
            }

            if (abs($newNorm - $oldNorm) < 0.0001) {
                continue;
            }

            // Пока остаток нулевой, менять нечего: пересчитывать нечего, и
            // решение человека о штуках не понадобится.
            if ((int) ($model->quantity ?? 1) <= 0) {
                continue;
            }

            $mode = $decisions[$propertyId] ?? null;

            // custom: человек вписал факт — сколько штук и сколько всего, — и
            // норма выводится из него. Нужен ровно тогда, когда оба готовых
            // варианта непригодны: человек ошибся в нормах (200 бутылок по
            // 200 мл вместо 100 по 100), и никакой расчёт от чужой нормы его не
            // спасёт.
            if ($mode === 'custom') {
                // Норма остаётся введённой: её и правит человек, ошибся он или
                // нет. Проверяется только согласованность — влезает ли в
                // вписанные штуки вписанный запас. Норму из запаса не выводим:
                // при 5 штуках и 4 500 на руках вывелось бы «по 900», а человек
                // вводил 1 000 и одну неполную штуку — это разные вещи.
                $this->assertFits($propertyId, $newNorm, (float) ($actual['properties'][$propertyId] ?? 0), (int) ($actual['quantity'] ?? 0));
                $pending[$propertyId] = ['norm' => $newNorm, 'mode' => 'custom'];
                continue;
            }

            if (! in_array($mode, ['recalculate', 'keep'], true)) {
                // Список свойств, а не текст: клиент показывает человеку обе
                // величины — старую и новую — и они у него уже есть, из формы.
                // Собирать их обратно из строки сообщения значило бы разбирать
                // текст, который человек видел на экране.
                $needed[] = [
                    'property_id' => $propertyId,
                    'from'        => $oldNorm,
                    'to'          => $newNorm,
                    'current'     => $this->partialWriteoff->total($model, $propertyId, $oldNorm),
                ];

                continue;
            }

            $pending[$propertyId] = ['norm' => $newNorm, 'mode' => $mode];
        }

        if ($needed !== []) {
            /*
             * Оба варианта считаются на весь набор сразу, а не по одному
             * свойству: число штук у предмета одно, и оно равно большему из
             * отмеченных. Пересчитать «Объём» отдельно от «Вес» значило бы
             * показать человеку два несовместимых будущих и предложить выбрать
             * между величинами, которых одновременно не бывает.
             */
            $changes = [];

            foreach ($needed as $row) {
                $changes[(int) $row['property_id']] = (float) $row['to'];
            }

            $outcomes = [];

            foreach (['recalculate', 'keep'] as $mode) {
                $preview = $this->partialWriteoff->previewNormChange($model, $changes, $mode);

                $outcomes[$mode] = [
                    'quantity' => $preview['quantity'],
                    // Признак, что остаток в выбранные штуки помещается. При
                    // «штуки прежние» это может быть не так, и тогда вариант
                    // непригоден: он раздул бы запас молча.
                    'fits'     => (bool) ($preview['properties'][array_key_first($changes)]['fits'] ?? true),
                    'properties' => array_map(
                        fn (int $propertyId): array => $preview['properties'][$propertyId] ?? ['stock' => 0.0, 'remaining' => 0.0, 'fits' => true],
                        array_keys($changes),
                    ),
                ];
            }

            foreach ($needed as $index => $row) {
                $needed[$index]['outcomes'] = [
                    'recalculate' => [
                        'quantity'  => $outcomes['recalculate']['quantity'],
                        'stock'     => $outcomes['recalculate']['properties'][$index]['stock'] ?? 0.0,
                        'remaining' => $outcomes['recalculate']['properties'][$index]['remaining'] ?? 0.0,
                        'fits'      => $outcomes['recalculate']['fits'],
                    ],
                    'keep' => [
                        'quantity'  => $outcomes['keep']['quantity'],
                        'stock'     => $outcomes['keep']['properties'][$index]['stock'] ?? 0.0,
                        'remaining' => $outcomes['keep']['properties'][$index]['remaining'] ?? 0.0,
                        'fits'      => $outcomes['keep']['fits'],
                    ],
                ];
            }

            throw ValidationException::withMessages([
                'partial_norms' => [sprintf(
                    __('Норма изменилась: %s — укажите, пересчитать штуки или оставить'),
                    implode(', ', array_map(
                        fn (array $row): string => sprintf('%s %s → %s', $this->propertyTitle((int) $row['property_id']), $this->formatAmount((float) $row['from']), $this->formatAmount((float) $row['to'])),
                        $needed
                    ))
                )],
                'partial_norms_rows' => $needed,
            ]);
        }

        // Значения свойств переписываются нормой, выведенной из факта: дальше
        // их записывает обычная синхронизация, отдельного пути не нужно.
        foreach ($incoming as $propertyId => $norm) {
            foreach ($properties as $index => $property) {
                if ((int) ($property['property_id'] ?? 0) === $propertyId) {
                    $properties[$index]['value'] = $this->formatAmount($norm);
                }
            }
        }

        // Порядок важен: решение принимается по старым нормам, поэтому все
        // пересчёты идут до записи новых значений свойств. При «своих числах»
        // пересчёта нет — остатки ставятся фактические.
        foreach ($pending as $propertyId => $decision) {
            if ($decision['mode'] === 'custom') {
                continue;
            }

            $oldNorm = $this->partialWriteoff->norm($model, $propertyId);
            $result = $this->partialWriteoff->applyNormChange($model, $propertyId, $decision['norm'], $decision['mode']);

            /*
             * Смена нормы меняет запас, а операции за ней не стоит: никто не
             * принёс и не унёс. Без записи на графике остатков был бы скачок
             * без причины — ровно то, ради чего мы и переводили график на
             * операции.
             */
            $this->logs->record(
                \App\Enums\AuditAction::ItemUpdated,
                'item_id',
                (int) $model->id,
                (int) $model->user_id,
                [
                    'changes' => [
                        'property_norm:' . $this->propertyTitle($propertyId) => [
                            $this->formatAmount((float) $oldNorm),
                            $this->formatAmount((float) $decision['norm']),
                        ],
                    ],
                    'norm_change' => [
                        'property_id' => $propertyId,
                        'property_title' => $this->propertyTitle($propertyId),
                        'from' => (float) $oldNorm,
                        'to' => (float) $decision['norm'],
                        'mode' => $decision['mode'],
                        'stock' => (float) $result['stock'],
                        'quantity' => (int) $result['quantity'],
                    ],
                ],
            );
        }

        return $properties;
    }

    /**
     * Остаток и число штук, вписанные человеком, — после смены норм.
     *
     * Единственный способ разойтись с остатком по-настоящему: вписать и штуки,
     * и содержимое и получить несовместимую пару — 5 штук по 10 000 мл при
     * 4 500 на руках. Проверяем это здесь и отказываем с текстом, в котором
     * сказано, чего именно не хватает: молчание читалось бы как поломка
     * сохранения.
     *
     * @param  array<int, string>  $decisions  режим по свойствам: recalculate | keep | custom
     * @param  array<string, mixed>  $actual  фактические числа: quantity и properties
     */
    private function applyActualBalance(Item $model, array $decisions, array $actual): void
    {
        if (! in_array('custom', $decisions, true)) {
            return;
        }

        $model->refresh();
        $settings = $this->partialWriteoff->settings($model);
        $quantity = max(0, (int) ($actual['quantity'] ?? 0));
        $remainders = [];

        foreach ((array) ($actual['properties'] ?? []) as $propertyId => $value) {
            $propertyId = (int) $propertyId;
            // Норма к этому моменту уже выведена из факта и записана
            // синхронизацией свойств, поэтому она здесь своя, а не прежняя.
            $norm = (float) $this->partialWriteoff->norm($model, $propertyId);

            if ($norm <= 0) {
                throw ValidationException::withMessages([
                    'partial_actual' => [sprintf(
                        __('«%s»: вписано %s, а штук нет — столько на руках быть не может'),
                        $this->propertyTitle($propertyId),
                        $this->formatAmount((float) $value)
                    )],
                ]);
            }

            $total = round((float) $value, 3);

            if ($total < 0) {
                throw ValidationException::withMessages([
                    'partial_actual' => [sprintf(__('«%s»: остаток не может быть отрицательным'), $this->propertyTitle($propertyId))],
                ]);
            }

            /*
             * Остаток внутри текущей штуки: из полного запаса вычитается всё,
             * что в целых штуках. Отрицательный означает, что штук введено
             * больше, чем товара на руках, — это противоречие, и тихо принять
             * его значило бы записать в карточку чушь.
             */
            $remaining = $total - ($quantity - 1) * $norm;

            if ($quantity > 0 && $remaining < -PartialWriteoff::TOLERANCE) {
                throw ValidationException::withMessages([
                    'partial_actual' => [sprintf(
                        __('«%s»: %s не помещаются в %d шт по %s — штук слишком много или запаса слишком мало'),
                        $this->propertyTitle($propertyId),
                        $this->formatAmount($total),
                        $quantity,
                        $this->formatAmount($norm)
                    )],
                ]);
            }

            $remainders[$propertyId] = max(0.0, $remaining);
        }

        if ($remainders === []) {
            throw ValidationException::withMessages([
                'partial_actual' => [__('Впишите, сколько всего осталось на руках')],
            ]);
        }

        /*
         * Штук должно быть ровно столько, сколько набирается из запаса.
         *
         * Больше — значит лишние пустые: 50 000 мл при норме 10 000 это ровно
         * пять полных бутылок, и шестая была бы пустой, то есть её не
         * существует. Меньше — товар не помещается. Отдельно проверяется
         * каждое отмеченное свойство, потому что штуку держит любое из них, и
         * одно может требовать больше штук, чем другое.
         */
        $held = 0;

        foreach ($remainders as $propertyId => $remaining) {
            $isReason = $settings->contains(
                fn ($row) => (int) $row->property_id === $propertyId && $row->is_full_reason
            );

            if (! $isReason) {
                continue;
            }

            $norm = (float) $this->partialWriteoff->norm($model, $propertyId);
            $total = ($quantity - 1) * $norm + $remaining;
            $held = max($held, (int) ceil($total / $norm - PartialWriteoff::TOLERANCE));
        }

        if ($quantity !== $held) {
            throw ValidationException::withMessages([
                'partial_actual' => [sprintf(
                    __('По вписанным остаткам получается %d шт, а указано %d — лишние штуки были бы пустыми'),
                    $held,
                    $quantity
                )],
            ]);
        }

        /*
         * Строки операции собираются ДО пересчёта: после persist остатки уже
         * другие, и в журнале записалось бы «было 0, стало 1800» — движение,
         * которого не было.
         */
        $before = (int) ($model->quantity ?? 1);
        $code = (string) ($model->codes()->orderBy('id')->value('code') ?? '');
        $rows = [];

        foreach ($remainders as $propertyId => $remaining) {
            $norm = (float) $this->partialWriteoff->norm($model, $propertyId);
            $stock = $quantity <= 0 ? 0.0 : ($quantity - 1) * $norm + $remaining;
            $oldStock = $before <= 0 ? 0.0 : ($before - 1) * $norm + min($remaining, $norm);

            $rows[] = [
                'code'            => $code,
                'item_id'         => $model->id,
                'owner_id'        => $model->user_id,
                'title'           => $model->title,
                'delta'           => 0,
                'before'          => $before,
                'after'           => $quantity,
                'property_id'     => $propertyId,
                'property_title'  => $this->propertyTitle($propertyId),
                'amount'          => abs($stock - $oldStock),
                'property_before' => $oldStock,
                'property_after'  => $stock,
                'is_writeoff'     => $stock < $oldStock,
            ];
        }

        $changed = $quantity !== $before || collect($rows)->contains(fn (array $row) => $row['amount'] > 0);

        $this->partialWriteoff->persist($model, $remainders, $quantity);

        if ($changed) {
            $this->initialStock->recordCorrection($model, $rows, __('Корректировка: смена нормы'));
        }
    }

    /**
     * Согласованность вписанных чисел: влезает ли запас в штуки.
     *
     * Норма здесь та, что человек ввёл в поле свойства, а «всего» — факт на
     * руках. Проверяем ровно одно: помещается ли этот запас в указанное число
     * штук при такой норме. Не помещается — значит одно из чисел не то, и
     * сказать об этом лучше до записи, чем записать противоречие.
     */
    private function assertFits(int $propertyId, float $norm, float $total, int $quantity): void
    {
        if ($norm <= 0) {
            return;
        }

        if ($quantity <= 0) {
            if ($total > 0) {
                throw ValidationException::withMessages([
                    'partial_actual' => [sprintf(
                        __('«%s»: указано %s, а штук нет — столько на руках быть не может'),
                        $this->propertyTitle($propertyId),
                        $this->formatAmount($total)
                    )],
                ]);
            }

            return;
        }

        /*
         * Сразу видно, во сколько штук укладывается запас. Проверку точного
         * совпадения делает applyActualBalance — там видно и то, что штуку
         * держит другое свойство, — а здесь ловим только грубое: меньше
         * заявленного товар тем более не поместится.
         */
        $fits = (int) ceil(max(0.0, $total) / $norm - PartialWriteoff::TOLERANCE);

        if ($quantity < $fits) {
            throw ValidationException::withMessages([
                'partial_actual' => [sprintf(
                    __('«%s»: %s — это %d шт, а указано %d'),
                    $this->propertyTitle($propertyId),
                    $this->formatAmount($total),
                    $fits,
                    $quantity
                )],
            ]);
        }

        $remaining = $total - ($quantity - 1) * $norm;

        /*
         * Остаток текущей штуки обязан лежать в пределах [0, нормы]. Меньше нуля
         * значит, что штук вписано больше, чем товара; больше нормы — что
         * товара больше, чем в них помещается. Обе стороны — то же самое: одно
         * из чисел лишнее. Ровно норма — не ошибка: это просто текущая штука,
         * которая ещё не тронута, то есть 5 полных по 10 000 это 50 000.
         */
        if ($remaining < -PartialWriteoff::TOLERANCE || $remaining > $norm + PartialWriteoff::TOLERANCE) {
            throw ValidationException::withMessages([
                'partial_actual' => [sprintf(
                    __('«%s»: в %d шт по %s помещается %s, а указано %s — проверьте штуки или остаток'),
                    $this->propertyTitle($propertyId),
                    $quantity,
                    $this->formatAmount($norm),
                    $this->formatAmount(max(0.0, $quantity * $norm)),
                    $this->formatAmount($total)
                )],
            ]);
        }
    }

    private function propertyTitle(int $propertyId): string
    {
        return \App\Models\Property::find($propertyId)?->title ?? (string) $propertyId;
    }

    private function formatAmount(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ''), '0'), ',');
    }

    private function normalizeCodes(array $data): ?array
    {
        $raw = match (true) {
            array_key_exists('codes', $data) => (array) $data['codes'],
            array_key_exists('code', $data) => [$data['code']],
            default => null,
        };

        if ($raw === null) {
            return null;
        }

        $codes = [];

        foreach ($raw as $value) {
            $code = trim((string) ($value ?? ''));

            // Повтор в одном списке — это один и тот же код дважды: строки
            // в БД иначе завелись бы одинаковыми, а предмет с двумя
            // одинаковыми кодами сканировался бы как неоднозначный сам у себя.
            if ($code === '' || in_array($code, $codes, true)) {
                continue;
            }

            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Приводит набор кодов предмета к заданному списку: недостающие
     * привязывает, лишние удаляет, всем проставляет sort по позиции.
     *
     * Пустой список не оставляет предмет вовсе без кода: от кода зависят
     * печать этикетки, карточка и разрешение скана, поэтому генерируется
     * новый UUID — ровно как при создании предмета без кода.
     *
     * @param  list<string>  $codes
     */
    private function syncCodes(Item $item, array $codes): void
    {
        if ($codes === [] && ! $item->release_code_on_writeoff) {
            // Обычному предмету код нужен всегда: по нему печатается этикетка,
            // он ищется сканером и открывается по нему из списка.
            //
            // Помеченному — нет: там код и есть единица, и ноль кодов значит
            // ноль единиц. Сгенерированный UUID посчитали бы за наклейку,
            // которой никто не клеил, и пустой предмет сразу стал бы непустым.
            $codes = [(string) Str::uuid7()];
        }

        foreach (array_values($codes) as $index => $code) {
            // Код хранилища не может быть переиспользован предметом: скан
            // такого кода должен приводить к хранилищу, а не к предмету.
            if (Code::where('code', $code)->whereNotNull('store_id')->exists()) {
                throw ValidationException::withMessages([
                    "codes.{$index}" => [__('Код уже привязан к хранилищу')],
                ]);
            }
        }

        $existing = $item->codes()->get();
        $used = [];

        foreach (array_values($codes) as $position => $code) {
            $row = $this->matchExisting($existing, $code, $used);

            if ($row === null) {
                $this->attachCode($item, $code, $position);
                continue;
            }

            $used[] = $row->id;

            // sort переписывается всегда, даже когда код остался на месте:
            // человек мог переставить коды в списке, и без этого верхний
            // на форме разошёлся бы с главным на сервере.
            if ($row->sort !== $position) {
                $row->update(['sort' => $position]);
            }
        }

        // Кода, который в списке больше нет, у предмета не остаётся: строка
        // удаляется, а не просто отвязывается. Иначе на печатной этикетке
        // остался бы код, который предмету не принадлежит, и он же вернулся
        // бы в чистку осиротевших — как код со снятого предмета.
        $existing->reject(fn (Code $row) => in_array($row->id, $used, true))->each->delete();
    }

    /**
     * Ищет среди кодов предмета строку с таким значением, ещё не занятую
     * другим пунктом списка.
     *
     * Повтор значения в списке возможен: уникальность code в БД не
     * гарантирована, и на разных предметах один штрихкод живёт в разных
     * строках. Поэтому «занята» отслеживается отдельно — иначе второй
     * такой же код в списке сочёлся бы за уже обработанный и потерял бы
     * строку, а с ней и своё место в порядке.
     *
     * @param  \Illuminate\Support\Collection<int, Code>  $existing
     * @param  list<int>  $used
     */
    private function matchExisting($existing, string $code, array $used): ?Code
    {
        return $existing->first(
            fn (Code $row) => $row->code === $code && ! in_array($row->id, $used, true)
        );
    }

    /**
     * Привязывает один код к предмету и ставит его на указанное место.
     *
     * Свободный код — например, напечатанный в наборе безымянных этикеток —
     * переиспользуем, а не плодим вторую строку с тем же значением:
     * уникальность code в БД не гарантирована, дубль разошёлся бы по
     * поиску и по набору. Связь с label_list сохраняется — код остаётся
     * частью своего набора.
     */
    private function attachCode(Item $item, string $code, int $sort): void
    {
        $free = Code::where('code', $code)
            ->whereNull('item_id')
            ->whereNull('store_id')
            ->orderByDesc('id')
            ->first();

        if ($free !== null) {
            $free->update(['item_id' => $item->id, 'sort' => $sort]);

            return;
        }

        Code::create(['code' => $code, 'item_id' => $item->id, 'sort' => $sort]);
    }

    public function delete(Item $model): JsonResponse
    {
        abort_unless(app(AccessService::class)->canDelete($model), 403);

        $model->delete();

        return response()->json(null, 204);
    }

    public function list()
    {
        $items = Item::with('code')->orderByDesc('id')->get();

        $items->transform(function (Item $item) {
            $uuid = strtoupper(str_replace('-', '', (string) $item->code?->code));

            $barcode = new Barcode();
            $bobj = $barcode->getBarcodeObj('DATAMATRIX', $uuid, 200, 200);
            $item->qrSvg = $bobj->getSvgCode();

            return $item;
        });

        return view('item.list', compact('items'));
    }
}
