<?php

namespace App\Jobs;

use App\TraitClass\CurlTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessGetApiVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait, CurlTrait;

    public int $tries = 1;

    public int $timeout = 180000; //默认60秒超时

    private array $item;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($item)
    {
        //
        $this->item = $item;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $item = $this->item;
        $insertData = [
            'name' => $item['name'],
            'duration_seconds' => $item['duration'],
            'duration' => $this->formatSeconds($item['duration']),
            'sync' => 2,
            'type' => 4,
            'tag' => json_encode([]),
            'cat' => json_encode([]),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        //保存资源
        $playUrl = $item['play'];
//        $this->info('m3u8文件:'.$playUrl);

        $pathInfo = pathinfo($playUrl);
        $insertData['url'] = $item['hash'];
        $file_name = $insertData['url'];
        //创建对应的切片目录
        $ts_path = '/public'.env('SLICE_DIR','/slice').'/hls/'.$file_name.'/';

        $content = $this->curlByUrl($playUrl);
        $m3u8File = $ts_path.$file_name.'.m3u8';
        Storage::disk('sftp')->put($m3u8File,$content); //save m3u8
        $insertData['hls_url'] = $m3u8File;
        $trimmed = explode("\n",$content);
//        $this->info('封面图文件:'.$pathInfo['dirname'].'/cover.jpg');

        $coverContent = $this->curlByUrl($pathInfo['dirname'].'/cover.jpg');
        $encryptFile = $ts_path.'cover.htm';
        //Log::info('===encryptImg===',[$encryptFile,$content]);
        Storage::disk('sftp')->put($encryptFile,$coverContent);
        $insertData['cover_img'] = $ts_path.'cover.jpg';
        Storage::disk('sftp')->put($insertData['cover_img'],$coverContent);

        foreach ($trimmed as $key => $val) {
            if(str_contains($val, '#EXT-X-KEY')){
                $uriLine = rtrim(explode(':',$val)[1],',');
                $uri = rtrim(explode(',',$uriLine)[1],',');
                $keyFileName = rtrim(explode('"',$uri)[1],'"');
                $keyFile = $pathInfo['dirname'].'/'.$keyFileName;
                $keyContent = file_get_contents($keyFile);
                Storage::disk('sftp')->put($ts_path.$keyFileName,$keyContent); //save key file
//                $this->info('key文件:'.$keyFile);
            }

            if(str_contains($val, 'dx')){
                $tsFileName = $val;
                $tsFile = $pathInfo['dirname'].'/'.$tsFileName;
                $tsContent = $this->getContentByUrl($tsFile);
                Storage::disk('sftp')->put($ts_path.$tsFileName,$tsContent); //save ts file
//                $this->info('ts文件:'.$tsFile);
            }
        }
        //入库
        $insertData['updated_at'] = date('Y-m-d H:i:s');
        DB::table('video')->insert($insertData);
    }
}
