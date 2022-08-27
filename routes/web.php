<?php

use Illuminate\Support\Facades\Route;



Route::get('/', function () {
    return view('welcome');
});

Route::get('/pay/{payKey}', function ($payKey) {
    $response = \Illuminate\Support\Facades\Redis::connection()->client()->get($payKey);
    if(!$response){
        $response = "页面已过期";
    }
    return view('pay',['response'=>$response]);
});

//验证码
Route::prefix('api/')->group(function ($route) {
    $route->get('captcha/{type?}', 'Api\CaptchaController@index')->name('api.captcha');

});
