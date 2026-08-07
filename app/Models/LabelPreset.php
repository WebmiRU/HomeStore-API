<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelPreset extends Model
{
    protected $table = 'label_preset';

    protected $fillable = [
        'title',
        'page_width',
        'page_height',
        'page_margin_top',
        'page_margin_right',
        'page_margin_bottom',
        'page_margin_left',
        'cell_width',
        'cell_height',
        'cell_pad_top',
        'cell_pad_right',
        'cell_pad_bottom',
        'cell_pad_left',
        'barcode_position',
        'barcode_text_gap',
        'barcode_size',
        'font_id',
        'font_size_min',
        'font_size_max',
        'font_size_step',
        'line_height_factor',
    ];

    public function font()
    {
        return $this->belongsTo(Font::class);
    }

    public function images()
    {
        return $this->belongsToMany(Image::class, 'image_m2m_label_preset')
            ->withTimestamps();
    }

    /**
     * Return all options as an associative array suitable for LabelPdfService.
     */
    public function toOptions(): array
    {
        return [
            'page_width'         => $this->page_width,
            'page_height'        => $this->page_height,
            'page_margin_top'    => $this->page_margin_top,
            'page_margin_right'  => $this->page_margin_right,
            'page_margin_bottom' => $this->page_margin_bottom,
            'page_margin_left'   => $this->page_margin_left,
            'cell_width'         => $this->cell_width,
            'cell_height'        => $this->cell_height,
            'cell_pad_top'       => $this->cell_pad_top,
            'cell_pad_right'     => $this->cell_pad_right,
            'cell_pad_bottom'    => $this->cell_pad_bottom,
            'cell_pad_left'      => $this->cell_pad_left,
            'barcode_position'   => $this->barcode_position,
            'barcode_text_gap'   => $this->barcode_text_gap,
            'barcode_size'       => $this->barcode_size,
            'font_family'        => $this->font?->key ?? 'robotocondensedb',
            'font_size_min'      => $this->font_size_min,
            'font_size_max'      => $this->font_size_max,
            'font_size_step'     => $this->font_size_step,
            'line_height_factor' => $this->line_height_factor,
        ];
    }
}
