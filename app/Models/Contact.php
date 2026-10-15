<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToOrganization, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'contacts';

    protected array $ownerColumns = ['created_by'];

    protected $fillable = [
        'customer_id', 'full_name', 'position', 'email', 'phone', 'company', 'notes', 'created_by', 'is_demo',
    ];

    protected $casts = ['is_demo' => 'boolean'];

    /** Ngoài liên hệ tự tạo, nhân viên còn thấy liên hệ của khách hàng mình phụ trách. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->can('contacts.view_all')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('contacts.created_by', $user->id)
            ->orWhereHas('customer', fn ($c) => $c->where('assigned_to', $user->id)));
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->created_by === $user->id || (int) $this->customer?->assigned_to === $user->id;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable')->latest('occurred_at');
    }
}
