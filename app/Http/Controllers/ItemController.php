<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Code;
use App\Models\Item;
use App\Models\Store;
use Com\Tecnick\Barcode\Barcode;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    public function index(): ResourceCollection
    {
        return ItemResource::collection(
            Item::with(['code', 'store.parent', 'images', 'user'])
                ->orderByDesc('id')
                ->paginate()
        );
    }

    public function get(Item $model): ItemResource
    {
        return new ItemResource($model->load(['code', 'store.parent', 'images', 'user']));
    }

    public function post(StoreItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        abort_unless($this->canCreateItem($data), 403, 'Нет права на создание в этом складе');

        $item = DB::transaction(function () use ($data) {
            $code = isset($data['code']) ? trim((string) $data['code']) : '';
            unset($data['code']);

            $item = Item::create($data);

            if ($code === '') {
                // Код не передан — генерируем UUID по умолчанию
                Code::create([
                    'code'    => (string) Str::uuid7(),
                    'item_id' => $item->id,
                ]);
            } else {
                // Коды могут повторяться у разных предметов (один штрихкод на
                // несколько экземпляров товара). Отклоняем только код, привязанный
                // к хранилищу: скан такого кода приводит к хранилищу, а не к предмету.
                if (Code::where('code', $code)->whereNotNull('store_id')->exists()) {
                    throw ValidationException::withMessages([
                        'code' => ['Код уже привязан к хранилищу'],
                    ]);
                }

                Code::create([
                    'code'    => $code,
                    'item_id' => $item->id,
                ]);
            }

            return $item;
        });

        return (new ItemResource($item->load(['code', 'store.parent', 'images', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateItemRequest $request, Item $model): ItemResource
    {
        abort_unless(app(AccessService::class)->canEdit($model), 403);

        DB::transaction(function () use ($request, $model) {
            $data = $request->validated();
            $code = array_key_exists('code', $data) ? trim((string) ($data['code'] ?? '')) : null;
            unset($data['code']);

            $model->update($data);

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

        return new ItemResource($model->load(['code', 'store.parent', 'images', 'user']));
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
