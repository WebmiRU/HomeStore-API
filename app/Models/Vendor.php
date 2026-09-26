<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendor extends Model
{
    use OwnedByUser;

    protected $table = 'vendor';

    protected $fillable = [
        'title',
        'description',
        'logo_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function logoImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'logo_id');
    }

    public function logoUrl(): ?string
    {
        return $this->logoImage?->url();
    }

    public function logoSha(): ?string
    {
        return $this->logoImage?->sha256;
    }

    /**
     * Обрезка по краям: уникальный индекс на (user_id, title) сравнивает
     * байты, и «Bosch » прошло бы проверку уникальности, чтобы упереться
     * в него на вставке. Обрезка приводит значение к тому же виду, который
     * проверяет Rule::unique, иначе дубликат проскакивал бы в базу.
     */
    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value === null ? null : trim($value);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        // Пустая textarea приходит как '', а хранить её незачем: в карточке
        // и в списке пустое описание должно читаться как «нет описания».
        $value = $value === null ? null : trim($value);

        $this->attributes['description'] = $value === '' ? null : $value;
    }
}
