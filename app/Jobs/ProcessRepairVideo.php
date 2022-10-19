<?php

namespace App\Jobs;

use App\TraitClass\VideoTrait;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

class ProcessRepairVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait;

    /**
     * 任务尝试次数
     *
     * @var int
     */
//    public int $tries = 1;

    public int $timeout = 180000; //默认60秒超时
    //跳跃式延迟执行
    private mixed $item;


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
     *
     * Execute the job.
     * @return void
     * @throws FileNotFoundException
     * @throws \Exception
     */
    /*
     *
     */
    public function handle()
    {
        $file_name = pathinfo($this->item->url,PATHINFO_FILENAME);
        $tmp_path = 'public/slice/hls/'.$file_name.'/';
        $m3u8_path = $tmp_path.$file_name.'.m3u8';

        $video = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("ftp")
            ->openUrl('/home/hj/'.$m3u8_path);
        $format = new \FFMpeg\Format\Video\X264();
        $encryptKey = HLSExporter::generateEncryptionKey();
        Storage::disk('local')->put($tmp_path.'/secret.key',$encryptKey);//在storage/app的位置
        $video->exportForHLS()
            ->withEncryptionKey($encryptKey)
            ->setSegmentLength(1)//默认值是10
            ->toDisk("local")
            ->addFormat($format)
            ->save($m3u8_path);
        $this->syncSlice($this->item->url,true);
    }

    public function syncSlice($url, $del=false)
    {
        $dir_name = pathinfo($url,PATHINFO_FILENAME);
        $slice_dir = env('SLICE_DIR','/slice');
        $hls_directory = '/public'.$slice_dir.'/hls/'.$dir_name;
        //$cover_img_dir = '/public'.$slice_dir.'/'.$this->coverImgDir.'/'.$dir_name;
        $hls_files = Storage::files($hls_directory);
        //$cover_img = Storage::files($cover_img_dir);

        foreach ($hls_files as $file){
            $content = Storage::get($file);
            Storage::disk('ftp')->put($file,$content);
        }
        /*foreach ($cover_img as $img)
        {
            $content = Storage::get($img);
            Storage::disk('ftp')->put($img,$content);
        }*/
        if($del!==false){
            Storage::deleteDirectory($hls_directory);
        }
    }



}
