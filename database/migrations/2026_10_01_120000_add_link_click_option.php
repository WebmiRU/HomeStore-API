<?php

use App\Models\Option;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Настройка «Клик по ссылке: переход или фильтр».
 *
 * Клик по названию хранилища, категории или производителя в списке предметов
 * увёл на страницу объекта. Иногда это то, что нужно, а иногда — наоборот:
 * человек в списке предметов хочет посмотреть, что лежит в этом хранилище, и
 * остаться в списке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->string('link_click', 16)->default(Option::LINK_CLICK_NAVIGATE)->after('locale');
        });

        // В существующих строках значения не поставить нельзя: там NULL вместо
        // значения по умолчанию, и Option::present отдаст null — настройка
        // окажется «не заданной» у всех, кто ею пользуется.
        DB::table('option')->whereNull('link_click')->update(['link_click' => Option::LINK_CLICK_NAVIGATE]);
    }

    public function down(): void
    {
        Schema::table('option', function (Blueprint $table): void {
            $table->dropColumn('link_click');
        });
    }
};