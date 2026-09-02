<?php

namespace App\Modules\AssetRegistry\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRegistry extends Model
{
    use HasFactory;

    protected $table = 'asset_registry';

    protected $fillable = [
        'asset_code', 'category', 'name', 'pic', 'location', 'purchase_date', 'status',
    ];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    /**
     * Nama relasi sengaja bukan "pic()" — kolom "pic" (FK ke users.id) akan
     * selalu menang atas method relasi bernama sama pada Eloquent::getAttribute(),
     * sehingga $model->pic tidak akan pernah mengembalikan relasi.
     */
    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic');
    }
}
