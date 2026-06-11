<?php

namespace App\Services;


use Exception;
use App\Enums\Ask;
use App\Models\User;
use App\Enums\Status;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Product;
use App\Enums\OrderType;
use App\Enums\PaymentGateway;
use App\Models\StockTax;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Transaction;
use App\Events\SendOrderSms;
use Illuminate\Http\Request;
use App\Events\SendOrderMail;
use App\Events\SendOrderPush;
use App\Libraries\AppLibrary;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\PosOrderRequest;
use App\Http\Requests\OrderItemsUpdateRequest;
use App\Libraries\QueryExceptionLibrary;
use App\Http\Requests\OrderStatusRequest;
use App\Http\Requests\PaymentStatusRequest;

class OrderService
{
    public object $order;
    protected array $orderFilter = [
        'order_serial_no',
        'user_id',
        'total',
        'order_type',
        'order_datetime',
        'payment_method',
        'payment_status',
        'status',
        'active',
        'source'
    ];

    protected array $exceptFilter = [
        'excepts'
    ];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_by') ?? 'desc';

            return Order::with('transaction', 'orderProducts')->where(function ($query) use ($requests) {
                if (isset($requests['from_date']) && isset($requests['to_date'])) {
                    $first_date = Date('Y-m-d', strtotime($requests['from_date']));
                    $last_date  = Date('Y-m-d', strtotime($requests['to_date']));
                    $query->whereDate('order_datetime', '>=', $first_date)->whereDate(
                        'order_datetime',
                        '<=',
                        $last_date
                    );
                }
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->orderFilter)) {
                        if ($key === "status") {
                            $query->where($key, (int)$request);
                        } else if ($key === 'payment_method' && (int)$request < 0) {
                            $query->where('pos_payment_method', abs($request));
                        } else if ($key === 'source') {
                            $query->where($key, $request);
                        } else {
                            $query->where($key, 'like', '%' . $request . '%');
                        }
                    }

                    if (in_array($key, $this->exceptFilter)) {
                        $explodes = explode('|', $request);
                        if (is_array($explodes)) {
                            foreach ($explodes as $explode) {
                                $query->where('order_type', '!=', $explode);
                            }
                        }
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }


