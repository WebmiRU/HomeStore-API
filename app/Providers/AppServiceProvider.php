<?php

namespace App\Providers;

use App\Models\AccessGrant;
use App\Models\Item;
use App\Models\LabelList;
use App\Models\LabelPreset;
use App\Models\Store;
use App\Models\UserProfile;
use App\Models\Warehouse;
use App\Observers\AccessGrantObserver;
use App\Observers\ItemObserver;
use App\Observers\LabelListObserver;
use App\Observers\LabelPresetObserver;
use App\Observers\StoreObserver;
use App\Observers\UserProfileObserver;
use App\Observers\WarehouseObserver;
use App\Services\Pdf\LabelPdfService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LabelPdfService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Item::observe(ItemObserver::class);
        Store::observe(StoreObserver::class);
        Warehouse::observe(WarehouseObserver::class);
        LabelPreset::observe(LabelPresetObserver::class);
        LabelList::observe(LabelListObserver::class);
        AccessGrant::observe(AccessGrantObserver::class);
        UserProfile::observe(UserProfileObserver::class);
    }
}
