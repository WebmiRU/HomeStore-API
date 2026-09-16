<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // «Осиротевшие» сущности (не входящие ни в один склад) передаём основному владельцу.
        $ownerId = DB::table('user')->where('email', 'e.wolf@webmi.ru')->value('id');

        if ($ownerId === null) {
            return;
        }

        DB::table('store')
            ->whereNull('warehouse_id')
            ->where('user_id', '<>', $ownerId)
            ->update(['user_id' => $ownerId]);

        // Предметы: без хранилища либо в хранилище без склада.
        DB::table('item')
            ->where('user_id', '<>', $ownerId)
            ->where(function ($q) {
                $q->whereNull('store_id')
                    ->orWhereRaw('store_id NOT IN (SELECT id FROM store WHERE warehouse_id IS NOT NULL)');
            })
            ->update(['user_id' => $ownerId]);
    }

    public function down(): void
    {
        // Необратимая переназначение владельца — откат не предусмотрен.
    }
};