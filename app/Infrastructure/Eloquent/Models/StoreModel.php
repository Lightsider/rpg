<?php

namespace App\Infrastructure\Eloquent\Models;

use App\Domain\Store\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreModel extends Model
{
    use HasFactory;

    protected $table = 'stores';
    protected $guarded = [];

    public function toDomain(): Store
    {
        return new Store(
            $this->id,
            $this->name,
            $this->location_id
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreItemModel::class, 'store_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationModel::class, 'location_id');
    }
}
