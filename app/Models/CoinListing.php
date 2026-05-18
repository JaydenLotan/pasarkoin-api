<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinListing extends Model
{
    protected $fillable = [
        'seller_id',
        'title',
        'description',
        'price',
        'coin_origin',
        'coin_year',
        'material',
        'condition',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function images()
    {
        return $this->hasMany(CoinImage::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}