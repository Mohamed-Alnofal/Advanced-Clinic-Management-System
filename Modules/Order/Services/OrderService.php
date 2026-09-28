<?php

namespace Modules\Order\Services;

// use Modules\Order\Services\FileService;
use Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use Modules\Drug\Models\Drug;
use Modules\Order\app\Events\OrderStatusChanged;
use Modules\Order\Events\OrderCreated;
use Illuminate\Validation\ValidationException;
use Modules\Payment\PaymentFactory;
use Modules\Wallet\Models\Wallet;
use Modules\WalletTransaction\Models\WalletTransaction;
use Modules\Pharmacy\Models\Pharmacy;
use Modules\Pharmacy\Models\PharmacyDrug;

class OrderService
{
        public function __construct(private FileService $fileService)
    {
    }

    public function create(array $data): Order
    {
        // بدء المعاملة (Transaction) لضمان تنفيذ كل العمليات بنجاح أو التراجع عنها
        DB::beginTransaction();

        try {
            // ✅ التحقق من وجود مستخدم مسجل الدخول
            $user = auth()->user();
            
            if (!$user) {
                throw new \Exception('يجب تسجيل الدخول أولاً');
            }

            $totalPrice = 0;

            // 🧾 إنشاء الطلب الأساسي
            $pharmacy = Pharmacy::with('pharmacist')->findOrFail($data['pharmacy_id']);

            $owner = $pharmacy->pharmacist;
            if (! $owner || ! $owner->hasActiveSubscription()) {
                throw ValidationException::withMessages([
                    'pharmacy_id' => ['هذه الصيدلية غير مشتركة حالياً ولا يمكن استقبال الطلبات.'],
                ]);
            }

            // Prevent rapid duplicate pending orders (same user + pharmacy + same drugs within 2 min).
            $requestedDrugIds = collect($data['items'] ?? [])
                ->map(fn ($item) => (int) ($item['drug_id'] ?? 0))
                ->filter()
                ->sort()
                ->values()
                ->all();

            $recentDuplicate = Order::query()
                ->where('user_id', $user->id)
                ->where('pharmacy_id', $pharmacy->id)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->with('items')
                ->latest('id')
                ->first();

            if ($recentDuplicate) {
                $recentDrugIds = $recentDuplicate->items
                    ->pluck('drug_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                if ($recentDrugIds === $requestedDrugIds) {
                    throw ValidationException::withMessages([
                        'order' => ['طلب مشابه قيد المعالجة. انتظر قليلاً قبل إعادة الطلب.'],
                    ]);
                }
            }

            // Pharmacy orders require wallet funds up front (professional prepaid flow).
            $paymentStatus = $data['payment_status'] ?? 'paid';
            if (! in_array($paymentStatus, ['paid', 'pending', 'cash_on_delivery'], true)) {
                $paymentStatus = 'paid';
            }

            // Soft pre-check wallet when paying from wallet (final lock happens below).
            if ($paymentStatus === 'paid') {
                $walletPreview = Wallet::where('user_id', $user->id)->first();
                if (! $walletPreview) {
                    throw ValidationException::withMessages([
                        'payment_status' => ['المحفظة غير موجودة. أنشئ محفظة ثم اشحن رصيدك.'],
                    ]);
                }
            }

        $order = Order::create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacy->id,
            'pharmacist_id' => null,
            'status' => 'pending',
            'payment_status' => $paymentStatus === 'paid' ? 'pending' : $paymentStatus,
            'total_price' => 0,
        ]);

            /*
            |-----------------------------------------
            | 💊 معالجة الأدوية (ITEMS PROCESSING)
            | Batch-load drugs + pharmacy stock (no N+1).
            |-----------------------------------------
            */
            $items = $data['items'];
            $drugIds = array_values(array_unique(array_map(
                fn ($item) => (int) $item['drug_id'],
                $items
            )));

            $drugs = Drug::with(['prices', 'products'])
                ->whereIn('id', $drugIds)
                ->get()
                ->keyBy('id');

            if ($drugs->count() !== count($drugIds)) {
                $missing = array_diff($drugIds, $drugs->keys()->all());
                throw new \Exception('أدوية غير موجودة: '.implode(', ', $missing));
            }

