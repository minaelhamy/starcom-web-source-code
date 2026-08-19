<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CustomerServiceLeadApplicationRequest;
use App\Http\Requests\CustomerServiceLeadProfileRequest;
use App\Http\Requests\CustomerServiceLeadStatusRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\CustomerServiceLeadResource;
use App\Models\CustomerServiceLead;
use App\Services\CustomerServiceLeadService;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomerServiceLeadController extends AdminController implements HasMiddleware
{
    public function __construct(private readonly CustomerServiceLeadService $customerServiceLeadService)
    {
        parent::__construct();
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:customer-service-leads', only: ['index', 'show', 'dashboardSummary']),
            new Middleware('permission:customer-service-leads_update', only: ['updateStatus', 'updateProfile']),
            new Middleware('permission:customer-service-leads_submit', only: ['submitApplication']),
            new Middleware('permission:customer-service-reports', only: ['reportSummary']),
            new Middleware('permission:customer-service-redistribute', only: ['redistribute']),
        ];
    }

    public function index(PaginateRequest $request): Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|Application|ResponseFactory
    {
        try {
            return CustomerServiceLeadResource::collection($this->customerServiceLeadService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(CustomerServiceLead $customerServiceLead): Response|CustomerServiceLeadResource|Application|ResponseFactory
    {
        try {
            return new CustomerServiceLeadResource($this->customerServiceLeadService->show($customerServiceLead));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateStatus(CustomerServiceLeadStatusRequest $request, CustomerServiceLead $customerServiceLead): Response|CustomerServiceLeadResource|Application|ResponseFactory
    {
        try {
            return new CustomerServiceLeadResource($this->customerServiceLeadService->updateStatus($customerServiceLead, $request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function updateProfile(CustomerServiceLeadProfileRequest $request, CustomerServiceLead $customerServiceLead): Response|CustomerServiceLeadResource|Application|ResponseFactory
    {
        try {
            return new CustomerServiceLeadResource($this->customerServiceLeadService->updateProfile($customerServiceLead, $request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function submitApplication(CustomerServiceLeadApplicationRequest $request, CustomerServiceLead $customerServiceLead): Response|CustomerServiceLeadResource|Application|ResponseFactory
    {
        try {
            return new CustomerServiceLeadResource($this->customerServiceLeadService->submitApplication($customerServiceLead, $request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function dashboardSummary(): Response|array|Application|ResponseFactory
    {
        try {
            return ['data' => $this->customerServiceLeadService->dashboardSummary()];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function reportSummary(Request $request): Response|array|Application|ResponseFactory
    {
        try {
            return ['data' => $this->customerServiceLeadService->reportSummary($request)];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function redistribute(Request $request): Response|array|Application|ResponseFactory
    {
        try {
            $perAgent = $request->filled('per_agent') ? (int) $request->get('per_agent') : null;
            return ['data' => $this->customerServiceLeadService->redistribute($perAgent)];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
