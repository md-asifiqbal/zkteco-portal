<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PunchLog extends Model
{
    protected $connection = 'logs_db';

    protected $guarded = [];
}
