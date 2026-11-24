<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\TopProductsReportRequest;
use App\Http\Resources\TopProductResource;
use App\Services\ReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService)
    {
    }

    public function __invoke(TopProductsReportRequest $request): AnonymousResourceCollection
    {
        $rows = $this->reportService->topProducts($request->validated('from'), $request->validated('to'));

        return TopProductResource::collection($rows)->additional([
            'meta' => [
                'from' => $request->validated('from'),
                'to'   => $request->validated('to'),
            ],
        ]);
    }
}
