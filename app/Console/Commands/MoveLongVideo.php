<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MoveLongVideo extends Command
{
    use PHPRedisTrait,VideoTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'move_long_video';

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

        //DB::table('video')->where('type',5)->update(['tag'=>json_encode([115=>181])]);
//        $topCat = DB::table('categories')
//            ->where('parent_id',2)
//            ->where('is_checked',1)
//            ->orderBy('sort')
//            ->pluck('id');
//        foreach ($topCat as $cat){
            //$cat = 10000; //短视频
        $ids = [];
        /*$tagIndex = $this->argument('id');
        if(!$tagIndex){
            return 0;
        }*/
        $ids['326'] = [238, 91, 22, 11139, 2637, 287, 9645, 10008, 82, 7216, 10075, 63, 6725, 9958, 681, 13211, 8954, 271, 536, 676, 16253, 219, 161, 4709, 8896, 10023, 4706, 14360, 9605, 361];
        $ids['325'] = [24, 16557, 253, 10590, 10456, 9810, 2615, 95, 113, 634, 9813, 13652, 146, 9870, 2384, 10591, 10537, 9513, 16326, 11169, 16134, 10511, 16741, 13474, 12354, 12456, 13715, 10682, 12904, 12827];
        $ids['330'] = [13011, 13008, 13005, 13000, 12998, 13019, 12946, 12948, 12938, 12949, 12694, 13011, 12931, 12982, 12961, 12715, 12704, 12710, 12962, 12956, 12945, 12958, 12972, 12940, 12943, 12996, 12708, 13015, 12970, 12979];
        $ids['331'] = [5098, 5024, 5142, 5955, 6160, 5283, 6366, 5277, 5774, 5750, 6324, 12821, 5903, 5734, 16351, 5188, 5630, 6518, 6165, 6132, 5577, 6302, 16320, 6094, 6152, 13545, 12295, 5496, 12284, 12294];
        $ids['332'] = [14466, 16866, 16118, 14542, 16130, 16198, 14113, 16532, 16477, 16820, 16620, 14670, 14007, 13702, 13589, 13453, 12452, 5893, 5904, 5914, 5945, 6021, 6104, 6175, 6198, 6205, 6252, 6272, 6315, 6326];
        $ids['333'] = [5312, 5748, 5560, 5518, 5441, 5836, 5993, 5965, 16869, 5039, 5604, 5831, 5382, 5776, 6010, 5656, 5718, 5638, 12860, 5979, 5864, 5662, 5704, 5929, 5766, 16599, 17042, 5430, 5822, 16705];
        $ids['334'] = [4264, 4267, 5045, 4512, 4266, 5727, 13841, 5730, 5404, 5936, 4272, 5973, 5968, 16280, 5536, 5503, 5433, 5741, 5131, 5071, 5618, 5712, 14540, 5569, 5029, 4985, 16451, 5342, 6047, 5696];
        $ids['335'] = [3053, 3074, 3110, 13583, 11560, 11477, 11398, 3115, 3066, 10919, 3058, 3078, 2575, 11385, 2560, 2551, 3063, 3060, 2577, 13428, 10829, 2564, 2600, 2587, 2565, 13913, 14092, 3089, 3047, 2554];
        $ids['336'] = [10396, 7190, 8757, 7170, 7165, 7041, 6968, 16191, 11205, 11207, 11209, 11210, 13448, 11529, 8271, 8253, 3219, 3218, 2037, 2040, 2018, 2017];
        $ids['337'] = [2033, 2030, 2027, 2045, 2042];

        $ids['321'] = [13565, 8, 64, 9599, 115, 2632, 4681, 8906, 9871, 485, 10574, 4660, 16593, 350, 308, 122, 365, 163, 4705, 562, 575, 563, 568, 572, 9553, 12825, 9633, 11059, 13787, 9784];
        $ids['323'] = [17144, 17038, 16883, 14399, 14267, 9994, 9677, 9568, 8945, 7215, 6833, 6746, 4583, 2792, 2393, 856, 719, 720, 705, 477, 471, 472, 467, 159, 131, 116, 100, 76, 142, 164];
        $ids['324'] = [7110, 7109, 7105, 7081, 7077, 7072, 7071, 7066, 7063, 7064, 7061, 7006, 7005, 7000, 6426, 6423, 6421, 6419, 6414, 3655, 4595, 4567, 7107, 7104, 7102, 7099, 7097, 7079, 7069, 7068];

        $tags = [
            '321' => '精东传媒',
            '323' => '91制片厂',
            '324' => 'JVID',
            '326' => '星空传媒',
            '325' => '蜜桃传媒',
            '330' => '日韩无码',
            '331' => '日韩调教',
            '332' => '日韩制服',
            '333' => '日韩凌辱',
            '334' => '日韩多p',
            '335' => '3D动画',
            '336' => '校园动漫',
            '337' => '乱伦动漫',
//            '338' => '华风国漫',
        ];
        foreach ($tags as $key => $tag){
            $items = DB::table('video_copy1')->whereIn('id',$ids[$key])->get();
            foreach ($items as $item) {
                $insert = (array)$item;
                unset($insert['id']);
                $insert['type'] = 1;
                $insert['cat'] = json_encode([]);
                $insert['tagNames'] = '';

                $insert['tag'] = json_encode([$key]);
                $insert['tag_kv'] = json_encode([$key => $tag]);

                DB::table('video')->insert($insert);
            }
        }


        /*$tagAll = DB::table('tag')->pluck('name','id')->all();
         * DB::table('video_short')->chunkById(100,function ($items) use ($tagAll){
            foreach ($items as $item){
                $insert = (array)$item;
                if($insert['status']>0){
                    unset($insert['id']);
                    unset($insert['tid']);
                    unset($insert['favors']);
                    $insert['cid'] = 10000;
                    $insert['dev_type'] = 1;

                    $tagKvJson = json_decode($insert['tag_kv'],true);
                    $tagKv = $tagKvJson ?? [];
                    $intersection = array_intersect($tagAll,$tagKv);
                    if(!empty($intersection)){
                        $insert['tag_kv'] = json_encode($intersection);
                    }else{
                        $insert['tag_kv'] = json_encode([]);
                    }
                    DB::table('video')->insert($insert);
                }
            }
        });*/
        /*$videos = DB::table('video_short')->get();
        foreach ($videos as $video){
            DB::table('video')->insert([
                'name' => $video->name,
                'dev_type' => 1,
                'cid' => 10000,
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
