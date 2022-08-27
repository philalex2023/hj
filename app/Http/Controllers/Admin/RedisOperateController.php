<?php
namespace App\Http\Controllers\Admin;

use App\Models\Config;
use App\Models\User;
use App\Models\Withdraw;
use App\TraitClass\AdTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Response;

class RedisOperateController extends BaseCurlController
{
    use PHPRedisTrait,AdTrait;
    //去掉公共模板
    public $commonBladePath = '';
    public $pageName = 'redis操作';


    public function index()
    {
        $type = request('type');
        $method = request('method');
        $parameters = request('parameters');
        if($method){
            $disabledMethods = ['flushdb','flushall','config'];
            if(in_array(strtolower($method),$disabledMethods)){
                return redirect()->route('admin.redisOperate.index',['res'=>'禁用此命令']);
            }
//            $parameters = explode(' ',$parameters);
            $redis = $this->redis();
            $redis->select(intval($type));
            $res = $redis->rawCommand($method,$parameters);
//            Redis::select(intval($type));
//            $res = Redis::command($method,$parameters);
            $n=1;
            if(is_array($res)){
                $n = count($res);
                $r = '';
                foreach ($res as $s){
                    $r .= json_encode($s,JSON_UNESCAPED_UNICODE)."\n";
                }
                $res = $r;
            }else{
                $res = json_encode($res,JSON_UNESCAPED_UNICODE);
            }
        }
        return $this->display(['res'=>$res??'','type'=>$type??0,'method'=>$method??'','parameters'=>$parameters??'','numbers'=>$n??'']);
    }

    public function submitPost(Request $request)
    {
        $params = $request->all(['method','parameters','type']);
        return $request->wantsJson()
            ? new Response('', 204)
            : redirect()->route('admin.redisOperate.index',['type'=>$params['type'],'method'=>$params['method'],'parameters'=>$params['parameters']]);
    }

}
