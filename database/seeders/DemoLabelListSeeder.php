<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\LabelList;
use App\Models\LabelPreset;
use App\Models\Store;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;

class DemoLabelListSeeder extends Seeder
{
    public function run(): void
    {
        $demo = UserProfile::where('email', 'demo@demo.demo')->firstOrFail();

        $workshopItems = Item::query()
            ->where('user_id', $demo->id)
            ->whereHas('store', fn ($q) => $q->where('title', 'Верхняя полка'))
            ->pluck('id')
            ->all();

        $drawerItems = Item::query()
            ->where('user_id', $demo->id)
            ->whereHas('store', fn ($q) => $q->where('title', 'Нижний ящик'))
            ->pluck('id')
            ->all();

        $this->makeList($demo, '111', 'Лоток 1л', $workshopItems);
        $this->makeList($demo, 'Хранилища 1', 'Хранилища', $drawerItems);
    }

    private function makeList(UserProfile $user, string $title, string $presetTitle, array $itemIds): void
    {
        $preset = LabelPreset::where('title', $presetTitle)->where('user_id', $user->id)->firstOrFail();

        $list = LabelList::updateOrCreate(
            ['title' => $title, 'user_id' => $user->id],
            ['label_preset_id' => $preset->id]
        );

        $list->items()->sync($itemIds);
    }
}