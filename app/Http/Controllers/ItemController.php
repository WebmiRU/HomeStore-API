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
            $item = Item::create($request->validated());

            Code::create([
                'code'    => (string) Str::uuid7(),
                'item_id' => $item->id,
            ]);

            return $item;
        });

        return (new ItemResource($item->load(['code', 'store.parent'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateItemRequest $request, Item $model): ItemResource
    {
        $model->update($request->validated());

        return new ItemResource($model->load(['code', 'store.parent']));
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
