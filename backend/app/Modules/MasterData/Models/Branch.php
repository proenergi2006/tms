<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Fleet\Models\Fleet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'address'];

    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }
}
