<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Implementing MustVerifyEmail is what actually arms Laravel's `verified`
 * middleware — the trait on the base class only provides the methods. Without
 * the interface every `verified` route guard silently passes everyone through.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CUSTOMER = 'customer';

    /**
     * The attributes that are mass assignable.
     *
     * Note: `role` is intentionally NOT mass assignable so it can never be
     * set through registration or profile updates (privilege escalation).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    /**
     * Determine if the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * The orders placed by the user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    /**
     * Determine whether the user has a paid order containing the given book.
     *
     * Ownership is derived from what was actually paid for rather than stored
     * on a pivot, so it cannot drift out of sync with the order history — and
     * a refund revokes access for free.
     */
    public function hasPurchased(Book $book): bool
    {
        return $this->orders()
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', fn ($query) => $query->where('book_id', $book->id))
            ->exists();
    }

    /**
     * The shape shared with the frontend on every Inertia response. Listing
     * the fields explicitly means a new column is never leaked by accident.
     *
     * @return array<string, mixed>
     */
    public function toInertiaArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_admin' => $this->isAdmin(),
            'email_verified_at' => $this->email_verified_at,
        ];
    }
}
