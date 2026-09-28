<?php

namespace Modules\Order\Http\Controllers;

// use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use Modules\Order\Models\Order;
use Modules\Order\Requests\StoreOrderRequest;
use Modules\Order\Services\FileService;
use Modules\Order\Services\OrderService;
use Modules\Order\Transformers\OrderResource;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService ,  private FileService $fileService)
    {
        $this->orderService = $orderService;
    }
    

    /**
     * Show the form for creating a new resource.
     */
public function index()
{
        $order = $this->orderService->myOrders();

    return OrderResource::collection($order);
}

public function store(StoreOrderRequest $request)
{
    $data = $request->all();

    // دمج الملفات داخل items بشكل صحيح
    if ($request->has('items')) {
        foreach ($request->items as $index => $item) {

            if ($request->hasFile("items.$index.prescription_file")) {
                $data['items'][$index]['prescription_file'] =
                    $request->file("items.$index.prescription_file");
            }
        }
    }

    try {
        $order = $this->orderService->create($data);
    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (\Throwable $e) {
        $message = $e->getMessage() ?: 'Could not create order.';
        $status = str_contains(mb_strtolower($message), 'رصيد') || str_contains(mb_strtolower($message), 'محفظ')
            ? 422
            : 400;

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $status === 422 ? 'insufficient_balance' : 'order_failed',
        ], $status);
    }

    return new OrderResource(
        $order->load(['items.drug', 'pharmacist'])
    );
}

public function update(Request $request, $id)
{
    $order = Order::with('items')
        ->where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

  $data = $request->all();

if ($request->has('items')) {
    foreach ($request->file('items', []) as $index => $item) {
        if (isset($item['prescription_file'])) {
            $data['items'][$index]['prescription_file'] = $item['prescription_file'];
        }
    }
}

    // $order = $this->orderService->update($order, $data);
    $this->orderService->update($order->id, $request->all());

    return new OrderResource($order);
}


public function show($id)
{
        $order = $this->orderService->show($id);

    return new OrderResource($order);
}

public function cancel($id)
{
    $order = Order::with('items')
        ->where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    $order = $this->orderService->cancel($order);

   return new OrderResource($order);
}

   
    public function pharmacistOrders()
        {
            $pharmacist = auth()->user()->pharmacist;

            if (! $pharmacist) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $orders = $this->orderService
                ->pharmacistOrders($pharmacist->id);

            return response()->json($orders);
        }

    public function startProcessing($id)
    {
        return $this->orderService->startProcessing($id);
    }

    // accept
    public function accept($id)
    {
        return $this->orderService->acceptOrder($id);
    }

    // reject
    public function reject($id)
    {
        return $this->orderService->rejectOrder($id);
    }

    }