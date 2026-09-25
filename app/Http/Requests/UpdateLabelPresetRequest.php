<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabelPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'               => ['sometimes', 'string', 'max:500', 'unique:label_preset,title'],
            'page_width'          => ['sometimes', 'numeric', 'min:1'],
            'page_height'         => ['sometimes', 'numeric', 'min:1'],
            'page_margin_top'     => ['sometimes', 'numeric', 'min:0'],
            'page_margin_right'   => ['sometimes', 'numeric', 'min:0'],
            'page_margin_bottom'  => ['sometimes', 'numeric', 'min:0'],
            'page_margin_left'    => ['sometimes', 'numeric', 'min:0'],
            'cell_width'          => ['sometimes', 'numeric', 'min:1'],
            'cell_height'         => ['sometimes', 'numeric', 'min:1'],
            'cell_pad_top'        => ['sometimes', 'numeric', 'min:0'],
            'cell_pad_right'      => ['sometimes', 'numeric', 'min:0'],
            'cell_pad_bottom'     => ['sometimes', 'numeric', 'min:0'],
            'cell_pad_left'       => ['sometimes', 'numeric', 'min:0'],
            'barcode_position'    => ['sometimes', 'string', 'in:left,right,top,bottom'],
            'barcode_text_gap'    => ['sometimes', 'numeric', 'min:0'],
            'barcode_size'        => ['sometimes', 'numeric', 'min:1'],
            'font_id'             => ['nullable', 'integer', 'exists:font,id'],
            'font_size_min'       => ['sometimes', 'numeric', 'min:1'],
            'font_size_max'       => ['sometimes', 'numeric', 'min:1'],
            'font_size_step'      => ['sometimes', 'numeric', 'min:0.1'],
            'line_height_factor'  => ['sometimes', 'numeric', 'min:0.5'],
        ];
    }
}
