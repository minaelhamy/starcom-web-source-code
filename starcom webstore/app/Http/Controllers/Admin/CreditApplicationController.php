<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CreditApplicationDecisionRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\CreditApplicationResource;
use App\Http\Resources\CreditFacilityResource;
use App\Models\CreditApplication;
use App\Services\CreditApplicationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CreditApplicationController extends AdminController implements HasMiddleware
{
    public function __construct(private readonly CreditApplicationService $creditApplicationService)
    {
        parent::__construct();
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:credit-requests', only: ['index']),
            new Middleware('permission:credit-requests_show', only: ['show']),
            new Middleware('permission:credit-requests_review', only: ['approve', 'decline']),
            new Middleware('permission:lending-portfolio', only: ['portfolio']),
        ];
    }

    public function index(PaginateRequest $request): Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|Application|ResponseFactory
    {
        try {
            return CreditApplicationResource::collection($this->creditApplicationService->queueList($request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function portfolio(PaginateRequest $request): Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|Application|ResponseFactory
    {
        try {
            return CreditFacilityResource::collection($this->creditApplicationService->portfolioList($request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(CreditApplication $creditApplication): CreditApplicationResource|Response|Application|ResponseFactory
    {
        try {
            return new CreditApplicationResource($this->creditApplicationService->show($creditApplication));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function approve(CreditApplication $creditApplication, CreditApplicationDecisionRequest $request): CreditFacilityResource|Response|Application|ResponseFactory
    {
        try {
            return new CreditFacilityResource($this->creditApplicationService->approve($creditApplication, $request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function decline(CreditApplication $creditApplication, CreditApplicationDecisionRequest $request): CreditFacilityResource|Response|Application|ResponseFactory
    {
        try {
            return new CreditFacilityResource($this->creditApplicationService->decline($creditApplication, $request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
