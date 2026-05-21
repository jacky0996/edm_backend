<?php

namespace App\Models\Google;

use Illuminate\Database\Eloquent\Model;

class GoogleOAuthToken extends Model
{
    protected $table = 'google_oauth_token';

    protected $fillable = [
        'provider',
        'account_email',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];
}
