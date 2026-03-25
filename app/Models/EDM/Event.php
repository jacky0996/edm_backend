<?php

namespace App\Models\EDM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    protected $table      = 'event';
    protected $connection = 'crm';
    protected $guarded    = [];
}
