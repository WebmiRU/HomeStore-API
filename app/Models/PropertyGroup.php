<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class PropertyGroup extends Model
{
    use OwnedByUser;

    protected $table = 'property_group';

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

    public function properties()
    {
        return $this->hasMany(Property::class, 'group_id');
    }
}
