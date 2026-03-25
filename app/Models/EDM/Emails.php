<?php

namespace App\Models\EDM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Emails extends Model
{
    use HasFactory;
    protected $table      = 'email';
    protected $connection = 'crm';
    protected $fillable   = [
        'email',
    ];
}
