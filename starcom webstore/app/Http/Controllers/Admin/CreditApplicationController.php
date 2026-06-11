<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CreditApplicationDecisionRequest;
use App\Http\Requests\CreditApplicationIdentityRequest;
use App\Http\Requests\CreditApplicationNoteRequest;
use App\Http\Requests\CreditFacilityAssignmentRequest;
use App\Http\Requests\CreditFacilityContractRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\CreditApplicationResource;
use App\Http\Resources\CreditFacilityResource;
use App\Models\CreditApplication;
use App\Models\CreditFacility;
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
            new Middleware('permission:credit-requests', only: ['index', 'destroy']),
            new Middleware('permission:credit-requests_show', only: ['show']),
            new Middleware('permission:credit-requests_review', only: ['approve', 'decline', 'markPendingApproval', 'resetApproval', 'assignmentOptions', 'addFacilityNote', 'updateIdentity']),
            new Middleware('permission:lending-portfolio', only: ['portfolio']),
            new Middleware('permission:lending-portfolio_show', only: ['showFacility', 'assignFacility', 'uploadFacilityContracts']),
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

    public function showFacility(CreditFacility $creditFacility): CreditFacilityResource|Response|Application|ResponseFactory
    {
        try {
            return new CreditFacilityResource($this->creditApplicationService->showFacility($creditFacility));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function assignmentOptions(): Response|Application|ResponseFactory
    {
        try {
            return response([
                'status' => true,
                'data' => $this->creditApplicationService->assignmentOptions(),
            ]);
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateIdentity(CreditApplication $creditApplication, CreditApplicationIdentityRequest $request): CreditApplicationResource|Response|Application|ResponseFactory
    {
        try {
            return response([
                'status' => true,
                'message' => 'تم تحديث بيانات الهوية بنجاح.',
                'data' => new CreditApplicationResource($this->creditApplicationService->updateIdentity($creditApplication, $request)),
            ]);
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

    public function markPendingApproval(CreditApplication $creditApplication, CreditApplicationDecisionRequest $request): CreditFacilityResource|Response|Application|ResponseFactory
    {
        try {
            return new CreditFacilityResource($this->creditApplicationService->markPendingApproval($creditApplication, $request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function resetApproval(CreditFacility $creditFacility): CreditApplicationResource|Response|Application|ResponseFactory
    {
        try {
            return response([
                'status'  => true,
                'message' => 'تم إلغاء الاعتماد وإعادة الطلب إلى قائمة المراجعة.',
                'data'    => new CreditApplicationResource($this->creditApplicationService->resetApproval($creditFacility)),
            ]);
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function assignFacility(CreditFacility $creditFacility, CreditFacilityAssignmentRequest $request): CreditFacilityResource|Response|Application|ResponseFactory
    {
        try {
            return response([
                'status' => true,
                'message' => 'تم تحديث جهة التمويل والموظف المسؤول بنجاح.',
                'data' => new CreditFacilityResource($this->creditApplicationService->assignFacility($creditFacility, $request)),
            ]);
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function addFacilityNote(CreditFacility $creditFacility, CreditApplicationNoteRequest $request): CreditFacilityResource|Response|Application|ResponseFactory
    {
        try {
            return response([
                'status' => true,
                'message' => 'تمت إضافة الملاحظة بنجاح.',
                'data' => new CreditFacilityResource($this->creditApplicationService->addFacilityNote($creditFacility, $request)),
            ]);
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function uploadFacilityContracts(CreditFacility $creditFacility, CreditFacilityContractRequest $request): CreditFacilityResource|Response|Application|ResponseFactory
    {
        try {
            return response([
                'status' => true,
                'message' => 'تم رفع العقود بنجاح.',
                'data' => new CreditFacilityResource($this->creditApplicationService->uploadFacilityContracts($creditFacility, $request)),
            ]);
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(CreditApplication $creditApplication): Response|Application|ResponseFactory
    {
        try {
            $this->creditApplicationService->adminDestroy($creditApplication);

            return response([
                'status'  => true,
                'message' => 'تم حذف الطلب بنجاح.',
            ]);
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
