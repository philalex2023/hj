<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SaveStatisticsDataFromRedis extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save:statisticData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $redis = $this->redis();
        $yesterdayTime = strtotime(date('Y-m-d',strtotime('-1 day')));

        /*$statistic_day_collection_key = 'statistic_day_collection';
        if(!$redis->exists($statistic_day_collection_key)){
            $statistic_day_keys = $redis->keys('*statistic_day:*');
            foreach ($statistic_day_keys as $day_key){
                $redis->sAdd($statistic_day_collection_key,$day_key);
            }
        }else{
            $statistic_day_keys = $redis->sMembers($statistic_day_collection_key);
        }

        foreach ($statistic_day_keys as $statistic_day_key){
            $channelStatisticItem = $redis->hGetAll($statistic_day_key);
            $channel_id = $channelStatisticItem['channel_id'] ?? 0;
            $device_system = $channelStatisticItem['device_system'] ?? 0;
            $at_time = $channelStatisticItem['at_time'] ?? 0;
            if($at_time>0 && $device_system>0){
                DB::table('statistic_day')
                    ->where('channel_id',$channel_id)
                    ->where('device_system',$device_system)
                    ->where('at_time',$at_time)
                    ->updateOrInsert(['channel_id'=>$channel_id,'device_system'=>$device_system,'at_time'=>$at_time],$channelStatisticItem);
            }
            if($at_time<=$yesterdayTime){
                $redis->del($statistic_day_key);
                $redis->sRem($statistic_day_collection_key,$statistic_day_key);
            }    
        }*/

        //
        $channel_day_statistics_collection_key = 'channel_day_statistics_collection';
        if(!$redis->exists($channel_day_statistics_collection_key)){
            $channel_day_statistics_keys = $redis->keys('channel_day_statistics:*');
            $addData = [];
            foreach ($channel_day_statistics_keys as $channel_day_key){
                $addData[] = $channel_day_key;
            }
            !empty($addData) && $redis->sAddArray($channel_day_statistics_collection_key,$addData);
        }else{
            $channel_day_statistics_keys = $redis->sMembers($channel_day_statistics_collection_key);
        }

        foreach ($channel_day_statistics_keys as $channel_day_statistics_key){
            $item = $redis->hGetAll($channel_day_statistics_key);
            $channel_id = $item['channel_id'] ?? 0;
            $date_at = $item['date_at'] ?? 0;
            if($channel_id>0){
                DB::table('channel_day_statistics')
                    ->where('channel_id',$channel_id)
                    ->where('date_at',$date_at)
                    ->updateOrInsert(['channel_id'=>$channel_id,'date_at'=>$date_at],$item);
                if(strtotime($date_at) <= $yesterdayTime){
                    $redis->del($channel_day_statistics_key);
                    $redis->sRem($channel_day_statistics_collection_key,$channel_day_statistics_key);
                }
            }
        }

        $this->info('######执行成功######');
        return 0;
    }
}
