<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '84') && strlen($digits) >= 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits ?: null;
    }

    /** Tìm khách hàng có thể trùng theo email hoặc số điện thoại. */
    public function findDuplicates(?string $email, ?string $phone, ?int $exceptId = null): Collection
    {
        $email = $email ? mb_strtolower(trim($email)) : null;
        $phone = self::normalizePhone($phone);

        if (! $email && ! $phone) {
            return collect();
        }

        return Customer::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->orWhereRaw('LOWER(email) = ?', [$email]);
                }
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->with('assignee:id,name')
            ->limit(5)
            ->get(['id', 'code', 'name', 'email', 'phone', 'assigned_to']);
    }

    public function save(Customer $customer, array $data, User $actor): Customer
    {
        $tags = $data['tags'] ?? null;
        unset($data['tags'], $data['confirm_duplicate']);
        $data['phone'] = self::normalizePhone($data['phone'] ?? null);
        $data['email'] = isset($data['email']) ? mb_strtolower(trim($data['email'])) : null;

        $previousAssignee = $customer->assigned_to;

        $customer = DB::transaction(function () use ($customer, $data, $actor, $tags) {
            if (! $customer->exists) {
                $data['created_by'] = $actor->id;
            }
            $data['updated_by'] = $actor->id;
            $customer->fill($data)->save();
            if ($tags !== null) {
                $customer->tags()->sync($tags);
            }

            return $customer;
        });

        if ($customer->assigned_to && $customer->assigned_to !== $previousAssignee && $customer->assigned_to !== $actor->id) {
            CrmNotification::send($customer->assignee, 'assigned', 'Bạn được phân công phụ trách khách hàng '.$customer->name,
                route('customers.show', $customer), 'bi-people');
        }

        return $customer;
    }
}
