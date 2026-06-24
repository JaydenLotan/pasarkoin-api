<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinImage extends Model
{
    protected $fillable = [
        'coin_listing_id',
        'image_path',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function coinListing()
    {
        return $this->belongsTo(CoinListing::class);
    }
}