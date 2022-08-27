<?php
namespace App\Http\Controllers\Admin;

use App\Models\Config;
use App\Models\User;
use App\Models\Withdraw;
use App\TraitClass\AdTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Response;

class ChannelReferController extends BaseCurlController
{
    use PHPRedisTrait,AdTrait;
    //去掉公共模板
    public $commonBladePath = '';
    public $pageName = '渠道来源分析';

    public function index()
    {
        $parameters = request('res');
        $domain = request('domain');
        $type = request('type');
        if($parameters){
            $res = '';
            if(!Cache::lock('channelRefer',10)->get()){
                $res = '为避免服务器性能受到影响,请30秒后再试';
            }else{
                $redis = $this->redis();
                $keys = $redis->keys('*download*');
                $channelData = [];
                //$keys = array_slice($keys,0,1000);
                foreach ($keys as $key){
                    $originalKey = str_replace('laravel_database_','',$key);
                    $hashData = $redis->hGetAll($originalKey);
                    isset($hashData['refer']) && $channelData[$hashData['refer']] = $hashData['download_url'];
                }

                $n = count($channelData);
                foreach ($channelData as $k => $v){
                    if($k){
                        $arr = parse_url($v);
                        $krr = parse_url($k);
                        isset($arr['query']) && parse_str($arr['query'],$parr);
                        $channel_id = !isset($parr['channel_id']) ? '' : $parr['channel_id'];

                        isset($krr['host']) && isset($arr['host']) && $res  .= $krr['host'].','.$arr['host'].','.$channel_id."\n";
                    }
                }
            }

        }
        return $this->display(['parameters'=>$res??'','domain'=>$domain,'type'=>$type,'numbers'=>$n??'']);
    }

    public function submitPost(Request $request)
    {
        $params = $request->all(['domain','type']);
        $domainStr = $params['domain']??'';
        return $request->wantsJson()
            ? new Response('', 204)
            : redirect()->route('admin.channelRefer.index',['res'=>1,'domain'=>$domainStr,'type'=>$params['type']]);
    }

}
