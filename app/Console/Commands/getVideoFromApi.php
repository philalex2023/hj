<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGetApiVideo;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use AWS\CRT\Log;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class getVideoFromApi extends Command
{
    use PHPRedisTrait,DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:video';

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
        $apiUrl = 'https://kenb.cloud/?test';
//        $apiUrl = 'http://192.168.0.155/?mdsq';
        $response = (new Client([
            'headers' => ['Content-Type' => 'application/json']
        ]))->get($apiUrl, [
            'verify' => false
        ])->getBody()->getContents();
//        $this->info('response:'.$response);
        $xml=simplexml_load_string($response);
        /*$count = count((array)$xml->resource);
        print_r($count);*/
        $videos = DB::table('video')->where('type',4)->get(['id','url']);

        $mdVideoKey = 'hj_video';
        $redis = $this->redis('video');

        $memberArr = [];
        foreach ($videos as $video){
            $memberArr[] = $video->url;
        }
        if(!empty($memberArr)){
            $redis->sAddArray($mdVideoKey,$memberArr);
            $redis->expire($mdVideoKey,24*3600*7);
        }

        foreach ($xml->resource as $item){
            $hash = (string)$item['hash'];
            $play = 'http://154.207.98.131/'.date('ym',(int)$item['time']).'/'.$hash.'/play.m3u8';
            $itemArr = [
                'name' => (string)$item,
                'duration' => (int)$item['duration'],
                'play' => $play,
                'hash' => $hash,
            ];
            /*$this->info('url-'.$play);
            break;*/
            if(!$redis->sIsMember($mdVideoKey,$itemArr['hash'])){
                $job = new ProcessGetApiVideo($itemArr);
                $this->dispatch($job->onQueue('high'));
                $this->info('######执行-'.$itemArr['hash'].'######');
            }
        }
//        $this->info('######执行成功######');
        return 0;
    }
}
