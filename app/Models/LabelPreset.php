<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LabelPreset extends Model
{
    use OwnedByUser;

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
        'show_text',
        'font_id',
        'font_size_min',
        'font_size_max',
        'font_size_step',
        'line_height_factor',
        'user_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'show_text' => 'boolean',
    ];

    /**
     * Пользователь видит свои шаблоны и общие системные.
     *
     * Системные опознаются по is_system, а не по user_id IS NULL: у
     * осиротевших после удаления пользователя шаблонов user_id тоже NULL,
     * и трактовка «NULL — значит системный» выдала бы их наружу.
     */
    protected static function applyOwnershipScope(Builder $builder, int $userId): void
    {
        $builder->where(function (Builder $q) use ($userId): void {
            $q->where('label_preset.user_id', $userId)
                ->orWhere('label_preset.is_system', true);
        });
    }

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function font()
    {
        return $this->belongsTo(Font::class);
    }

    public function images()
    {
        return $this->belongsToMany(Image::class, 'image_m2m_label_preset')
            ->withTimestamps();
    }

    public function labelLists()
    {
        return $this->hasMany(LabelList::class);
    }

    /**
     * Сколько ячеек помещается на лист по горизонтали, по вертикали и всего.
     */
    public function layout(): array
    {
        $columns = (int) max(1, floor(
            ($this->page_width - $this->page_margin_left - $this->page_margin_right) / $this->cell_width
        ));
        $rows = (int) max(1, floor(
            ($this->page_height - $this->page_margin_top - $this->page_margin_bottom) / $this->cell_height
        ));

        return [
            'columns'   => $columns,
            'rows'      => $rows,
            'per_page'  => $columns * $rows,
        ];
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
            'show_text'          => (bool) $this->show_text,
            'font_family'        => $this->font?->key ?? 'robotocondensedb',
            'font_size_min'      => $this->font_size_min,
            'font_size_max'      => $this->font_size_max,
            'font_size_step'     => $this->font_size_step,
            'line_height_factor' => $this->line_height_factor,
        ];
    }
}
