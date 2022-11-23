<?php

namespace App\Jobs;

use App\Models\LoginLog;
use App\Models\User;
use App\TraitClass\ChannelTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use phpseclib\Crypt\Random;
use Zhuzhichao\IpLocationZh\Ip;
use Illuminate\Support\Str;

class ProcessLogin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StatisticTrait, PHPRedisTrait, ChannelTrait;

    /**
     * 任务尝试次数
     *
     * @var int
     */
    public int $tries = 1;

    //跳跃式延迟执行
    //public $backoff = [60,180];

    public array $loginLogData=[];

    public $code = '';

    public int $device_system = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($loginLogData)
    {
        //
        $this->code = $loginLogData['promotion_code'];
        $this->device_system = (int)$loginLogData['device_system'];
        unset($loginLogData['promotion_code']);
        $this->loginLogData = $loginLogData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $uid = $this->loginLogData['uid'];
        // 冗余最后一次登录地理信息
//        $area = Ip::find($this->loginLogData['ip']);
//        $areaJson = json_encode($area,JSON_UNESCAPED_UNICODE);

        if($this->loginLogData['type']==1){
            $this->saveStatisticByDay('install',$this->loginLogData['channel_id'],$this->device_system,null,$uid);
        }else{
            $this->saveStatisticByDay('login_number',$this->loginLogData['channel_id'],$this->device_system);
        }
        $this->saveStatisticByDay('active_users',$this->loginLogData['channel_id'],$this->device_system);

//        $userRedis = $this->redis('user');
        $date = date('Y-m-d');
        $dayData = date('Ymd');
        $time = strtotime($date);
        $keepUidKey = 'keep_'.$uid;
        $statistic_day_key = 'statistic_day:'.$this->loginLogData['channel_id'].':'.$this->device_system.':'.$time;

        Redis::pipeline(function ($pipe) use ($keepUidKey,$time,$date,$statistic_day_key,$dayData,$uid) {
            $pipe->select(2);
            $pipe->zAdd($keepUidKey, $time, $date);
            $pipe->expire($keepUidKey,10*3600*24);
            $keepUser = $pipe->zRange($keepUidKey,0,-1,true);
//            $redis = $this->redis();
            $pipe->select(0);
            for ($i=1;$i<11;++$i){
                $keyDate = date('Y-m-d',strtotime('-'.$i.' day'));
                if(isset($keepUser[$keyDate])){
                    $pipe->hIncrBy($statistic_day_key,'keep_'.$i,1);
                    //首页统计
                    //$i==1 && $redis->incr('total_keep_1_'.$dayData) && $redis->expire('total_keep_1_'.$dayData,86400);
                    $i==1 && $pipe->zAdd('hj_keep_1_'.$dayData,time(),$uid) && $pipe->expire('hj_keep_1_'.$dayData,3600*24*7);
                }
            }
            //首页统计
            $nowTime = time();

            $pipe->zAdd('at_user_'.$dayData,$nowTime,$uid);
            $pipe->expire('at_user_'.$dayData,3600*24*7);

            if($this->loginLogData['type']==1){//新用户
                $pipe->zAdd('new_increase_'.$dayData,$nowTime,$uid);
                $pipe->expire('new_increase_'.$dayData,3600*24*7);

                if($this->device_system==1 || $this->device_system==3){
                    $pipe->zAdd('new_inc_ios_'.$dayData,$nowTime,$uid);
                    $pipe->expire('new_inc_ios_'.$dayData,3600*24*7);
                }

                if($this->device_system==2){
                    $pipe->zAdd('new_inc_android_'.$dayData,$nowTime,$uid);
                    $pipe->expire('new_inc_android_'.$dayData,3600*24*7);
                }

                if($this->loginLogData['channel_id'] > 0){ //渠道量
                    $pipe->zAdd('new_inc_channel_'.$dayData,$nowTime,$uid);
                    $pipe->expire('new_inc_channel_'.$dayData,3600*24*7);
                }else{ //自来量
                    $pipe->zAdd('new_inc_auto_'.$dayData,$nowTime,$uid);
                    $pipe->expire('new_inc_auto_'.$dayData,3600*24*7);
                }
            }else{
                //增加登录次数
                DB::table('users')->where('id',$uid)->increment('login_numbers');
            }
        });


        //记录登录日志
        /*$this->loginLogData['area'] = $areaJson;
        if(isset($this->loginLogData['clipboard'])){
            unset($this->loginLogData['clipboard']);
        }*/

//        $updateData['location_name'] = $areaJson;
        /*if(!$this->code){
            $invitationCode = Str::random(2).$uid.Str::random(2);
            $updateData['promotion_code'] = $invitationCode;
            //$updateData['account'] = $this->loginLogData['account'] . '-' .$uid;
            //$updateData['password'] = $this->loginLogData['account'];
        }*/
        //增加登录次数
//        DB::table('users')->where('id',$uid)->increment('login_numbers',1,$updateData);
//        LoginLog::query()->create($this->loginLogData);

    }

}
