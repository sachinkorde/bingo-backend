<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;

/**
 * Central helper for dashboard authorization. Superadmin/admin see everything;
 * subadmins see only the resources their `permissions` list allows.
 */
class AdminAccess
{
    /** Resource key => human label (also used as the subadmin permission options). */
    public const RESOURCES = [
        'users' => 'Players',
        'gamers' => 'Profiles / KYC',
        'bank_details' => 'Bank Details',
        'wallets' => 'Wallets',
        'wallet_transactions' => 'Transactions (ledger)',
        'rounds' => 'Rounds',
        'bets' => 'Bets',
        'deposits' => 'Deposits',
        'withdrawals' => 'Withdrawals',
    ];

    public static function current(): ?Admin
    {
        return Auth::guard('admin')->user();
    }

    public static function canView(string $key): bool
    {
        return self::current()?->canViewResource($key) ?? false;
    }

    public static function canManageAdmins(): bool
    {
        return self::current()?->canManageAdmins() ?? false;
    }

    public static function resourceOptions(): array
    {
        return self::RESOURCES;
    }
}
