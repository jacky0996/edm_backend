<?php

namespace App\Models\EDM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mobiles extends Model
{
    use HasFactory;
    protected $table      = 'mobile';
    protected $connection = 'crm';
    protected $guarded    = [];
}
