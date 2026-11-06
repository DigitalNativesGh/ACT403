<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProctorEvent extends Model
{
    public const UPDATED_AT = null;

    public const TALKING = 'talking';

    public const HEAD_TURN = 'head_turn';

    public const DETECTOR_UNAVAILABLE = 'detector_unavailable';

    protected $fillable = ['type', 'penalty'];
}
