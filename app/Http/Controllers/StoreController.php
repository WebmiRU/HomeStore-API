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
use Illuminate\Support\Str;

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

    public function get(Store $model): StoreResource
    {
        return new StoreResource($model->load(['code', 'parent']));
    }

    public function post(StoreStoreRequest $request): JsonResponse
    {
        $store = Store::create($request->validated());

        Code::create([
            'code'     => (string) Str::uuid(),
            'store_id' => $store->id,
        ]);

        return (new StoreResource($store->load(['code', 'parent'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateStoreRequest $request, Store $model): StoreResource
    {
        $model->update($request->validated());

        return new StoreResource($model->load(['code', 'parent']));
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
