<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Subscription\Models\SubscriptionPayment;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Services\WalletTopUpService;
use Modules\WalletTransaction\Models\WalletTransaction;

class AdminWalletController extends Controller
{
    public function __construct(
        private WalletTopUpService $walletService,
    ) {}

    /**
     * List wallets (admin finance).
     * GET /api/admin/wallets
     */
    public function index(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        $wallets = Wallet::query()
            ->with(['user:id,name,email,phone,status'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->get('q'));
                $q->whereHas('user', function ($user) use ($term) {
                    $user->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $wallets->items(),
            'meta' => [
                'current_page' => $wallets->currentPage(),
                'last_page' => $wallets->lastPage(),
                'total' => $wallets->total(),
                'per_page' => $wallets->perPage(),
            ],
        ]);
    }

    /**
     * Create (or return existing) wallet for a user.
     * POST /api/admin/wallets
     * Body: { user_id: int, initial_balance?: number }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'initial_balance' => 'nullable|numeric|min:0|max:1000000',
        ]);

        $user = User::findOrFail((int) $data['user_id']);
        $existing = Wallet::where('user_id', $user->id)->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Wallet already exists for this user.',
                'created' => false,
                'data' => $existing->load('user:id,name,email'),
            ]);
        }

        $wallet = $this->walletService->createWallet($user);
        $initial = (float) ($data['initial_balance'] ?? 0);

        if ($initial > 0) {
            $wallet->update(['balance' => $initial]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Wallet created successfully.',
            'created' => true,
            'data' => $wallet->fresh()->load('user:id,name,email'),
        ], 201);
    }

    /**
     * Admin wallet dashboard — balance, subscription revenue, recent ledger.
     * GET /api/admin/wallets/dashboard
     */
    public function dashboard(Request $request)
    {
        $admin = $this->resolveAdmin($request);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'No admin user found.',
            ], 404);
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $admin->id],
            ['balance' => 0],
        );

        $settledStatuses = ['success', 'completed', 'paid'];

        $txBase = WalletTransaction::query()->where('wallet_id', $wallet->id);

        $stats = [
            'balance' => (float) $wallet->balance,
            'total_deposits' => (float) (clone $txBase)
                ->where('type', 'deposit')
                ->whereIn('status', $settledStatuses)
                ->sum('amount'),
            'total_withdrawals' => (float) (clone $txBase)
                ->where('type', 'withdraw')
                ->whereIn('status', $settledStatuses)
                ->sum('amount'),
            'subscription_revenue' => (float) SubscriptionPayment::query()
                ->where('method', 'wallet')
                ->where('status', 'paid')
                ->sum('amount'),
            'subscription_payments_count' => SubscriptionPayment::query()
                ->where('method', 'wallet')
                ->where('status', 'paid')
                ->count(),
        ];

        $recentTransactions = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
                'status' => $tx->status,
                'created_at' => $tx->created_at?->toIso8601String(),
            ]);

        $recentSubscriptions = SubscriptionPayment::query()
            ->with(['subscription.plan', 'subscription.subscriber.user'])
            ->where('method', 'wallet')
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get()
            ->map(function (SubscriptionPayment $payment) {
                $subscription = $payment->subscription;
                $subscriber = $subscription?->subscriber;
                $user = $subscriber?->user ?? null;

                return [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'plan_name' => $subscription?->plan?->name,
                    'subscriber_type' => $subscription?->plan?->subscriber_type,
                    'subscriber_name' => $user?->name,
                    'subscriber_email' => $user?->email,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => (float) $wallet->balance,
                ],
                'stats' => $stats,
                'recent_transactions' => $recentTransactions,
                'recent_subscription_payments' => $recentSubscriptions,
            ],
        ]);
    }

    /**
     * Paginated transactions for the admin wallet.
     * GET /api/admin/wallets/transactions
     */
    public function transactions(Request $request)
    {
        $admin = $this->resolveAdmin($request);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'No admin user found.',
            ], 404);
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $admin->id],
            ['balance' => 0],
        );

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        $transactions = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->get('type')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
            ],
        ]);
    }

    /**
     * Ensure the first admin account has a wallet (needed for subscription credits).
     * POST /api/admin/wallets/ensure-admin
     */
    public function ensureAdminWallet(Request $request)
    {
        $admin = $this->resolveAdmin($request);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'No admin user found.',
            ], 404);
        }

        $wallet = $this->walletService->createWallet($admin);

        return response()->json([
            'success' => true,
            'message' => 'Admin wallet is ready.',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
                'wallet' => $wallet,
            ],
        ]);
    }

    private function resolveAdmin(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        return User::role('admin')->orderBy('id')->first();
    }
}
