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
        $ids['339'] = [5058, 5074, 5026, 5011, 5023, 5013, 5020, 5073, 4999, 5018, 5016, 4994, 5025, 5012, 5021, 5024, 5022, 4998, 5007, 4987, 4989, 4990, 4991, 4992, 4993, 4995, 4996, 4997, 5000, 5005, 5015];
        $ids['340'] = [4635, 4600, 4741, 4739, 4601, 647, 642, 4629, 4825, 4661, 651, 562, 4832, 640, 574, 566, 648, 4740, 4602, 4599, 4332, 617, 662, 618, 4637, 570, 519, 5058, 4952, 616, 572];
        $ids['341'] = [4492, 848, 858, 847, 860, 863, 853, 862, 802, 800, 4500, 808, 783, 865, 833, 850, 854, 815, 849, 855, 799, 864, 4859, 821, 633, 830, 4854, 825, 809, 812, 816];
        $ids['342'] = [3130, 3183, 3150, 1379, 2324, 3172, 3166, 2313, 1378, 3170, 3181, 1109, 2316, 3155, 1279, 3160, 1114, 1371, 1359, 3148, 1083, 2495, 3179, 1255, 3174, 1259, 3152, 3180, 1082, 1084, 3139];
        $ids['343'] = [3221, 4184, 751, 174, 745, 753, 741, 347, 775, 4602, 776, 180, 766, 173, 757, 535, 728, 743, 538, 761, 772, 531, 4705, 732, 721, 527, 530, 764, 2214, 353, 730];
        $ids['344'] = [699, 704, 689, 700, 697, 653, 694, 706, 685, 693, 707, 705, 702, 698, 3116, 671, 690, 4864, 4049, 4681, 666, 4045, 676, 4053, 497, 696, 4056, 493, 669, 3122, 1909];
        $ids['345'] = [409, 4999, 577, 593, 584, 587, 4846, 589, 2386, 2916, 582, 586, 2472, 438, 594, 4003, 7, 276, 411, 420, 435, 436, 441, 709, 2437, 2454, 3999, 4688, 4994, 27, 278];
        $ids['346'] = [976, 981, 975, 95, 978, 984, 94, 983, 986, 989, 3066, 979, 990, 1043, 2665, 985, 991, 1001, 1027, 2537, 2672, 3026, 977, 987, 988, 1003, 1017, 1045, 2643, 2648, 2660];


        $tags = [
            '339' => '竖版最新',
            '340' => '竖版推荐',
            '341' => '竖版偷拍',
            '342' => '竖版黑料',
            '343' => '竖版萝莉',
            '344' => '竖版制服',
            '345' => '竖版Cosplay',
            '346' => '竖版动漫',
        ];
        foreach ($tags as $key => $tag){
            if($key == 339){
                $items = DB::table('video_short')->whereIn('id',$ids[$key])->get();
                $this->info('total-'.count($items));
                foreach ($items as $item) {
                    $insert = (array)$item;
                    unset($insert['id']);
                    unset($insert['tid']);
                    unset($insert['favors']);
                    $insert['dev_type'] = 1;
                    $insert['type'] = 1;
                    $insert['gold'] = 100*$insert['gold'];
                    $insert['cid'] = 10000;
                    $insert['cat'] = json_encode([]);
                    $insert['tagNames'] = '';
                    $insert['tag'] = json_encode([$key]);
                    $insert['tag_kv'] = json_encode([$key => $tag]);

                    DB::table('video')->insert($insert);
                }
            }

        }

        $this->info('######执行完成######');
        return 0;
    }
}
