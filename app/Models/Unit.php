<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use OwnedByUser;

    protected $table = 'unit';

    protected $fillable = [
        'title_short',
        'title_full',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function setTitleShortAttribute(?string $value): void
    {
        $this->attributes['title_short'] = $value === null ? null : trim($value);
    }

    public function setTitleFullAttribute(?string $value): void
    {
        $this->attributes['title_full'] = $value === null ? null : trim($value);
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'unit_id');
    }
}