            // Lock stock rows for this pharmacy in one query (isolation under concurrency).
            $pharmacyDrugs = PharmacyDrug::where('pharmacy_id', $pharmacy->id)
                ->whereIn('drug_id', $drugIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('drug_id');

            foreach ($items as $item) {
                $drugId = (int) $item['drug_id'];
                $drug = $drugs->get($drugId);
                $pharmacyDrug = $pharmacyDrugs->get($drugId);

                if (! $pharmacyDrug) {
                    throw new \Exception("الدواء غير متوفر في هذه الصيدلية: {$drug->name}");
                }

                // OTC = over the counter (no Rx). Prescription required when NOT OTC.
                $isOtc = (bool) optional($drug->products->first())->otc;
                $requiresPrescription = ! $isOtc;

                if ($requiresPrescription && empty($item['prescription_file']) && empty($data['prescription_id'] ?? null)) {
                    throw ValidationException::withMessages([
                        'items' => ["الدواء {$drug->name} يحتاج وصفة طبية"],
                    ]);
                }

                $prescriptionPath = null;
                if (! empty($item['prescription_file'])) {
                    $prescriptionPath = $this->fileService->upload(
                        $item['prescription_file'],
                        'prescriptions'
                    );
                }

                $price = (float) $pharmacyDrug->price;
                $qty = (int) $item['quantity'];

                // Atomic stock claim — prevents last-bottle double sell
                $affected = PharmacyDrug::where('id', $pharmacyDrug->id)
                    ->where('quantity', '>=', $qty)
                    ->decrement('quantity', $qty);

                if ($affected === 0) {
                    throw new \Exception("الكمية غير متوفرة للدواء: {$drug->name}");
                }

                $totalPrice += $price * $qty;

                $order->items()->create([
                    'drug_id' => $drug->id,
                    'quantity' => $qty,
                    'price' => $price,
                    'prescription_file' => $prescriptionPath,
                ]);
            }

            /*
            |-----------------------------------------
            | 💰 تحديث إجمالي السعر في الطلب
            |-----------------------------------------
            */
            $order->update([
                'total_price' => $totalPrice
            ]);

            /*
            |-----------------------------------------
            | 💳 الدفع من المحفظة (افتراضي للطلبات المدفوعة)
            |-----------------------------------------
            */
            if ($paymentStatus === 'paid') {

                $wallet = Wallet::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    throw ValidationException::withMessages([
                        'payment_status' => ['المحفظة غير موجودة. أنشئ محفظة ثم اشحن رصيدك.'],
                    ]);
                }

                if ($wallet->balance < $totalPrice) {
                    throw ValidationException::withMessages([
                        'payment_status' => ['رصيد المحفظة غير كافي. اشحن محفظتك ثم أعد المحاولة.'],
                    ]);
                }

                $wallet->decrement('balance', $totalPrice);

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'amount' => $totalPrice,
                    'type' => 'withdraw',
                    'status' => 'completed',
                ]);

                $order->update([
                    'payment_status' => 'paid',
                    'total_price' => $totalPrice,
                ]);
            }

            // تأكيد المعاملة (حفظ كل التغييرات)
            DB::commit();

            // إعادة الطلب مع تحميل العلاقات المطلوبة
            return $order->load(['items.drug', 'pharmacy.medicalFacility']);

        } catch (\Exception $e) {
            // في حالة حدوث خطأ، التراجع عن كل التغييرات
            DB::rollBack();
            throw $e;
        }
    }



