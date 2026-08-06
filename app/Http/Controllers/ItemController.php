<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        return 'Item index';
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
