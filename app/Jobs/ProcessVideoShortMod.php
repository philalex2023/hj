<?php

namespace App\Jobs;

use App\TraitClass\VideoTrait;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessVideoShortMod implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait;

    /**
     * 任务尝试次数
     *
     * @var int
     */
    //public $tries = 3;

    public int $timeout = 180000; //默认60秒超时
    //跳跃式延迟执行
//    public $backoff = [60,180];
    //public $backoff = [18000,36000];


    public string $mp4Path;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row)
    {
        //
        $this->row = $row;
        $this->mp4Path = env('RESOURCE_DOMAIN') . $row->url;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function handle()
    {
        //远程下载
        $domain = env('RESOURCE_DOMAIN');
        $originUrl = $this->row->url;
        $this->saveOriginFile($domain . $originUrl);
        //切片
        $this->short_dash_slice($this->row);
        $this->short_hls_slice($this->row,true);

        //先更新播放地址
        $url = basename($this->row->url);
        $coverImg = self::get_slice_url($url,'cover');
        DB::table('video_short')->where('id',$this->row->id)->update([
            'dash_url' => self::get_slice_url($url),
            'hls_url' => self::get_slice_url($url,'hls'),
            'cover_img' => $coverImg,
        ]);
        // 同步到资源站
        $this->syncSlice($url,true);
        $this->syncUpload($coverImg);
        //生成预览
        //$this->generatePreview($this->row);
    }

    public function saveOriginFile($file)
    {
        $fileName = basename($file);
        $path = storage_path('app/public/shortVideo/');
        if(!is_dir($path)){
            mkdir($path, 0755, true);
        }
        // 创建 stream
        $opts = array(
            'http'=>array(
                'method'=>"GET",
                'header'=>"Accept-language: en\r\n" .
                    "Cookie: foo=bar\r\n"
            )
        );
        $context = stream_context_create($opts);
        // 以下面设置的 HTTP 头来打开文件
        $file = file_get_contents($file, false, $context);
        file_put_contents($path . $fileName, $file, LOCK_EX);
    }

    public function transcodeShortMp4($file_name)
    {
        return '/public/shortVideo/'.$file_name.'.mp4';
    }

    /**
     * @throws \Exception
     */
    public function short_dash_slice($row)
    {
        //切片转码成m4s格式文件
        //$mp4_path = env('RESOURCE_DOMAIN') . $row->url;
        $file_name = pathinfo($row->url,PATHINFO_FILENAME);
        //不是mp4格式转mp4
        //$mp4_path = $this->transcodeMp4($mp4_path,$file_name);
        $mp4_path = $this->transcodeShortMp4($file_name);
        //创建对应的切片目录
        $sliceDir = 'public'.env('SLICE_DIR','/slice');
        $tmp_path = $sliceDir.'/dash/'.$file_name.'/';
        $dirname = storage_path('app/').$tmp_path;
        if(!is_dir($dirname)){
            mkdir($dirname, 0755, true);
        }

        $mpd_path = $tmp_path.$file_name.'.mpd';

        $format = new \FFMpeg\Format\Video\X264();
        $format->setAdditionalParameters(['-vcodec', 'copy','-acodec', 'copy']); //跳过编码
        //$format = $format->setAdditionalParameters(['-hwaccels', 'cuda']);//GPU高效转码
        $video = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("local") //在storage/app的位置
        ->open($mp4_path)
        ->export()
        ->toDisk("local")
        ->inFormat($format);
        $video->save($mpd_path);
        //done 生成截图
        $frame = $video->frame(TimeCode::fromSeconds(1));
        $cover_path = $sliceDir.'/'.$this->coverImgDir.'/'.$file_name.'/'.$file_name.'.jpg';
        $frame->save($cover_path);

    }

    public function short_hls_slice($row, $del=false)
    {
        //$mp4_path = $this->getMp4Path();
        $file_name = pathinfo($row->url,PATHINFO_FILENAME);
        //不是mp4格式转mp4
        //$mp4_path = $this->transcodeMp4($mp4_path,$file_name);
        $mp4_path = $this->transcodeShortMp4($file_name);
        //创建对应的切片目录
        $tmp_path = 'public'.env('SLICE_DIR','/slice').'/hls/'.$file_name.'/';
        $dirname = storage_path('app/').$tmp_path;
        if(!is_dir($dirname)){
            mkdir($dirname, 0755, true);
        }

        $m3u8_path = $tmp_path.$file_name.'.m3u8';

        $format = new \FFMpeg\Format\Video\X264();
        //增加commads的参数,使用ffmpeg -hwaccels命令查看支持的硬件加速选项
        $segmentLength = 1;
        $format->setAdditionalParameters([
             '-hls_list_size',0, //设置播放列表保存的最多条目，设置为0会保存有所片信息，默认值为5
            // '-force_key_frames','expr:gte(t,n_forced*'.$segmentLength.')', //强制一秒内必须至少要一帧
            // '-rw_timeout','1800000000',
            // '-listen_timeout','1800000000',
            // '-timeout','1800000000',
            '-vcodec', 'copy','-acodec', 'copy', //跳过编码
        ]);
        //多码率
        //$lowBitrate = (new FFMpeg\Format\Video\X264('aac', 'libx264'))->setKiloBitrate(250);
        //$midBitrate = (new FFMpeg\Format\Video\X264('aac', 'libx264'))->setKiloBitrate(500);
        //$highBitrate = (new FFMpeg\Format\Video\X264('aac', 'libx264'))->setKiloBitrate(1000);

        $video = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("local") //在storage/app的位置
        ->open($mp4_path);

        $result = $video->exportForHLS()
            ->setSegmentLength($segmentLength)//默认值是10
            ->toDisk("local")
            ->addFormat($format)
            //->addFormat($lowBitrate)
            //->addFormat($midBitrate)
            //->addFormat($highBitrate)
            ->save($m3u8_path);
        $durationSeconds = floor($result->getDurationInMiliseconds()/1000);
        $updateData = ['duration_seconds' => $durationSeconds];
        $updateData['duration'] = $this->formatSeconds($durationSeconds);
        DB::table('video_short')->where('id',$this->row->id)->update($updateData);
        //删除mp4文件
        /*if($del!==false){
            Storage::delete($mp4_path);
        }*/
    }

}
