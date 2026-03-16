<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stats extends Model
{
    protected $table = "statistics";
    const UPDATED_AT = "modified_at";
    protected $guarded = false;
}
