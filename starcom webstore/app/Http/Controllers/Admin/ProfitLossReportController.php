<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProfitLossReportExport;
use App\Http\Requests\PaginateRequest;
use App\Http\Resources\ProfitLossReportResource;
use App\Http\Resources\ProfitLossReportSummaryResource;
use App\Models\ThemeSetting;
use App\Services\CompanyService;
use App\Services\ProfitLossReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Dipokhalder\Settings\Facades\Settings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class ProfitLossReportController extends AdminController implements HasMiddleware
{
    public function __construct(
        private readonly ProfitLossReportService $profitLossReportService,
        private readonly CompanyService $companyService,
    )
    {
        parent::__construct();
    }

    public static function middleware(): array
    {
        return [new Middleware('permission:profit-loss-report')];
    }

    public function index(PaginateRequest $request)
    {
        try {
            return ProfitLossReportResource::collection($this->profitLossReportService->list($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function summary(Request $request)
    {
        try {
            return new ProfitLossReportSummaryResource($this->profitLossReportService->summary($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function export(PaginateRequest $request)
    {
        try {
            return Excel::download(new ProfitLossReportExport($this->profitLossReportService, $request), 'Profit-Loss-Report.xlsx');
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function exportPdf(PaginateRequest $request)
    {
        try {
            $exportRequest = clone $request;
            $exportRequest->merge(['paginate' => 0]);
            $reports = $this->profitLossReportService->list($exportRequest);
            $company = $this->companyService->list();
            $copyright = Settings::group('site')->get('site_copyright');
            $imagePath = ThemeSetting::where('key', 'theme_logo')->value('logo');
            $themeLogo = $imagePath
                ? 'data:image/png;base64,' . base64_encode(Http::withOptions(['verify' => false])->get($imagePath)->body())
                : null;

            $pdf = Pdf::loadView('reports.profitLossReport', compact('company', 'themeLogo', 'reports', 'copyright'))
                ->setPaper('a4', 'landscape');

            return response()->stream(
                fn () => print($pdf->output()),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="profit-loss-report.pdf"',
                ]
            );
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
