<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records that a person has voted in a vote. It never stores the choice.
 */
class VoteReceipt extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['vote_id', 'user_id', 'cast_at'];

    protected function casts(): array
    {
        return ['cast_at' => 'datetime'];
    }
}
