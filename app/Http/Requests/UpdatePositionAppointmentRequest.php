<?php

namespace App\Http\Requests;

use App\Models\ChamberPosition;
use App\Models\Member;
use Illuminate\Validation\Rule;

class UpdatePositionAppointmentRequest extends StorePositionAppointmentRequest
{
    /**
     * Existing appointments may keep a position that was deactivated later.
     * PositionAppointmentService rejects a move to an inactive position.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['member_id'] = [
            'required',
            Rule::exists((new Member)->getTable(), 'id')->whereNull('deleted_at'),
        ];
        $rules['chamber_position_id'] = [
            'required',
            Rule::exists((new ChamberPosition)->getTable(), 'id'),
        ];

        return $rules;
    }
}
