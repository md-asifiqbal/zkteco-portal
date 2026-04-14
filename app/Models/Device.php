<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $connection = 'mysql';
    protected $guarded = [];


    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
