<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One counted choice. Deliberately has no link to the voter.
 */
#[Fillable(['vote_id', 'vote_option_id'])]
class VoteBallot extends Model
{
    public $timestamps = false;
}
