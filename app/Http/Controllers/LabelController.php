<?php

namespace App\Http\Controllers;

use App\Services\Pdf\LabelPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class LabelController extends Controller
{
    public function __construct(
        private readonly LabelPdfService $labelService,
    ) {}

    /**
     * Генерирует PDF с этикетками и возвращает как скачиваемый файл.
     *
     * POST /api/label/generate
     *
     * Тело запроса (JSON):
     * {
     *   "labels": [
     *     {"code": "ITEM-001", "title": "Шуруп оцинкованный 4х35"},
     *     {"code": "ITEM-002", "title": "Гайка М8"},
     *     ...
     *   ],
     *   "options": {                       // все поля опциональны
     *     "page_width": 210,
     *     "page_height": 297,
     *     "page_margin_top": 10,
     *     "page_margin_right": 10,
     *     "page_margin_bottom": 10,
     *     "page_margin_left": 10,
     *     "cell_width": 78,
     *     "cell_height": 23,
     *     "cell_pad_top": 3,
     *     "cell_pad_right": 5,
     *     "cell_pad_bottom": 5,
     *     "cell_pad_left": 5,
     *     "barcode_position": "left",
     *     "barcode_size": 13,
     *     "font_family": "helvetica",
     *     "font_size_min": 5,
     *     "font_size_max": 14
     *   }
     * }
     */
    public function generate(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'labels'              => ['required', 'array', 'min:1'],
            'labels.*.code'       => ['required', 'string', 'max:255'],
            'labels.*.title'      => ['required', 'string', 'max:500'],
            'options'             => ['sometimes', 'array'],
            'options.barcode_position' => ['sometimes', 'string', 'in:left,right'],
            'options.font_family' => ['sometimes', 'string'],
            'options.font_size_min' => ['sometimes', 'numeric', 'min:2', 'max:20'],
            'options.font_size_max' => ['sometimes', 'numeric', 'min:4', 'max:72'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $labels  = $request->input('labels', []);
        $options = $request->input('options', []);

        $pdf = $this->labelService->generate($labels, $options);

        return response($pdf->Output('labels.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="labels.pdf"',
        ]);
    }
}