public function update(int $orderId, array $data): Order
{
    DB::beginTransaction();

    try {
        $order = Order::where('id', $orderId)
            ->where('status', 'pending')
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            throw new \Exception("Order not found or cannot be updated");
        }

        // 🔄 إعادة المخزون القديم (من pharmacy_drug)
        foreach ($order->items as $oldItem) {
            $pharmacyDrug = PharmacyDrug::where('pharmacy_id', $order->pharmacy_id)
                ->where('drug_id', $oldItem->drug_id)
                ->first();
                
            if ($pharmacyDrug) {
                $pharmacyDrug->increment('quantity', $oldItem->quantity);
            }
        }

        // 🗑️ حذف items القديمة
        $order->items()->delete();

        $totalPrice = 0;

        /*
        |-----------------------------------------
        | 💊 إعادة بناء ITEMS
        |-----------------------------------------
        */
        foreach ($data['items'] as $item) {
            $drug = Drug::with(['prices', 'products'])
                ->findOrFail($item['drug_id']);

            // ✅ التحقق من وجود الدواء في الصيدلية
            $pharmacyDrug = PharmacyDrug::where('pharmacy_id', $order->pharmacy_id)
                ->where('drug_id', $drug->id)
                ->first();

            if (!$pharmacyDrug) {
                throw new \Exception("الدواء غير متوفر في هذه الصيدلية: {$drug->name}");
            }

            $isOtc = (bool) optional($drug->products->first())->otc;
            $requiresPrescription = ! $isOtc;

            // ❌ تحقق الوصفة
            if ($requiresPrescription && empty($item['prescription_file'])) {
                throw ValidationException::withMessages([
                    'items' => ["الدواء {$drug->name} يحتاج وصفة طبية"]
                ]);
            }

            // 📁 رفع الوصفة
            $prescriptionPath = null;
            if (!empty($item['prescription_file'])) {
                $prescriptionPath = $this->fileService->upload(
                    $item['prescription_file'],
                    'prescriptions'
                );
            }

            // 💰 السعر من pharmacy_drug
            $price = (float) $pharmacyDrug->price;

            $qty = (int) $item['quantity'];
            $affected = PharmacyDrug::where('id', $pharmacyDrug->id)
                ->where('quantity', '>=', $qty)
                ->decrement('quantity', $qty);

            if ($affected === 0) {
                throw new \Exception("الكمية غير متوفرة للدواء: {$drug->name}");
            }

            $subtotal = $price * $qty;
            $totalPrice += $subtotal;

            // 💊 إنشاء item جديد
            $order->items()->create([
                'drug_id' => $drug->id,
                'quantity' => $qty,
                'price' => $price,
                'prescription_file' => $prescriptionPath,
            ]);
        }

        /*
        |-----------------------------------------
        | 🧾 تحديث بيانات الطلب
        |-----------------------------------------
        */
        $order->update([
            'delivery_address' => $data['delivery_address'] ?? $order->delivery_address,
            'latitude' => $data['latitude'] ?? $order->latitude,
            'longitude' => $data['longitude'] ?? $order->longitude,
            'payment_status' => $data['payment_status'] ?? $order->payment_status,
            'total_price' => $totalPrice,
        ]);

        /*
        |-----------------------------------------
        | 💳 PAYMENT HANDLING (إذا كان Paid)
        |-----------------------------------------
        */
        if (($data['payment_status'] ?? $order->payment_status) === 'paid') {
            $wallet = Wallet::where('user_id', auth()->id())
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \Exception("المحفظة غير موجودة");
            }

            if ($wallet->balance < $totalPrice) {
                throw new \Exception("رصيد المحفظة غير كافي");
            }

            $wallet->decrement('balance', $totalPrice);
        }

        DB::commit();

        return $order;

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

public function myOrders()
{
    return Order::with(['items.drug.prices', 'pharmacy.medicalFacility'])
        ->where('user_id', auth()->id())
        ->latest()
        ->get();
}

public function show($id): Order
{
    $order = Order::with(['items.drug.prices','pharmacy.medicalFacility', 'user'])
        ->where('id', $id)
        ->where('user_id', auth()->id())
        ->first();

    if (!$order) {
        throw new \Exception('Order not found');
    }

    return $order;
}


public function cancel(Order $order): Order
{
    DB::beginTransaction();

    try {
        if ($order->status !== 'pending') {
            throw new \Exception('لا يمكن إلغاء الطلب بعد معالجته');
        }

        // 🔁 إعادة المخزون إلى pharmacy_drug
        foreach ($order->items as $item) {
            $pharmacyDrug = PharmacyDrug::where('pharmacy_id', $order->pharmacy_id)
                ->where('drug_id', $item->drug_id)
                ->first();
                
            if ($pharmacyDrug) {
                $pharmacyDrug->increment('quantity', $item->quantity);
            }
        }

        // 💰 استرجاع المال (Refund)
        if ($order->payment_status === 'paid') {
            $wallet = Wallet::where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \Exception("المحفظة غير موجودة");
            }

            $wallet->increment('balance', $order->total_price);
        }

        // ❌ تحديث الحالة
        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'refunded'  // ✅ تغيير من 'paid' إلى 'refunded'
        ]);

        DB::commit();

        return $order->fresh(['items.drug', 'pharmacy']);

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

   public function pharmacistOrders(int $pharmacistId)
{
    $orders = Order::with(['items.drug', 'pharmacy.medicalFacility', 'user'])
        ->whereHas('pharmacy', function($q) use ($pharmacistId) {
            $q->where('pharmacist_id', $pharmacistId);
        })
        ->whereIn('status', ['pending', 'preparing', 'accepted', 'ready', 'on_the_way', 'rejected'])
        ->latest()
        ->paginate(50);

    $orders->getCollection()->transform(function ($order) {
        return [
            'id' => $order->id,
            'order_id' => $order->id,
            'customer_id' => $order->user_id,
            'customer_name' => $order->user->name ?? null,
            'pharmacy_name' => $order->pharmacy->medicalFacility->name ?? null,
            'delivery_address' => $order->delivery_address,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total_price' => $order->total_price,
            'total_quantity' => $order->items->sum('quantity'),
            'items' => $order->items->map(function ($item) {
                return [
                    'drug_id' => $item->drug_id,
                    'drug_name' => $item->drug->name ?? null,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ];
            }),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    });

    return $orders;
}

     public function startProcessing($orderId)
    {
        $pharmacist = auth()->user()->pharmacist;

        if (! $pharmacist) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $pharmacist->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Active subscription required to process orders.',
                'code' => 'subscription_required',
            ], 403);
        }

        $pharmacistId = $pharmacist->id;

        $order = Order::where('id', $orderId)
            ->where('status', 'pending')
            ->whereHas('pharmacy', function ($q) use ($pharmacistId) {
                $q->where('pharmacist_id', $pharmacistId);
            })
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not available'], 404);
        }

        $order->update([
            'status' => 'preparing',
            'pharmacist_id' => $pharmacistId,
            'locked_at' => now(),
        ]);

        return response()->json([
            'message' => 'Order locked successfully',
            'order_id' => $order->id,
            'status' => $order->status,
        ]);
    }

    
