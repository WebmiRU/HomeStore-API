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
        $q = trim((string) $request->query('q'));

        if (strlen($q) < 8 || strlen($q) > 256) {
            return response()->json(['error' => 'Invalid code length'], 400);
        }

        $candidates = [$q];

        // «Голый» UUID (32 hex-символа) приводим к дефисному виду для совместимости
        if (strlen($q) === 32 && ctype_xdigit($q)) {
            $lower = strtolower($q);
            $candidates[] = substr($lower, 0, 8) . '-'
                . substr($lower, 8, 4) . '-'
                . substr($lower, 12, 4) . '-'
                . substr($lower, 16, 4) . '-'
                . substr($lower, 20);
        }

        $code = Code::with(['store.parent', 'item.store.parent'])
            ->whereIn('code', $candidates)
            ->first();

        if (!$code) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return new CodeResource($code);
    }
}
