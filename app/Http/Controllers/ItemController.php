<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Code;
use App\Models\Item;
use Com\Tecnick\Barcode\Barcode;
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
            Item::with(['code', 'store.parent'])
                ->orderBy('id')
                ->paginate()
        );
    }

    public function get(Item $model): ItemResource
    {
        return new ItemResource($model->load(['code', 'store.parent']));
    }

    public function post(StoreItemRequest $request): JsonResponse
    {
        $item = DB::transaction(function () use ($request) {
            $data = $request->validated();
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
                $existing = Code::where('code', $code)->first();

                if ($existing) {
                    if ($existing->item_id !== null) {
                        throw ValidationException::withMessages([
                            'code' => ['Код уже привязан к другому предмету'],
                        ]);
                    }

                    if ($existing->store_id !== null) {
                        throw ValidationException::withMessages([
                            'code' => ['Код уже привязан к хранилищу'],
                        ]);
                    }

                    // Код существует, но ни к чему не привязан — привязываем к товару
                    $existing->update(['item_id' => $item->id]);
                } else {
                    Code::create([
                        'code'    => $code,
                        'item_id' => $item->id,
                    ]);
                }
            }

            return $item;
        });

        return (new ItemResource($item->load(['code', 'store.parent'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateItemRequest $request, Item $model): ItemResource
    {
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

        return new ItemResource($model->load(['code', 'store.parent']));
    }

    private function bindCodeToItem(Item $item, string $code): void
    {
        $existing = Code::where('code', $code)->first();

        if ($existing) {
            if ($existing->item_id !== null && $existing->item_id !== $item->id) {
                throw ValidationException::withMessages([
                    'code' => ['Код уже привязан к другому предмету'],
                ]);
            }

            if ($existing->store_id !== null) {
                throw ValidationException::withMessages([
                    'code' => ['Код уже привязан к хранилищу'],
                ]);
            }

            if ($existing->item_id === $item->id) {
                // Код уже привязан к этому товару
                return;
            }

            // «Осиротевший» код — привязываем к товару
            Code::where('item_id', $item->id)->delete();
            $existing->update(['item_id' => $item->id]);

            return;
        }

        // Новый код — заменяем текущую связку товара
        Code::where('item_id', $item->id)->delete();
        Code::create(['code' => $code, 'item_id' => $item->id]);
    }

    public function delete(Item $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }

    public function list()
    {
        $items = Item::with('code')->orderBy('id')->get();

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
