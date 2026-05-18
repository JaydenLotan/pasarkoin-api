<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'buyer_id',
        'coin_listing_id',
        'total_price',
        'status',
        'buyer_name',
        'buyer_phone',
        'shipping_address',
        'seller_note',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function coinListing()
    {
        return $this->belongsTo(CoinListing::class);
    }
}