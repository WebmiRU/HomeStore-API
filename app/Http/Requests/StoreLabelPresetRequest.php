<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabelPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'               => ['required', 'string', 'max:500', 'unique:label_preset,title'],
            'page_width'          => ['required', 'numeric', 'min:1'],
            'page_height'         => ['required', 'numeric', 'min:1'],
            'page_margin_top'     => ['required', 'numeric', 'min:0'],
            'page_margin_right'   => ['required', 'numeric', 'min:0'],
            'page_margin_bottom'  => ['required', 'numeric', 'min:0'],
            'page_margin_left'    => ['required', 'numeric', 'min:0'],
            'cell_width'          => ['required', 'numeric', 'min:1'],
            'cell_height'         => ['required', 'numeric', 'min:1'],
            'cell_pad_top'        => ['required', 'numeric', 'min:0'],
            'cell_pad_right'      => ['required', 'numeric', 'min:0'],
            'cell_pad_bottom'     => ['required', 'numeric', 'min:0'],
            'cell_pad_left'       => ['required', 'numeric', 'min:0'],
            'barcode_position'    => ['required', 'string', 'in:left,right,top,bottom'],
            'barcode_text_gap'    => ['required', 'numeric', 'min:0'],
            'barcode_size'        => ['required', 'numeric', 'min:1'],
            'font_id'             => ['nullable', 'integer', 'exists:font,id'],
            'font_size_min'       => ['required', 'numeric', 'min:1'],
            'font_size_max'       => ['required', 'numeric', 'min:1'],
            'font_size_step'      => ['required', 'numeric', 'min:0.1'],
            'line_height_factor'  => ['required', 'numeric', 'min:0.5'],
        ];
    }
}
