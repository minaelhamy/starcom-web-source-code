<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleEnum;
use App\Http\Requests\CreditCustomerStatusRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\CreditCustomerStatusResource;
use App\Models\User;
use App\Services\CreditCustomerStatusService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class CreditCustomerStatusController extends AdminController implements HasMiddleware
{
    public function __construct(private readonly CreditCustomerStatusService $creditCustomerStatusService)
    {
        parent::__construct();
    }

    public static function middleware(): array
    {
        return [new Middleware('permission:credit-customer-status')];
    }

    public function index(PaginateRequest $request)
    {
        try {
            $this->ensureAdmin();
            return CreditCustomerStatusResource::collection($this->creditCustomerStatusService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function summary(Request $request)
    {
        try {
            $this->ensureAdmin();
            return response(['data' => $this->creditCustomerStatusService->summary()]);
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function blacklist(User $user, CreditCustomerStatusRequest $request)
    {
        try {
            $this->ensureAdmin();
            return new CreditCustomerStatusResource($this->creditCustomerStatusService->setBlacklist(
                $user,
                $request->boolean('blacklisted'),
                $request->input('blacklist_reason')
            ));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function topCustomer(User $user, CreditCustomerStatusRequest $request)
    {
        try {
            $this->ensureAdmin();
            return new CreditCustomerStatusResource($this->creditCustomerStatusService->setTopCustomer(
                $user,
                $request->boolean('is_top_credit_customer')
            ));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function ensureAdmin(): void
    {
        if (!Auth::user()?->hasRole(RoleEnum::ADMIN)) {
            throw new Exception(trans('all.message.permission_denied'), 422);
        }
    }
}
