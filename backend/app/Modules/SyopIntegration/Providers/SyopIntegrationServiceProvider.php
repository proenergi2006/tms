<?php

namespace App\Modules\SyopIntegration\Providers;

use App\Modules\SyopIntegration\Adapters\SyopNativeAdapter;
use App\Modules\SyopIntegration\Contracts\SyopDataProviderInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Mengikat SyopDataProviderInterface ke implementasinya. Saat SYOP v4 live,
 * cukup ganti binding di bawah ke SyopV4Adapter (Architecture Document
 * Bagian 6.3) — tanpa mengubah kode modul bisnis yang bergantung pada
 * interface ini.
 */
class SyopIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SyopDataProviderInterface::class, SyopNativeAdapter::class);
    }
}
