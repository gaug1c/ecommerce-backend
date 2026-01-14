<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Attributs assignables en masse
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'is_active',
        'email_verified_at',
    ];

    /**
     * Attributs cachés pour l'API
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts automatiques
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /* =======================
       RELATIONS
    ======================== */

    // Relation many-to-many avec les rôles
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    // Relation one-to-one avec le profil vendeur
    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    // Relation one-to-many avec les adresses
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /* =======================
       HELPERS & ROLE CHECKS
    ======================== */

    /**
     * Vérifie si l'utilisateur a un rôle donné
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Vérifie si l'utilisateur est vendeur
     */
    public function isSeller(): bool
    {
        return $this->hasRole('seller');
    }

    /**
     * Vérifie si l'utilisateur est client
     */
    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    /* =======================
       UTILS POUR LE PROFIL VENDEUR
    ======================== */

    /**
     * Retourne true si le profil vendeur est créé et actif
     */
    public function sellerProfileActive(): bool
    {
        return $this->sellerProfile && $this->sellerProfile->seller_status === 'active';
    }

    /**
     * Retourne le nom de la boutique si existante
     */
    public function shopName(): ?string
    {
        return $this->sellerProfile ? $this->sellerProfile->shop_name : null;
    }
}
