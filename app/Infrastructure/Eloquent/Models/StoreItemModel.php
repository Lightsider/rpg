<?php

namespace App\Infrastructure\Eloquent\Models;

use App\Domain\Store\StoreItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreItemModel extends Model
{
    use HasFactory;

    protected $table = 'store_items';
    protected $guarded = [];

    public function toDomain(): StoreItem
    {
        return new StoreItem(
            $this->id,
            $this->store_id,
            $this->item_id,
            $this->price,
            $this->currency_type
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(StoreModel::class, 'store_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'item_id');
    }
}
