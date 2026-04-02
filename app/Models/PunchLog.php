<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PunchLog extends Model
{
    protected $connection = 'logdb';

    protected $guarded = [];
}
