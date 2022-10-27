<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCollectionBbs;
use App\Jobs\ProcessGetApiVideo;
use App\Models\CommBbs;
use App\TraitClass\CurlTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use AWS\CRT\Log;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BbsSet extends Command
{
    use PHPRedisTrait,DispatchesJobs,CurlTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bbs_enc_thumbs {url?}';

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
        $url = $this->argument('url');
        $urlCon = Storage::disk('sftp1')->get($url);
        $this->info('con:'.$urlCon);
        $dirname = pathinfo($url,PATHINFO_DIRNAME);
        $filename = pathinfo($url,PATHINFO_FILENAME);
        $newUrl = $dirname.'/'.$filename.'.htm';
        $this->info('save path:'.$newUrl);
        $bool = Storage::disk('sftp1')->put($newUrl,$urlCon);
        $bool && $this->info('加密成功');
        /*DB::table('community_bbs')->where('id','>=',310)->chunkById(100,function ($bbs){
            foreach ($bbs as $item) {
                $updateData = [
                    'author_avatar' => rand(1,43),
                    'author_nickname' => '游客_'.$item->author_id,
                    'author_location_name' => '未知',
                ];
                DB::table('community_bbs')->where('id',$item->id)->update($updateData);
            }
            $this->info('finished update records 100!');
        });*/

        return 0;
    }
}
