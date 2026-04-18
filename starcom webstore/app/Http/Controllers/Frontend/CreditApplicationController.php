<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreditApplicationStoreRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\CreditApplicationResource;
use App\Http\Resources\WalletTransactionResource;
use App\Services\CreditApplicationService;
use Illuminate\Support\Facades\Auth;

class CreditApplicationController extends Controller
{
    public function __construct(private readonly CreditApplicationService $creditApplicationService)
    {
    }

    public function index(PaginateRequest $request)
    {
        try {
            return CreditApplicationResource::collection($this->creditApplicationService->customerList($request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function summary()
    {
        try {
            return ['data' => $this->creditApplicationService->summaryForCustomer(Auth::user())];
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(CreditApplicationStoreRequest $request)
    {
        try {
            return new CreditApplicationResource($this->creditApplicationService->customerStore($request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(\App\Models\CreditApplication $creditApplication)
    {
        try {
            $this->creditApplicationService->customerDestroy($creditApplication);

            return response([
                'status'  => true,
                'message' => 'تم حذف الطلب بنجاح.',
            ]);
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function walletTransactions(PaginateRequest $request)
    {
        try {
            $method = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';

            return WalletTransactionResource::collection(
                Auth::user()->walletTransactions()->latest()->$method($methodValue)
            );
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
