<?php

namespace App\Providers;

use App\Modules\Maintenance\Models\Request as MaintenanceRequest;
use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\Maintenance\Models\WorkOrderItem;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cocok dengan nilai attachable_type pada tabel attachments (Design
        // Document Bagian 2.2 / DB Schema 4.6). Memakai morphMap() (bukan
        // enforceMorphMap()) karena enforceMorphMap melarang morph relation
        // App\Models\User milik Sanctum (personal_access_tokens.tokenable_type)
        // yang tidak terdaftar di map ini.
        Relation::morphMap([
            'request' => MaintenanceRequest::class,
            'work_order' => WorkOrder::class,
            'work_order_item' => WorkOrderItem::class,
        ]);

        // Model pada app/Modules/* tidak mengikuti struktur namespace default
        // (App\Models\*) yang diasumsikan Eloquent saat menebak nama factory,
        // sehingga nama factory di-flatten ke Database\Factories\{Model}Factory.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Batasi percobaan login (NFR-04 Security) — dikunci per email+IP
        // (bukan per IP saja) supaya satu penyerang tidak bisa mengunci
        // pengguna lain yang kebetulan berbagi IP (mis. kantor cabang di
        // belakang NAT yang sama), dan tidak per email saja supaya
        // penyerang tidak bisa mengunci akun korban dari IP mana pun.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        // Token SSO belum terdekripsi saat request masuk (tidak ada email di
        // body), jadi dikunci per-IP saja — cukup untuk menahan brute-force
        // tebak-token tanpa memblokir pengguna sah di belakang NAT yang sama
        // secara agresif (limit lebih longgar dari 'login').
        RateLimiter::for('sso', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
