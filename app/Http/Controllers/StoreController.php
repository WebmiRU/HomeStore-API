<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Code;
use App\Models\Store;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreController extends Controller
{
    public function index(): ResourceCollection
    {
        return StoreResource::collection(
            Store::with(['code', 'parent'])
                ->orderBy('id')
                ->paginate()
        );
    }

    public function all(): ResourceCollection
    {
        return StoreResource::collection(
            Store::with(['code', 'parent'])
                ->orderBy('id')
                ->get()
        );
    }

    public function get(Store $model): StoreResource
    {
        return new StoreResource($model->load(['code', 'parent']));
    }

    public function post(StoreStoreRequest $request): JsonResponse
    {
        $store = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $code = isset($data['code']) ? trim((string) $data['code']) : '';
            unset($data['code']);

            $store = Store::create($data);

            if ($code === '') {
                Code::create([
                    'code'     => (string) Str::uuid7(),
                    'store_id' => $store->id,
                ]);
            } else {
                $this->bindCodeToStore($store, $code);
            }

            return $store;
        });

        return (new StoreResource($store->load(['code', 'parent'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateStoreRequest $request, Store $model): StoreResource
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
                // Отвязываем код от хранилища
                Code::where('store_id', $model->id)->delete();
                return;
            }

            $this->bindCodeToStore($model, $code);
        });

        return new StoreResource($model->load(['code', 'parent']));
    }

    private function bindCodeToStore(Store $store, string $code): void
    {
        $existing = Code::where('code', $code)->first();

        if ($existing) {
            if ($existing->store_id !== null && $existing->store_id !== $store->id) {
                throw ValidationException::withMessages([
                    'code' => ['Код уже привязан к другому хранилищу'],
                ]);
            }

            if ($existing->item_id !== null) {
                throw ValidationException::withMessages([
                    'code' => ['Код уже привязан к предмету'],
                ]);
            }

            if ($existing->store_id === $store->id) {
                // Код уже привязан к этому хранилищу
                return;
            }

            // «Осиротевший» код — привязываем к хранилищу
            Code::where('store_id', $store->id)->delete();
            $existing->update(['store_id' => $store->id]);

            return;
        }

        // Новый код — заменяем текущую связку хранилища
        Code::where('store_id', $store->id)->delete();
        Code::create(['code' => $code, 'store_id' => $store->id]);
    }

    public function delete(Store $model): JsonResponse
    {
        $model->delete();

        return response()->json(null, 204);
    }

    public function list()
    {
        $stores = Store::with('code')->orderBy('id')->get();

        $stores->transform(function (Store $store) {
            $uuid = strtoupper(str_replace('-', '', (string) $store->code->code));

            $barcode = new Barcode();
            $bobj = $barcode->getBarcodeObj('DATAMATRIX', $uuid, 200, 200);
            $store->qrSvg = $bobj->getSvgCode();

            return $store;
        });

        return view('store.list', compact('stores'));
    }
}
