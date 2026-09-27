<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabelList extends Model
{
    use SoftDeletes;
    use OwnedByUser;


    protected $table = 'label_list';

    protected $fillable = [
        'title',
        'label_preset_id',
        'print_all_codes',
        'user_id',
    ];

    protected $casts = [
        'print_all_codes' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function labelPreset()
    {
        // Шаблон удаляется мягко, а список этикеток его переживает: связь
        // остаётся, чтобы в графе «Шаблон» было видно название с пометкой.
        return $this->belongsTo(LabelPreset::class)->withTrashed();
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'label_list_m2m_item')
            ->withTimestamps();
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'label_list_m2m_store')
            ->withTimestamps();
    }

    /**
     * Коды, напечатанные в этом наборе.
     *
     * У набора с предметами/хранилищами пусто: там печатаются их собственные
     * коды. Непустой список означает набор безымянных этикеток, где каждый код
     * принадлежит набору и не привязан ни к одному объекту.
     */
    public function codes()
    {
        return $this->hasMany(Code::class);
    }
}
