<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
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

            $builder = new Builder(
                writer: new SvgWriter(),
                data: $uuid,
                size: 200,
                margin: 10,
            );

            $store->qrSvg = $builder->build()->getString();

            return $store;
        });

        return view('store.list', compact('stores'));
    }
}
