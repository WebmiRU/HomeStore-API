<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MarksDeleted;
use App\Http\Resources\ImageResource;
use App\Models\Item;
use App\Services\AccessService;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    use MarksDeleted;

    public function toArray(Request $request): array
    {
        $rights = $this->resource instanceof Item
            ? app(AccessService::class)->rightsFor($this->resource)
            : [];

        return [
            'type'    => 'item',
            'rights'  => $rights,
            'is_owner'=> $this->user_id !== null && (int) $this->user_id === (int) CurrentUser::id(),
            'can_edit'=> in_array('edit', $rights, true),
            'can_delete' => in_array('delete', $rights, true),
            'code'    => $this->whenLoaded('code', fn() => $this->code?->code),

            // Коды, по которым тот же предмет есть у других: сообщаем, но
            // не мешаем жить. Дубли законны (один штрихкод на несколько
            // экземпляров), и запрещать их на сохранении нельзя — предупредить
            // можно. Значение ставит контроллер после сохранения кодов.
            'conflicts' => $this->whenLoaded(
                'conflicts',
                fn() => $this->conflicts
            ),

            // Все коды предмета, от главного к прочим. code выше — главный,
            // он же первый в этом списке: карточка и печать этикеток по
            // умолчанию берут именно его, а остальные живут здесь.
            'codes'   => $this->whenLoaded(
                'codes',
                fn() => $this->codes->pluck('code')->values()
            ),
            'payload' => [
                'id'          => $this->id,
                'user_id'     => $this->user_id,
                'user'        => $this->relationLoaded('user') && $this->user !== null
                    ? new UserBriefResource($this->user)
                    : $this->when(false, null),
                'title'       => $this->title,
                'title_print' => $this->title_print,
                'store_id'    => $this->store_id,
                'category_id' => $this->category_id,
                'vendor_id'   => $this->vendor_id,
                'quantity'    => $this->quantity,

                // Пометка «списывать по коду»: при списании код, по которому
                // сканировали, высвобождается и может быть наклеен на другую
                // вещь. По умолчанию выключено.
                'release_code_on_writeoff' => (bool) $this->release_code_on_writeoff,
                'created_at'  => $this->created_at,
                'updated_at'  => $this->updated_at,
            ],
            'vendor'   => $this->whenLoaded('vendor', fn() => $this->related($this->vendor, [
                'id'    => $this->vendor->id,
                'title' => $this->vendor->title,
            ])),
            'category' => $this->whenLoaded('category', fn() => $this->related($this->category, [
                'id'    => $this->category->id,
                'title' => $this->category->title,
            ])),
            'store'   => $this->whenLoaded('store', function () {
                $chain = [$this->related($this->store, $this->store->getAttributes())];
                foreach (array_reverse($this->store->ancestors()) as $ancestor) {
                    $chain[] = $ancestor;
                }
                return $chain;
            }),
            'images'  => ImageResource::collection($this->whenLoaded('images')),
            // В списке предметов значения не подгружаются, и поля в ответе
            // просто нет — карточка предмета их показывает, а список обошёл
            // бы ещё одну выборку на страницу впустую.
            'properties' => ItemPropertyResource::collection($this->whenLoaded('propertyValues')),
        ];
    }
}