public function acceptOrder($orderId)
{
    $pharmacist = auth()->user()->pharmacist;
    $pharmacistUserId = auth()->id();

    if (! $pharmacist) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    if (! $pharmacist->hasActiveSubscription()) {
        return response()->json([
            'message' => 'Active subscription required to accept orders.',
            'code' => 'subscription_required',
        ], 403);
    }

    $order = Order::where('id', $orderId)
        ->where('status', 'preparing')
        ->where('pharmacist_id', $pharmacist->id)
        ->first();

    if (!$order) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    DB::beginTransaction();

    try {

        $amount = $order->total_price;
        $alreadyPaid = $order->payment_status === 'paid';

        // 💳 محفظة المستخدم
        $userWallet = Wallet::where('user_id', $order->user_id)
            ->lockForUpdate()
            ->first();

        if (! $alreadyPaid) {
            if (! $userWallet) {
                throw new \Exception('User wallet not found');
            }

            if ($userWallet->balance < $amount) {
                throw new \Exception('Insufficient balance');
            }
        }

        // 💊 محفظة الصيدلي
        $pharmacistWallet = Wallet::firstOrCreate(
            ['user_id' => $pharmacistUserId],
            ['balance' => 0],
        );

        if (! $alreadyPaid && $userWallet) {
            $userWallet->decrement('balance', $amount);
        }

        $pharmacistWallet->increment('balance', $amount);

        $order->update([
            'status' => 'accepted',
            'payment_status' => 'paid',
            'processed_at' => now(),
        ]);

        DB::commit();

        event(new OrderStatusChanged($order, 'accepted'));

        return response()->json([
            'message' => 'Order accepted and payment transferred'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'message' => $e->getMessage()
        ], 400);
    }
}

public function rejectOrder($orderId)
{
    DB::beginTransaction();

    try {
        $pharmacist = auth()->user()->pharmacist;
        
        if (!$pharmacist) {
            throw new \Exception('Unauthorized', 403);
        }
        
        $order = Order::with(['items.drug', 'user.wallet'])
            ->where('id', $orderId)
            ->where('status', 'preparing')
            ->whereHas('pharmacy', function($q) use ($pharmacist) {
                $q->where('pharmacist_id', $pharmacist->id);
            })
            ->lockForUpdate()
            ->first();

        if (!$order) {
            throw new \Exception('Unauthorized', 403);
        }

        // 🔁 إعادة المخزون إلى pharmacy_drug
        foreach ($order->items as $item) {
            $pharmacyDrug = PharmacyDrug::where('pharmacy_id', $order->pharmacy_id)
                ->where('drug_id', $item->drug_id)
                ->first();
                
            if ($pharmacyDrug) {
                $pharmacyDrug->increment('quantity', $item->quantity);
            }
        }

        // 💰 استرجاع المال (Refund)
        if ($order->payment_status === 'paid') {
            $wallet = Wallet::where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \Exception("المحفظة غير موجودة");
            }

            $wallet->increment('balance', $order->total_price);
        }
        
        $order->update([
            'status' => 'rejected',
            'payment_status' => 'refunded',
            'processed_at' => now(),
        ]);

        DB::commit();

        return $order;

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
}