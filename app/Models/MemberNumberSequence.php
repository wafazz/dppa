<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-year counter used to build unique member numbers.
 */
#[Fillable(['year', 'last_number'])]
class MemberNumberSequence extends Model
{
    protected $primaryKey = 'year';

    public $incrementing = false;

    public $timestamps = false;
}
