<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Models\Code;
use App\Models\Image;
use App\Models\Item;
use App\Models\Store;
use App\Models\UserProfile;
use App\Support\CurrentUser;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class DemoItemsSeeder extends Seeder
{
    public function run(): void
    {
        $demo = UserProfile::where('email', 'demo@demo.demo')->firstOrFail();
        CurrentUser::set($demo);
        $store = Store::where('title', $this->storeTitle())->where('user_id', $demo->id)->firstOrFail();

        foreach ($this->items() as $title => $spec) {
            $file = $spec['file'];
            $qty  = $spec['qty'];

            $item = Item::where('title', $title)->where('store_id', $store->id)->first();

            if ($item === null) {
                DB::transaction(function () use ($title, $file, $qty, $store, $demo): void {
                    $item = Item::create([
                        'title'    => $title,
                        'store_id' => $store->id,
                        'user_id'  => $demo->id,
                        'quantity' => $qty,
                    ]);

                    Code::create([
                        'code'    => (string) Str::uuid7(),
                        'item_id' => $item->id,
                    ]);

                    $image = $this->storeImage($file, $title);
                    $item->images()->attach($image->id, [
                        'alt'    => $title,
                        'weight' => 1,
                    ]);
                });

                continue;
            }

            // Уже существует, но без количественного учёта (демо-предмет до
            // введения сидера остатков): заводим остаток и фиксируем «создание»
            // в журнал, чтобы остатки/статистика по нему появились.
            if ($item->quantity === null) {
                $item->update(['quantity' => $qty]);

                app(\App\Services\AuditLogService::class)->record(
                    AuditAction::ItemCreated,
                    'item_id',
                    (int) $item->id,
                    (int) $demo->id,
                    ['snapshot' => $item->refresh()->getAttributes()],
                    (int) $demo->id,
                );
            }
        }
    }

    abstract protected function storeTitle(): string;

    /**
     * @return array<string, array{file: string, qty: int}>
     */
    abstract protected function items(): array;

    private function storeImage(string $file, string $alt): Image
    {
        $path = database_path('migrations/images/' . $file);
        $mime = mime_content_type($path);

        $uploaded = new UploadedFile(
            $path,
            $file,
            $mime,
            null,
            true,
        );

        return Image::fromUploadedFile($uploaded);
    }
}