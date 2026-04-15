<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\FinancialInstitutionRequest;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\FinancialInstitutionResource;
use App\Models\User;
use App\Services\FinancialInstitutionService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FinancialInstitutionController extends AdminController implements HasMiddleware
{
    public function __construct(private readonly FinancialInstitutionService $financialInstitutionService)
    {
        parent::__construct();
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:financial-institutions', only: ['index']),
            new Middleware('permission:financial-institutions_show', only: ['show']),
            new Middleware('permission:financial-institutions_create', only: ['store']),
            new Middleware('permission:financial-institutions_edit', only: ['update']),
        ];
    }

    public function index(PaginateRequest $request): Response|\Illuminate\Http\Resources\Json\AnonymousResourceCollection|Application|ResponseFactory
    {
        try {
            return FinancialInstitutionResource::collection($this->financialInstitutionService->list($request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function store(FinancialInstitutionRequest $request): FinancialInstitutionResource|Response|Application|ResponseFactory
    {
        try {
            return new FinancialInstitutionResource($this->financialInstitutionService->store($request));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(User $financialInstitution): FinancialInstitutionResource|Response|Application|ResponseFactory
    {
        try {
            return new FinancialInstitutionResource($this->financialInstitutionService->show($financialInstitution));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function update(FinancialInstitutionRequest $request, User $financialInstitution): FinancialInstitutionResource|Response|Application|ResponseFactory
    {
        try {
            return new FinancialInstitutionResource($this->financialInstitutionService->update($request, $financialInstitution));
        } catch (\Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
