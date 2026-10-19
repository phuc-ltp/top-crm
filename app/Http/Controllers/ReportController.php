<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DashboardService;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Support\Lookup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('reports.view'), 403);

        return view('reports.index', ['types' => ReportService::allowedTypes($request->user())]);
    }

    private function build(Request $request, string $type): array
    {
        abort_unless($request->user()->can('reports.view'), 403);
        $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer'], 'status' => ['nullable', 'string', 'max:30'],
        ]);
        [$from, $to] = DashboardService::range($request->filled('from') || $request->filled('to') ? 'custom' : 'this_year', $request->from, $request->to);

        $service = new ReportService($request->user(), $from, $to, $request->integer('user_id') ?: null, $request->input('status') ?: null);

        return $service->build($type) + ['from' => $from, 'to' => $to];
    }

    public function show(Request $request, string $type)
    {
        $report = $this->build($request, $type);

        return view('reports.show', ['report' => $report, 'users' => Lookup::users(false), 'types' => ReportService::allowedTypes($request->user())]);
    }

    public function export(Request $request, string $type, string $format, ExportService $exporter)
    {
        abort_unless($request->user()->can('reports.export'), 403);
        $report = $this->build($request, $type);
        $name = 'bao_cao_'.$type.'_'.$report['from']->format('Ymd').'_'.$report['to']->format('Ymd');

        if ($format === 'pdf') {
            ActivityLogger::log('exported', null, 'Xuất PDF '.$report['title']);

            return Pdf::loadView('pdf.report', ['report' => $report, 'settings' => Setting::allCached()])
                ->setPaper('a4', count($report['headings']) > 6 ? 'landscape' : 'portrait')->download($name.'.pdf');
        }

        return $exporter->download($name, $report['headings'], $report['rows'], $format);
    }
}
