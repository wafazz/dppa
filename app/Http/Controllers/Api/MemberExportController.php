<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberFilterRequest;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Services\MemberCsvExporter;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController extends Controller
{
    /**
     * Export the members matching the current filters as CSV.
     */
    public function __invoke(MemberFilterRequest $request, MemberCsvExporter $exporter): StreamedResponse
    {
        $filename = 'senarai-ahli-'.Carbon::now()->format('Ymd-His').'.csv';

        app(ActivityLogger::class)->record('member.exported', $filename, null, null, ['filters' => $request->filters()]);

        return $exporter->stream(
            Member::query()->filter($request->filters()),
            $filename,
        );
    }
}
