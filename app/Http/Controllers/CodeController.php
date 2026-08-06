<?php

namespace App\Http\Controllers;

use App\Http\Resources\CodeResource;
use App\Models\Code;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class CodeController extends Controller
{
    public function index()
    {
        return Uuid::uuid7()->toString();
    }

    public function search(Request $request)
    {
        $q = $request->query('q');

        if (!$q || strlen($q) !== 32) {
            return response()->json(['error' => 'Invalid UUID format'], 400);
        }

        $lower = strtolower($q);
        $uuid = substr($lower, 0, 8) . '-'
            . substr($lower, 8, 4) . '-'
            . substr($lower, 12, 4) . '-'
            . substr($lower, 16, 4) . '-'
            . substr($lower, 20);

        $code = Code::with(['store', 'item'])->where('code', $uuid)->first();

        if (!$code) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return new CodeResource($code);
    }
}
