<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MoveShortVideo extends Command
{
    use PHPRedisTrait,VideoTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'move_short_video';

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

        DB::table('video')->where('type',5)->update(['tag'=>json_encode([115=>181])]);
//        $topCat = DB::table('categories')
//            ->where('parent_id',2)
//            ->where('is_checked',1)
//            ->orderBy('sort')
//            ->pluck('id');
//        foreach ($topCat as $cat){
            //$cat = 10000; //短视频

        /*DB::table('video_short')->chunkById(100,function ($items){
            foreach ($items as $item){
                $insert = (array)$item;
                if($insert['status']>0){
                    unset($insert['id']);
                    unset($insert['tid']);
                    unset($insert['favors']);
                    $insert['cid'] = 10000;
                    $insert['dev_type'] = 1;
                    DB::table('video')->insert($insert);
                }
            }
        });*/
            /*$videos = DB::table('video_short')->get();
            foreach ($videos as $video){
                DB::table('video')->insert([
                    'name' => $video->name,
                    'dev_type' => 1,
                    'cid' => $cat,
                    'tag' => json_encode([]),
                    'data_source' => json_encode([]),
                    'show_type' => $video->group_type,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }*/
//        }

        //$bar = $this->output->createProgressBar(count($Items));
        //$bar->start();
        //$bar->finish();
        $this->info('######执行完成######');
        return 0;
    }
}
