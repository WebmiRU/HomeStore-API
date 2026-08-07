<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Code;
use App\Models\Item;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return ItemResource::collection(
            Item::with(['code', 'store'])
                ->orderBy('id')
                ->get()
        );
    }

    public function get(Item $model): ItemResource
    {
        return new ItemResource($model->load(['code', 'store']));
    }

    public function post(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create($request->validated());

        // Авто-создание кода (UUID) для нового item
        Code::create([
            'code'    => (string) Str::uuid(),
            'item_id' => $item->id,
        ]);

        return (new ItemResource($item->load(['code', 'store'])))
            ->response()
            ->setStatusCode(201);
    }

    public function put(UpdateItemRequest $request, Item $model): ItemResource
    {
        $model->update($request->validated());

        return new ItemResource($model->load(['code', 'store']));
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
