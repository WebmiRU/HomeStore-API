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

        return ItemResource::collection($query->orderByDesc('id')->paginate());
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
            $code = isset($data['code']) ? trim((string) $data['code']) : '';
            $properties = $this->normalizeProperties($data);
            unset($data['code'], $data['properties']);

            $item = Item::create($data);

            if ($code === '') {
                // Код не передан — генерируем UUID по умолчанию
                Code::create([
                    'code'    => (string) Str::uuid7(),
                    'item_id' => $item->id,
                ]);
            } else {
                // Общий с put(): именно этот путь и есть «отсканировал
                // безымянную наклейку и завёл по ней предмет», а он ходит в
                // /items/create, то есть в POST, а не в PUT. Своя копия
                // логики здесь означала, что напечатанная строка кода
                // оставалась непривязанной, а рядом появлялась вторая с тем
                // же значением: та же этикетка попадала в чистку осиротевших,
                // а скан становился неоднозначным сразу после создания.
                $this->bindCodeToItem($item, $code);
            }

            if ($properties !== null) {
                $this->propertyService->sync($item, $properties);
            }

            return $item;
        });

        return (new ItemResource($item->load($this->relations())))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateItemRequest $request, Item $model): ItemResource
    {
        abort_unless(app(AccessService::class)->canEdit($model), 403);

        DB::transaction(function () use ($request, $model) {
            $data = $request->validated();
            $code = array_key_exists('code', $data) ? trim((string) ($data['code'] ?? '')) : null;
            $properties = $this->normalizeProperties($data);
            unset($data['code'], $data['properties']);

            $model->update($data);

            if ($properties !== null) {
                $this->propertyService->sync($model, $properties);
            }

            if ($code === null) {
                // Поле «code» не передано — связку не трогаем
                return;
            }

            if ($code === '') {
                // Отвязываем код от товара
                Code::where('item_id', $model->id)->delete();
                return;
            }

            $this->bindCodeToItem($model, $code);
        });

        return new ItemResource($model->load($this->relations()));
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
        return ['code', 'store.parent', 'category', 'images', 'user', 'propertyValues.property.unit', 'propertyValues.dictionaryValue'];
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

    private function bindCodeToItem(Item $item, string $code): void
    {
        // Код хранилища не может быть переиспользован предметом: скан такого
        // кода должен приводить к хранилищу, а не к предмету.
        if (Code::where('code', $code)->whereNotNull('store_id')->exists()) {
            throw ValidationException::withMessages([
                'code' => ['Код уже привязан к хранилищу'],
            ]);
        }

        // Свободный код (например, напечатанный в наборе безымянных этикеток)
        // переиспользуем, а не плодим вторую строку с тем же значением:
        // уникальность code в БД не гарантирована, дубль разошёлся бы по
        // поиску и по набору. link на label_list сохраняется — код остаётся
        // частью своего набора.
        $free = Code::where('code', $code)
            ->whereNull('item_id')
            ->whereNull('store_id')
            ->orderByDesc('id')
            ->first();

        // Дубли кодов разрешены — просто заменяем связку этого предмета.
        Code::where('item_id', $item->id)->delete();

        if ($free !== null) {
            $free->update(['item_id' => $item->id]);

            return;
        }

        Code::create(['code' => $code, 'item_id' => $item->id]);
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
            $uuid = strtoupper(str_replace('-', '', (string) $item->code->code));

            $barcode = new Barcode();
            $bobj = $barcode->getBarcodeObj('DATAMATRIX', $uuid, 200, 200);
            $item->qrSvg = $bobj->getSvgCode();

            return $item;
        });

        return view('item.list', compact('items'));
    }
}
