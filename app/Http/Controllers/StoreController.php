<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Com\Tecnick\Barcode\Barcode;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        return 'Store index';
    }

    public function list()
    {
        $stores = Store::with('code')->orderBy('id')->get();

        $stores->transform(function (Store $store) {
            $uuid = strtoupper(str_replace('-', '', (string) $store->code->code));

            // QR code (endroid/qr-code) — temporarily replaced with Data Matrix
            // $builder = new \Endroid\QrCode\Builder\Builder(
            //     writer: new \Endroid\QrCode\Writer\SvgWriter(),
            //     data: $uuid,
            //     size: 200,
            //     margin: 10,
            // );
            // $store->qrSvg = $builder->build()->getString();

            $barcode = new Barcode();
            $bobj = $barcode->getBarcodeObj('DATAMATRIX', $uuid, 200, 200);
            $store->qrSvg = $bobj->getSvgCode();

            return $store;
        });

        return view('store.list', compact('stores'));
    }
}
