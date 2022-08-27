<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MoveVideoToCate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'move:video_gold';

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
        $paramGold = $this->ask('输入要移动视频的限制金币');
        $paramCatId = $this->ask('输入将移动到的版块ID');
        if(!$paramGold || !$paramCatId){
            $this->info('参数错误');
        }
        $videos = DB::table('video')->where('gold',$paramGold)->get(['id','cat']);
        $count = 0;
        foreach ($videos as $video){
            $cat = json_decode($video->cat,true);
            if(!in_array($paramCatId,$cat)){
                $cat[] = $paramCatId;
            }
            if($paramGold==800){
                unset($cat[array_search('10051',$cat)]);
                unset($cat[array_search('10054',$cat)]);
            }
            if($paramGold==1000){
                unset($cat[array_search('10053',$cat)]);
                unset($cat[array_search('10054',$cat)]);
            }
            if($paramGold==1200){
                unset($cat[array_search('10051',$cat)]);
                unset($cat[array_search('10053',$cat)]);
            }

            $jsonCat = json_encode($cat);
            $this->info('####类别:'.$jsonCat);
            DB::table('video')->where('id',$video->id)->update(['cat'=>$jsonCat]);
            $this->info('####vid:'.$video->id);
            $this->info('####移动个数:'.$count);
            ++$count;
        }
        return 0;
    }
}
