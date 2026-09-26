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
    ) {}

    public function index(Request $request): ResourceCollection
    {
        // Значения свойств здесь не подгружаются: список предметов показывает
        // заголовки, а подтягивание ещё одной строки на каждое заполненное
        // поле на страницу в 15 предметов стоило бы дороже, чем весь остальной
        // список. В карточке предмета значения уже есть.
        $query = Item::with(['code', 'store.parent', 'category', 'images', 'user']);

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

        return ItemResource::collection(
            $query->orderByDesc('id')->paginate($request->integer('per_page', self::PER_PAGE))
        );
    }

    public function get(Item $model): ItemResource
    {
        return new ItemResource($model->load($this->relations()));
    }

    public function post(StoreItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        abort_unless($this->canCreateItem($data), 403, 'Нет права на создание в этом складе');

        $item = DB::transaction(function () use ($data) {
            $codes = $this->normalizeCodes($data);
            $properties = $this->normalizeProperties($data);
            unset($data['code'], $data['codes'], $data['properties']);

            $item = Item::create($data);

            $this->syncCodes($item, $codes ?? []);

            if ($properties !== null) {
                $this->propertyService->sync($item, $properties);
            }

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
            unset($data['code'], $data['codes'], $data['properties']);

            $model->update($data);

            if ($properties !== null) {
                $this->propertyService->sync($model, $properties);
            }

            if ($codes === null) {
                // Раздела codes в теле нет — коды не трогаем
                return;
            }

            $this->syncCodes($model, $codes);
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
     * Всё, что показывает ресурс предмета, одной строкой — иначе список,
     * карточка и ответ на сохранение расходились бы по составу полей, и
     * интерфейс ловил бы «поле пропало» там, где оно просто не грузилось.
     *
     * @return string[]
     */
    private function relations(): array
    {
        return ['code', 'codes', 'store.parent', 'category', 'vendor', 'images', 'user', 'propertyValues.property.unit', 'propertyValues.dictionaryValue'];
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
        if ($codes === []) {
            $codes = [(string) Str::uuid7()];
        }

        foreach (array_values($codes) as $index => $code) {
            // Код хранилища не может быть переиспользован предметом: скан
            // такого кода должен приводить к хранилищу, а не к предмету.
            if (Code::where('code', $code)->whereNotNull('store_id')->exists()) {
                throw ValidationException::withMessages([
                    "codes.{$index}" => ['Код уже привязан к хранилищу'],
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
