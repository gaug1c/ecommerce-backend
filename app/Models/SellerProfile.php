<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'shop_address',
        'shop_city',
        'shop_country',
        'shop_postal_code',
        'id_card_path',
        'id_card_status',
        'seller_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
