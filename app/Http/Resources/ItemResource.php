<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\MarksDeleted;
use App\Http\Resources\ImageResource;
use App\Models\Item;
use App\Models\Property;
use App\Services\AccessService;
use App\Services\PartialWriteoff;
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

                // Расход частями по свойствам. Сами настройки и остатки — в
                // одноимённом блоке partial ниже, потому что это не поле
                // предмета, а расчёт по его свойствам.
                'partial_writeoff' => (bool) $this->partial_writeoff,
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

            // Расход частями: по каждому расходуемому свойству — норма на
            // штуку, сколько осталось внутри текущей штуки и сколько всего с
            // учётом целых штук.
            'partial' => $this->whenLoaded('partialWriteoffProperties', function (): array {
                return $this->partialWriteoffBlock();
            }),
        ];
    }

    /**
     * Блок частичного списания для ответа.
     *
     * Всего и остатка текущей штуки здесь три числа, и они нужны вместе:
     * сколько осталось по складу и сколько — в той бутылке, которая сейчас
     * расходуется. Считается на лету, отдельной колонки с итогом нет: итог
     * меняется вместе с количеством и нормой, и хранить его отдельно значило
     * бы держать вторую копию одного и того же.
     */
    private function partialWriteoffBlock(): array
    {
        $partial = app(PartialWriteoff::class);
        $settings = $partial->settings($this->resource);
        $rows = [];

        foreach ($settings as $setting) {
            $norm = $partial->norm($this->resource, $setting->property_id);

            if ($norm === null) {
                // Нормы нет — расходовать нечего, и такую настройку
                // показывать незачем.
                continue;
            }

            $remaining = $partial->remaining($this->resource, $setting->property_id, $norm);

            $rows[] = [
                'property_id'     => $setting->property_id,
                'property_title'  => Property::find((int) $setting->property_id)?->title,
                'step'            => (float) $setting->step,
                'is_full_reason'  => (bool) $setting->is_full_reason,
                'sort'            => (int) $setting->sort,
                'norm'            => $norm,
                'remaining'       => $remaining,
                'total'           => $partial->total($this->resource, $setting->property_id, $norm),
                // Сколько можно списать: ровно общий остаток. Раньше здесь
                // стояло «меньше», потому что расход шёл поштучно и упирался в
                // текущую штуку; теперь остаток и число штук считаются от
                // запаса, и ограничения нет — что показано, то и спишется.
                'available'       => $partial->total($this->resource, (int) $setting->property_id, $norm),
            ];
        }

        return $rows;
    }
}
