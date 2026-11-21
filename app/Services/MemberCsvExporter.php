<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams member records as a UTF-8 CSV file that opens cleanly in Excel.
 */
class MemberCsvExporter
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    /**
     * Stream every member matching the query as CSV.
     *
     * @param  Builder<Member>  $query
     */
    public function stream(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $this->headers());

            $query->with('category')->orderBy('id')->chunkById(500, function ($members) use ($handle): void {
                foreach ($members as $member) {
                    fputcsv($handle, array_map($this->safeCell(...), $this->row($member)));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            'No. Ahli',
            'Status',
            'Nama Pemohon',
            'Nama Perniagaan',
            'Kategori',
            'Alamat Perniagaan',
            'Telefon',
            'Emel',
            'No. SSM',
            'Produk / Perkhidmatan',
            'Keperluan',
            'Tarikh Permohonan',
            'Tarikh Kelulusan',
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function row(Member $member): array
    {
        return [
            $member->member_no,
            $member->status->label(),
            $member->applicant_name,
            $member->business_name,
            $member->category?->name_bm,
            $member->business_address,
            $member->phone,
            $member->email,
            $member->ssm_no,
            $this->sanitizer->toPlainText($member->business_description),
            $this->sanitizer->toPlainText($member->business_needs),
            $member->applied_at?->format('Y-m-d H:i'),
            $member->approved_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Prevent spreadsheet formula injection by neutralising cells that start with formula characters.
     */
    private function safeCell(?string $value): string
    {
        $text = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
}
