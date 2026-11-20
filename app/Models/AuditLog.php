<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One line of the audit log. Entries can be added but never edited or deleted from the app.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    public const CATEGORIES = [
        'auth' => 'Sign-ins',
        'user' => 'Accounts',
        'gown' => 'Gowns',
        'reservation' => 'Reservations',
        'audit' => 'Audit log',
        'report' => 'Reports',
    ];

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit log entries cannot be changed.');
        });

        static::deleting(function () {
            throw new LogicException('Audit log entries cannot be deleted.');
        });
    }

    /**
     * Search and filter used by the audit log page and its CSV export.
     *
     * @param array{search?: mixed, category?: mixed, from?: ?string, to?: ?string} $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : '';
        $category = $filters['category'] ?? null;

        return $query
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function (Builder $query) use ($like) {
                    $query->where('description', 'like', $like)
                        ->orWhere('user_name', 'like', $like)
                        ->orWhere('subject_label', 'like', $like)
                        ->orWhere('ip_address', 'like', $like);
                });
            })
            ->when(is_string($category) && isset(self::CATEGORIES[$category]), function (Builder $query) use ($category) {
                $query->where('action', 'like', $category . '.%');
            })
            // from and to are shop-time dates, already converted to the database clock by the controller
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('created_at', '<', $to));
    }

    /**
     * Plain-words name of the action, for the page.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'auth.login' => 'Signed in',
            'auth.logout' => 'Signed out',
            'auth.failed' => 'Failed sign-in',
            'auth.lockout' => 'Locked out',
            'user.registered' => 'Account created',
            'gown.created' => 'Gown added',
            'gown.updated' => 'Gown edited',
            'gown.deleted' => 'Gown deleted',
            'gown.restored' => 'Gown restored',
            'reservation.created' => 'Reservation created',
            'reservation.status_changed' => 'Status changed',
            'reservation.updated' => 'Reservation edited',
            'audit.exported' => 'Log exported',
            'report.exported' => 'Report exported',
            default => ucfirst(str_replace(['.', '_'], ' ', $this->action)),
        };
    }
}
