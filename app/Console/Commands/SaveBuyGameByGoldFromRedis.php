<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SaveBuyGameByGoldFromRedis extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save_gold_buy_game';

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
    public function handle()
    {
        $redis = $this->redis();
        $collections = $redis->sMembers('uid_bid_collection');
        $count = 1;
        foreach ($collections as $collection){
            $explodeArr = explode('_',$collection);
            $uid = end($explodeArr);
            $insertBool = true;
            foreach($redis->sMembers($collection) as $bid){
                $id = $bid << 32 | $uid;
                if($bid==0){
                    $id = $uid;
                    $this->info('解锁全部的ID:'.$id);
                }
                if(!DB::table('bus')->where('id',$id)->exists()){
                    $res = DB::table('bus')->insert(['id'=>$id]);
                    if(!$res){
                        $insertBool = false;
                    }
                }
            }
            if($insertBool){
                $redis->sRem('uid_bid_collection',$collection);
//                $this->info('存库成功的key:'.$collection);
                $this->info('存入条数:'.$count);
                ++$count;
            }

        }
        return 0;
    }
}
