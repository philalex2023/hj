<?php

namespace App\Providers;

use App\Exceptions\ErrorException;
use App\ExtendClass\Plugin;

use App\ExtendClass\UserObserver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Carbon::setLocale(env('lang')=='cn'?'zh':'');
        User::observe(UserObserver::class);
        //关联关系简称对应关系
        $relation = [
            'admin' => 'App\Models\Admin'
        ];
        //如果关闭插件不加载
        if (env('OPEN_PLUGIN',1)) {
            //插件路由
            $relation=Plugin::loadPluginConfigArr($relation);
        }

        //注册关系
        Relation::morphMap($relation);
    }

    /**
     * 注册插件的相对关系
     * @param $relation
     * @return array
     */

}
