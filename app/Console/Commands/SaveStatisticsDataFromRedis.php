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
