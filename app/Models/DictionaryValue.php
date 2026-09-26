<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Значение справочника. Своего user_id у модели нет — владение выводится
 * из справочника-родителя (см. миграцию dictionary_value).
 */
class DictionaryValue extends Model
{
    protected $table = 'dictionary_value';

    protected $fillable = [
        'dictionary_id',
        'title',
    ];

    public function dictionary()
    {
        return $this->belongsTo(Dictionary::class, 'dictionary_id');
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value === null ? null : trim($value);
    }
}
