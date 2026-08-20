<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'role', 'external', 'notes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'admin_deactivated_at' => 'datetime',
            'external' => 'boolean',
        ];
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function discountCodes(): HasMany
    {
        return $this->hasMany(DiscountCode::class)->latest();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class)->latest();
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class)->latest();
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Owners have full access; staff are blocked from refunds, deleting
     * discounts, viewing Stripe payment data, and managing other admins.
     */
    public function isOwner(): bool
    {
        return $this->isAdmin() && $this->role === 'owner';
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() && $this->role !== 'owner';
    }

    public function initials(): string
    {
        $first = mb_substr(trim((string) $this->first_name), 0, 1);
        $last = mb_substr(trim((string) $this->last_name), 0, 1);
        $initials = strtoupper($first.$last);

        return $initials !== '' ? $initials : strtoupper(mb_substr((string) $this->email, 0, 1));
    }

    /**
     * Computed for backward compatibility with the many places that display
     * a single "name" (order facts, greetings, admin lists, ...).
     */
    protected function name(): Attribute
    {
        return Attribute::make(get: fn (): string => format_person_name($this->first_name, $this->last_name));
    }
}
