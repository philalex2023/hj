<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;


class SecretMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $status = $request->user()->status ?? 1;
        if($status==0){
            return response()->json(['state' => -1,'data'=>[],'msg' => '该账号已被禁用!']);
        }
        $content = $response->getContent();
        if ($content) {
            # 对 content 进行加密处理
            $content = Crypt::encryptString($content);
            $response->setContent($content);
        }
        return $response;
    }

}
