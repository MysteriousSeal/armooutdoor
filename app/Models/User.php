<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

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
            'admin_viewed_at' => 'datetime',
            'external' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Proofs of age this customer has sent in.
     *
     * @return HasMany<IdentityDocument, $this>
     */
    public function identityDocuments(): HasMany
    {
        return $this->hasMany(IdentityDocument::class);
    }

    /**
     * Where this customer stands on proving their age.
     *
     * A verified document settles it even if a later one is still waiting, and
     * a rejection only stands while nothing better has arrived. A verification
     * lasts until the date on the document it was read from, after which it
     * counts for nothing and the customer is asked again. The answer is the
     * verdict alone: the document itself is opened on one screen and this is
     * not it.
     *
     * @return array{state: string, at: ?Carbon, until: ?Carbon}
     */
    public function identityStatus(): array
    {
        $documents = $this->relationLoaded('identityDocuments')
            ? $this->identityDocuments
            : $this->identityDocuments()->get();

        // The furthest expiry any document carries: the day this customer
        // stops being covered, whatever else they have sent in. Only a
        // verification records one, so it is also the last date anybody
        // approved them to.
        $until = $documents->pluck('expires_at')->filter()->sortDesc()->first();

        // Expired ranks below pending: a lapsed proof and a new document
        // waiting means the answer is "waiting", not "lapsed". It ranks above
        // rejected, since having complied once and run out is not the same as
        // having been refused.
        foreach (['verified', 'pending', 'expired', 'rejected'] as $state) {
            $match = $documents
                ->filter(fn ($document): bool => $document->effectiveStatus() === $state)
                ->sortByDesc(fn ($document) => $document->reviewed_at ?? $document->created_at)
                ->first();

            if ($match !== null) {
                return [
                    'state' => $state,
                    // An expired proof is dated by the day it lapsed, not by
                    // the day somebody approved it.
                    'at' => $state === 'expired'
                        ? $match->expires_at
                        : ($match->reviewed_at ?? $match->created_at),
                    'until' => $until,
                ];
            }
        }

        return ['state' => 'none', 'at' => null, 'until' => null];
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

    /**
     * The codes reserved for this customer that they can redeem right now.
     *
     * Usability is decided by DiscountCode::eligibilityError(), the same
     * method checkout uses, so the account can never advertise a code the
     * checkout would then refuse for a reason it already knows about. The one
     * exception is a free-relay code on a cart where relay delivery is already
     * free: that depends on the cart, so it can only be judged at checkout.
     *
     * Ordered by how soon they lapse, since a code expiring tomorrow matters
     * more than one created yesterday.
     *
     * @return Collection<int, DiscountCode>
     */
    public function usableDiscountCodes(): Collection
    {
        return $this->discountCodes()
            ->withCount([
                'orders as customer_usage_count' => fn (Builder $query) => $query->where('user_id', $this->id),
            ])
            ->reorder()
            ->orderByRaw('ends_at IS NULL, ends_at')
            ->orderByDesc('created_at')
            ->get()
            ->each(fn (DiscountCode $code) => $code->rememberCustomerUsage($this->id, (int) $code->customer_usage_count))
            ->filter(fn (DiscountCode $code): bool => $code->eligibilityError($this) === null)
            ->values();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class)->latest();
    }

    /**
     * Shop customers no admin has opened the profile of yet. Scoped the same
     * way the customers list is — externals come from manual orders and never
     * appear there, so they must not appear in its badge either.
     */
    public function scopeUnviewedByAdmin(Builder $query): void
    {
        $query->where('is_admin', false)
            ->where('external', false)
            ->whereNull('admin_viewed_at');
    }

    public function markViewedByAdmin(): void
    {
        if ($this->admin_viewed_at === null) {
            $this->admin_viewed_at = now();
            $this->save();
        }
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
