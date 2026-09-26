<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use OwnedByUser;

    protected $table = 'property';

    protected $fillable = [
        'title',
        'type',
        'group_id',
        'unit_id',
        'dictionary_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value === null ? null : trim($value);
    }

    public function group()
    {
        return $this->belongsTo(PropertyGroup::class, 'group_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function dictionary()
    {
        return $this->belongsTo(Dictionary::class, 'dictionary_id');
    }

    public function values()
    {
        return $this->hasMany(ItemProperty::class, 'property_id');
    }
}
