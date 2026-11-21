<?php

namespace App\Services;

use App\Models\MemberNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Issues unique member numbers such as DPPA-2026-0001 using a row-locked yearly counter.
 */
class MemberNumberGenerator
{
    /**
     * Reserve the next member number for the given year.
     */
    public function generate(int $year): string
    {
        return DB::transaction(function () use ($year): string {
            MemberNumberSequence::query()->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
            ]);

            $sequence = MemberNumberSequence::query()->whereKey($year)->lockForUpdate()->firstOrFail();
            $sequence->last_number = $sequence->last_number + 1;
            $sequence->save();

            return sprintf('%s-%d-%04d', config('membership.member_no_prefix'), $year, $sequence->last_number);
        });
    }
}