    /**
     * @throws Exception
     */
    public function myOrder(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_by') ?? 'desc';

            return Order::where('order_type', "!=", OrderType::POS)->where(function ($query) use ($requests) {
                $query->where('user_id', Auth::id());
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->orderFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
                    }
                    if (in_array($key, $this->exceptFilter)) {
                        $explodes = explode('|', $request);
                        if (is_array($explodes)) {
                            foreach ($explodes as $explode) {
                                $query->where('status', '!=', $explode);
                            }
                        }
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function userOrder(PaginateRequest $request, User $user)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_by') ?? 'desc';

            return Order::where(function ($query) use ($requests, $user) {
                $query->where('user_id', $user->id);
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->orderFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
                    }
                    if (in_array($key, $this->exceptFilter)) {
                        $explodes = explode('|', $request);
                        if (is_array($explodes)) {
                            foreach ($explodes as $explode) {
                                $query->where('status', '!=', $explode);
                            }
                        }
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }


    /**
     * @throws Exception
     */
    public function posOrderStore(PosOrderRequest $request): object
    {
        try {
            DB::transaction(function () use ($request) {
                $this->order = new Order();
                $this->fillPosOrder($this->order, $request, true);
                $this->order->save();
                $this->syncPosOrderProducts($this->order, json_decode($request->products), true);

                $this->order->order_serial_no = date('dmy') . $this->order->id;
                $this->order->save();
            });
            return $this->order;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function posOrderUpdate(Order $order, PosOrderRequest $request): Order
    {
        try {
            DB::transaction(function () use ($order, $request) {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);

                if ((int)$order->order_type !== OrderType::POS) {
                    throw new Exception('يمكن تعديل فواتير نقاط البيع فقط.', 422);
                }

                if (in_array((int)$order->status, [OrderStatus::CANCELED, OrderStatus::REJECTED], true)) {
                    throw new Exception('لا يمكن تعديل فاتورة ملغية أو مرفوضة.', 422);
                }

                app(OrderStockService::class)->releaseOrderStocks($order);
                $this->deleteOrderProductStocks($order);
                $this->fillPosOrder($order, $request, false, $order->user_id);
                $this->syncPosOrderProducts($order, json_decode($request->products), true);
                $order->save();

                $this->order = $order->fresh();
            });

            return $this->order;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function onlineOrderUpdate(Order $order, OrderItemsUpdateRequest $request): Order
    {
        try {
            DB::transaction(function () use ($order, $request) {
                $order = Order::with('paymentMethod', 'transaction')->lockForUpdate()->findOrFail($order->id);

                $this->assertOnlineOrderCanBeEdited($order);

                app(OrderStockService::class)->releaseOrderStocks($order);
                $this->deleteOrderProductStocks($order);

                $order->subtotal = $request->subtotal;
                $order->discount = $request->discount ?? 0;
                $order->tax = $request->tax;
                $order->total = $request->total;
                $order->save();

                $stockStatus = (int)$order->active === Ask::YES ? Status::ACTIVE : Status::INACTIVE;
                $this->syncPosOrderProducts($order, json_decode($request->products), true, $stockStatus);
                $this->recalculateOnlineOrderPayments($order);

                $order->save();
                $this->order = $order->fresh();
            });

            return $this->order;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(Order $order, $auth = false): Order|array
    {
        try {
            if ($auth) {
                if ($order->user_id == Auth::user()->id) {
                    return $order;
                } else {
                    return [];
                }
            } else {
                return $order;
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function orderDetails(User $user, Order $order): Order|array
    {
        try {
            if ($order->user_id == $user->id) {
                return $order;
            } else {
                return [];
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function changeStatus(Order $order, OrderStatusRequest $request, $auth = false): Order|array
    {
        try {
            if ($auth) {
                if ($order->user_id == Auth::user()->id) {
                    if ($request->reason) {
                        $order->reason = $request->reason;
                    }

                    if ($request->status == OrderStatus::REJECTED || $request->status == OrderStatus::CANCELED) {
                        if ($order->transaction) {
                            app(PaymentService::class)->cashBack(
                                $order,
                                'credit',
                                rand(111111111111111, 99999999999999)
                            );
                        }

                        app(OrderStockService::class)->releaseOrderStocks($order);
                    }
                    SendOrderMail::dispatch(['order_id' => $order->id, 'status' => $request->status]);
                    SendOrderSms::dispatch(['order_id' => $order->id, 'status' => $request->status]);
                    SendOrderPush::dispatch(['order_id' => $order->id, 'status' => $request->status]);
                    $order->status = $request->status;
                    $order->save();
                }
            } else {
                if ($request->status == OrderStatus::REJECTED || $request->status == OrderStatus::CANCELED) {
                    $request->validate([
                        'reason' => 'required|max:700',
                    ]);

                    if ($request->reason) {
                        $order->reason = $request->reason;
                    }

                    if ($order->transaction) {
                        app(PaymentService::class)->cashBack(
                            $order,
                            'credit',
                            rand(111111111111111, 99999999999999)
                        );
                    }

                    app(OrderStockService::class)->releaseOrderStocks($order);
                }
                SendOrderMail::dispatch(['order_id' => $order->id, 'status' => $request->status]);
                SendOrderSms::dispatch(['order_id' => $order->id, 'status' => $request->status]);
                SendOrderPush::dispatch(['order_id' => $order->id, 'status' => $request->status]);
                $order->status = $request->status;
                $order->save();
            }
            return $order;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function changePaymentStatus(Order $order, PaymentStatusRequest $request, $auth = false): Order|array
    {
        try {
            if ($auth) {
                if ($order->user_id == Auth::user()->id) {
                    $order->payment_status = $request->payment_status;
                    $order->save();
                    return $order;
                } else {
                    return [];
                }
            } else {
                $order->payment_status = $request->payment_status;
                $order->save();
                return $order;
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(Order $order): void
    {
        try {
            DB::transaction(function () use ($order) {
                if ($order?->orderProducts) {
                    $stockIds = $order?->orderProducts->pluck('id');
                    if (!blank($stockIds)) {
                        StockTax::whereIn('stock_id', $stockIds)->delete();
                    }
                    $order?->orderProducts()->delete();
                }
                $order->delete();
            });
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function salesReportOverview(Request $request)
    {
        try {
            $requests    = $request->all();
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_by') ?? 'desc';

            $orders = Order::with('transaction', 'orderProducts')->where(function ($query) use ($requests) {
                if (isset($requests['from_date']) && isset($requests['to_date'])) {
                    $first_date = Date('Y-m-d', strtotime($requests['from_date']));
                    $last_date  = Date('Y-m-d', strtotime($requests['to_date']));
                    $query->whereDate('order_datetime', '>=', $first_date)->whereDate(
                        'order_datetime',
                        '<=',
                        $last_date
                    );
                }
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->orderFilter)) {
                        if ($key === "status") {
                            $query->where($key, (int)$request);
                        } else if ($key === 'payment_method' && (int)$request < 0) {
                            $query->where('pos_payment_method', abs($request));
                        } else if ($key === 'source') {
                            $query->where($key, $request);
                        } else {
                            $query->where($key, 'like', '%' . $request . '%');
                        }
                    }

                    if (in_array($key, $this->exceptFilter)) {
                        $explodes = explode('|', $request);
                        if (is_array($explodes)) {
                            foreach ($explodes as $explode) {
                                $query->where('order_type', '!=', $explode);
                            }
                        }
                    }
                }
            })->orderBy($orderColumn, $orderType)->get();
            $salesReportArray = [];

            $salesReportArray['total_orders'] = $orders->count();
            $salesReportArray['total_earnings'] = AppLibrary::currencyAmountFormat($orders->sum('total'));
            $salesReportArray['total_discounts'] = AppLibrary::currencyAmountFormat($orders->sum('discount'));
            $salesReportArray['total_shipping_charges'] = AppLibrary::currencyAmountFormat($orders->sum('shipping_charge'));

            return $salesReportArray;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    protected function fillPosOrder(Order $order, PosOrderRequest $request, bool $isNew = false, ?int $userId = null): void
    {
        $order->fill($request->validated() + [
            'user_id'                => $userId ?? $request->customer_id,
            'payment_method'         => PaymentGateway::CASH_ON_DELIVERY,
            'payment_status'         => PaymentStatus::PAID,
            'wallet_paid_amount'     => 0,
            'cash_on_delivery_amount'=> (float)$request->total,
            'active'                 => Ask::YES,
        ]);

        if ($isNew) {
            $order->status = OrderStatus::CONFIRMED;
            $order->order_datetime = now();
        }
    }

    protected function syncPosOrderProducts(Order $order, iterable $products, bool $lockStockRows = false, int $stockStatus = Status::ACTIVE): void
    {
        if (blank($products)) {
            return;
        }

        app(OrderStockService::class)->assertProductsAvailable($products, $lockStockRows);

        foreach ($products as $product) {
            $stock = Stock::create([
                'product_id'      => $product->product_id,
                'model_type'      => Order::class,
                'model_id'        => $order->id,
                'item_type'       => $product->variation_id > 0 ? ProductVariation::class : Product::class,
                'item_id'         => $product->variation_id > 0 ? $product->variation_id : $product->product_id,
                'variation_names' => $product->variation_names,
                'sku'             => $product->sku,
                'price'           => $product->price,
                'quantity'        => -$product->quantity,
                'discount'        => $product->discount,
                'tax'             => number_format($product->total_tax, env('CURRENCY_DECIMAL_POINT'), '.', ''),
                'subtotal'        => $product->subtotal,
                'total'           => $product->total,
                'status'          => $stockStatus,
            ]);

            if ($product->taxes) {
                $productTaxArray = [];
                foreach ($product->taxes as $index => $tax) {
                    $productTaxArray[$index] = [
                        'stock_id'   => $stock->id,
                        'product_id' => $product->product_id,
                        'tax_id'     => $tax->id,
                        'name'       => $tax->name,
                        'code'       => $tax->code,
                        'tax_rate'   => $tax->tax_rate,
                        'tax_amount' => $tax->tax_amount,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
                StockTax::insert($productTaxArray);
            }
        }
    }

    protected function deleteOrderProductStocks(Order $order): void
    {
        if (!$order->orderProducts || $order->orderProducts->isEmpty()) {
            return;
        }

        $stockIds = $order->orderProducts->pluck('id');
        if ($stockIds->isNotEmpty()) {
            StockTax::whereIn('stock_id', $stockIds)->delete();
        }
        $order->orderProducts()->delete();
    }

    protected function assertOnlineOrderCanBeEdited(Order $order): void
    {
        if ((int)$order->order_type === OrderType::POS) {
            throw new Exception('هذه العملية مخصصة للطلبات أونلاين فقط.', 422);
        }

        if ((int)$order->status === OrderStatus::DELIVERED) {
            throw new Exception('لا يمكن تعديل طلب تم تسليمه.', 422);
        }

        if (in_array((int)$order->status, [OrderStatus::CANCELED, OrderStatus::REJECTED], true)) {
            throw new Exception('لا يمكن تعديل طلب ملغي أو مرفوض.', 422);
        }
    }

    protected function recalculateOnlineOrderPayments(Order $order): void
    {
        $paymentSlug = $order->paymentMethod?->slug;
        $wasPaid = (int)$order->payment_status === PaymentStatus::PAID;

        if ($paymentSlug === 'cashondelivery') {
            $order->wallet_paid_amount = 0;
            $order->cash_on_delivery_amount = $wasPaid ? 0 : (float)$order->total;
            $order->payment_status = $wasPaid ? PaymentStatus::PAID : PaymentStatus::UNPAID;
            return;
        }

        if ($paymentSlug === 'credit') {
            $existingWalletDebit = app(WalletService::class)->getDebitedAmountForOrder($order);
            if ($existingWalletDebit > 0 || (float)$order->wallet_paid_amount > 0) {
                app(WalletService::class)->refundOrder($order, 'Refund for edited order #' . $order->order_serial_no);
            }

            $walletUsed = app(WalletService::class)->debitUpToForOrder($order, (float)$order->total);
            $cashOnDeliveryAmount = $wasPaid ? 0 : max(0, (float)$order->total - $walletUsed);

            $order->wallet_paid_amount = $walletUsed;
            $order->cash_on_delivery_amount = $cashOnDeliveryAmount;
            $order->payment_status = $wasPaid ? PaymentStatus::PAID : ($cashOnDeliveryAmount > 0 ? PaymentStatus::UNPAID : PaymentStatus::PAID);

            $transaction = Transaction::where(['order_id' => $order->id, 'type' => 'payment'])->first();
            if ($transaction) {
                $transaction->amount = $walletUsed;
                $transaction->save();
            }
            return;
        }

        $order->wallet_paid_amount = 0;
        $order->cash_on_delivery_amount = 0;

        $transaction = Transaction::where(['order_id' => $order->id, 'type' => 'payment'])->first();
        if ($transaction) {
            $transaction->amount = (float)$order->total;
            $transaction->save();
        }
    }
}
