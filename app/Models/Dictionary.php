<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class Dictionary extends Model
{
    use OwnedByUser;

    protected $table = 'dictionary';

    protected $fillable = [
        'title',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value === null ? null : trim($value);
    }

    public function values()
    {
        return $this->hasMany(DictionaryValue::class, 'dictionary_id');
    }
}
