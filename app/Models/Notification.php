<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    protected $fillable = [
        'company_id',
        'subject',
        'body',
        'icon'
    ];
}
