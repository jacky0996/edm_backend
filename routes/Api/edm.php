<?php

use Illuminate\Support\Facades\Route;

Route::prefix('edm')->group(function () {
    Route::prefix('member')->group(function () {
        Route::post('/list', 'EDM\MemberController@list');
        Route::post('/view', 'EDM\MemberController@view');
        Route::post('/add', 'EDM\MemberController@add');
        Route::post('/editStatus', 'EDM\MemberController@editStatus');
        Route::post('/editEmail', 'EDM\MemberController@editEmail');
        Route::post('/editMobile', 'EDM\MemberController@editMobile');
        Route::post('/editSales', 'EDM\MemberController@editSales');
    });

    Route::prefix('group')->group(function () {
        Route::post('/list', 'EDM\GroupController@list');
        Route::post('/view', 'EDM\GroupController@view');
        Route::post('/editStatus', 'EDM\GroupController@editStatus');
        Route::post('/create', 'EDM\GroupController@create');
        Route::post('/getEventList', 'EDM\GroupController@getEventList');
    });
    Route::prefix('event')->group(function () {
        Route::post('/list', 'EDM\EventController@list');
        Route::post('/view', 'EDM\EventController@view');
        Route::post('/create', 'EDM\EventController@create');
        Route::post('/update', 'EDM\EventController@update');
        Route::post('/editStatus', 'EDM\EventController@editStatus');
        Route::post('/imageUpload', 'EDM\EventController@imageUpload');
        Route::post('/getImage', 'EDM\EventController@getImage');
        Route::post('/getInviteList', 'EDM\EventController@getInviteList');
        Route::post('/importGroup', 'EDM\EventController@importGroup');
    });
    /** SSO 驗證 API (掛載 IP 白名單防護) */
    Route::middleware([\App\Http\Middleware\WhitelistIpMiddleware::class])->group(function () {
        Route::post('/sso/verify-token', 'EDM\SSOController@verifyToken')->name('api.edm.sso.verify');
    });
});
