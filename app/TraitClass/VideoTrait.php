<?php

namespace App\TraitClass;

use AetherUpload\Util;
use Exception;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

trait VideoTrait
{
    use GoldTrait,AboutEncryptTrait,PHPRedisTrait,CatTrait,TagTrait;

    public object $row;

    public array $videoFields = ['video.id','video.is_top','name','gold','cat','tag_kv','sync','title','dash_url','hls_url','duration','type','restricted','cover_img','views','updated_at'];

    public string $coverImgDir = 'coverImg';

    public array $restrictedType = [
        0 => [
            'id' => 0,
            'name' => '免费'
        ],
        1 => [
            'id' => 1,
            'name' => 'VIP会员卡'
        ],
        2 => [
            'id' => 2,
            'name' => '金币'
        ],
    ];

    public function setRow(): object
    {
        return $this->row;
    }

    public function getRow(): object
    {
        return $this->row;
    }

    public function getMp4Path(): string
    {
        $resource = Util::getResource($this->row->url);
        return $resource->path;
    }

    public function getMp4FilePath($url): string
    {
        $resource = Util::getResource($url);
        return $resource->path;
    }

    //视频转码
    public function transcodeMp4($file_path,$sourceName): string
    {
        $suf = '.mp4';
        $storagePath = storage_path('app');
        $absolutePath = $storagePath.DIRECTORY_SEPARATOR.$file_path;
        $video = FFMpeg::create([
            'ffmpeg.binaries'  => env('FFMPEG_BINARIES', 'ffmpeg'),
            'ffprobe.binaries' => env('FFPROBE_BINARIES', 'ffprobe'),
            'timeout'          => 36000, // The timeout for the underlying process
            'ffmpeg.threads'   => 3,   // The number of threads that FFMpeg should use
        ])->open($absolutePath);
        $format = new X264();
        $format->setAdditionalParameters(['-vcodec', 'copy','-acodec', 'copy']); //跳过编码
        $mp4_dir = '/public/mp4';
        $mp4_full_dir = $storagePath.$mp4_dir;
        if(!is_dir($mp4_full_dir)){
            mkdir($mp4_full_dir, 0755, true);
        }
        $savePath = $mp4_full_dir.DIRECTORY_SEPARATOR.$sourceName . $suf;
        $video->save($format, $savePath);
        return $mp4_dir.DIRECTORY_SEPARATOR.$sourceName . $suf;

    }

    public function syncSlice($url, $del=false)
    {
        $dir_name = pathinfo($url,PATHINFO_FILENAME);
        $slice_dir = env('SLICE_DIR','/slice');
        $dash_directory = '/public'.$slice_dir.'/dash/'.$dir_name;
        $hls_directory = '/public'.$slice_dir.'/hls/'.$dir_name;
        $cover_img_dir = '/public'.$slice_dir.'/'.$this->coverImgDir.'/'.$dir_name;
        $dash_files = Storage::files($dash_directory);
        $hls_files = Storage::files($hls_directory);
        $cover_img = Storage::files($cover_img_dir);
        foreach ($dash_files as $file){
            $content = Storage::get($file);
            Storage::disk('sftp')->put($file,$content);
        }
        foreach ($hls_files as $file){
            $content = Storage::get($file);
            Storage::disk('sftp')->put($file,$content);
        }
        foreach ($cover_img as $img)
        {
            $content = Storage::get($img);
            Storage::disk('sftp')->put($img,$content);
        }
        if($del!==false){
            Storage::deleteDirectory($dash_directory);
            Storage::deleteDirectory($hls_directory);
        }
    }

    /**
     * @throws FileNotFoundException
     */
    public function generatePreview($url)
    {
        $dir_name = pathinfo($url,PATHINFO_FILENAME);
        $slice_dir = env('SLICE_DIR','/slice');
        //$dash_directory = '/public'.$slice_dir.'/dash/'.$dir_name;
        $hls_directory = '/public'.$slice_dir.'/hls/'.$dir_name;
        //dash预览
        /*$dash_play_file = $dash_directory .'/'. $dir_name.'.mpd';
        $exists_dash = Storage::disk('sftp')->exists($dash_play_file);
        if($exists_dash){
            $content_dash = Storage::disk('sftp')->get($dash_play_file);
            if($content_dash){
                $xml_object = simplexml_load_string($content_dash);
                $xml_object['mediaPresentationDuration'] = 'PT0M30S';
                $xml_content = $xml_object->asXML();
                $dash_file = $dash_directory.'/preview.mpd';
                Storage::disk('sftp')->put($dash_file,$xml_content);
            }
        }*/

        //hls预览
        //$hls_play_file = $hls_directory . '/' . $dir_name.'.m3u8';
        //$hls_handle_play_file = Storage::disk('sftp')->exists($hls_play_file);
        if(true){
            /*$lines = explode("\n",Storage::disk('sftp')->get($hls_play_file));
            Log::info('==lines==',[$lines]);
            $initHlsFile = '';
            foreach ($lines as $line) {
                if(str_contains($line, '.m3u8')){
                    $initHlsFile = $hls_directory . '/' . $line;
                }
            }
            Log::info('==hls_handle_init_file==',[$initHlsFile]);*/
            $initHlsFile = $hls_directory . '/' . $dir_name.'_0_1000.m3u8';
            $hls_handle_init_file = Storage::disk('sftp')->exists($initHlsFile);
            if($hls_handle_init_file){
                $hls_file = $hls_directory . '/preview.m3u8';
                $trimmed = explode("\n",Storage::disk('sftp')->get($initHlsFile));
                //Log::info('==trimmed==',[$trimmed]);
                $second = 0;
                $breakLineNum = -1;
                $hlsContentLines = '';
                foreach ($trimmed as $key => $val) {
                    if($breakLineNum>0 && ($key==$breakLineNum+2)){
                        $hlsContentLines .= "#EXT-X-ENDLIST\n";
                        break;
                    }
                    $hlsContentLines .= $val."\n";
                    if(str_contains($val, '#EXTINF')){
                        $block_s = rtrim(explode(':',$val)[1],',');
                        $block_s += 0;
                        if($second < 31){
                            if($block_s<31){
                                $second = round($second + $block_s);
                            }else{
                                $second = $block_s;
                                $breakLineNum = $key;
                            }
                        }else{
                            $breakLineNum = $key;
                        }
                    }
                }
                if(!empty($hlsContentLines)){
                    Storage::disk('sftp')->put($hls_file, $hlsContentLines);
                }
            }
        }

    }

    public function resetRedisCatVideo($cats,$vid)
    {
        $redis = $this->redis();
        $allCats = $this->getCats();
        foreach ($allCats as $allCat){
            $redis->sRem('catForVideo:'.$allCat['id'],$vid);
        }
        foreach ($cats as $cat)
        {
            $redis->sAdd('catForVideo:'.$cat,$vid);
        }
    }

    /*public function resetRedisTagVideo($tags,$vid)
    {
        $redis = $this->redis();
        $allTags = $this->getTagData();
        foreach ($allTags as $allTag){
            $redis->sRem('tagForVideo:'.$allTag['id'],$vid);
        }
        foreach ($tags as $tag)
        {
            $redis->sAdd('tagForVideo:'.$tag,$vid);
        }
    }*/

    public function syncMiddleSectionTable()
    {
        try {
            $Video = DB::table('video')->where('status',1)->get(['id','cat']);
            foreach ($Video as $item)
            {
                $catArr = $item->cat ? @json_decode($item->cat) : [];
                if(!empty($catArr)){
                    $this->resetRedisCatVideo($catArr,$item->id);
                }
            }
        }catch (Exception $e){
            Log::error('syncMiddleSectionTable==='.$e->getMessage());
        }

    }

    public function syncMiddleTagTable()
    {
//        DB::beginTransaction();
        try {
            $this->syncMiddleTagProcess();
//            $this->syncMiddleTagProcess(1, 100);
        } catch (Exception $e) {
            Log::error('syncMiddleTagTable===' . $e->getMessage());
//            DB::rollBack();
        }
    }

    /*private function syncMiddleTagProcess()
    {
        $Video = DB::table('video')->where('status',1)->get(['id', 'tag']);
        foreach ($Video as $item) {
            $tagArr = $item->tag ? @json_decode($item->tag) : [];
            if (!empty($tagArr)) {
                $this->resetRedisTagVideo($tagArr,$item->id);
            }
        }
    }*/


    public static function getDomain($sync)
    {
        $sync += 0;
        return match ($sync) {
            0 => env('SLICE_DOMAIN'),
            1 => env('RESOURCE_DOMAIN'),
            2 => env('RESOURCE_DOMAIN2'),
            default => '',
        };
    }

    public static function getOrigin($sync,$pathName = '',$simple = false)
    {
        $url =  $sync==1 ? env('RESOURCE_DOMAIN_DEV') : env('SLICE_DOMAIN');
        if (!$pathName) {
            return '';
        }
        if ($simple) {
            return "{$url}/{$pathName}";
        }
        return "{$url}/aetherupload/display/{$pathName}";
    }

    //获取切片链接地址、封面图
    public static function get_slice_url($pathName,$type="dash",$sync=null): string
    {
        $play_file_name = pathinfo($pathName,PATHINFO_FILENAME);
        $sliceDir = env('SLICE_DIR','/slice');
        $path = match ($type) {
            "dash" => '/storage' . $sliceDir . '/' . $type . '/' . $play_file_name . '/' . $play_file_name . '.mpd',
            "hls" => '/storage' . $sliceDir . '/' . $type . '/' . $play_file_name . '/' . $play_file_name . '.m3u8',
            "cover" => '/storage' . $sliceDir . '/coverImg/' . $play_file_name . '/' . $play_file_name . '.jpg',
        };
        $url = $path;
        if($sync!==null){
//鉴权需要            ($type=='hls') && ($path = '/storage' . $sliceDir . '/' . $type . '/' . $play_file_name . '/' . $play_file_name . '_0_1000.m3u8');
            $url = self::getDomain($sync).$path;
            $url .= '?sign='. (self::getSignForVideo($path));
        }
        return $url;
    }

    //视频鉴权签名
    public static function getSignForVideo($path)
    {
        $authKey = 'q93We8y8VOlBAakUA48eqOPlK';
        $signStr = '';
        $timestamp = time();
        $randStr = Str::random(16);
        $md5Str=md5($path.'-'.$timestamp.'-'.$randStr.'-0-'.$authKey);
        $signStr = $timestamp.'-'.$randStr.'-0-'.$md5Str;
        return $signStr;
    }

    public function getSearchCheckboxResult($items,$inputData,$field)
    {
        if(!empty($inputData)){
            $is_none = end($inputData)==0;
            $result = [];
            foreach ($items as $item){
                if(!$item->$field){
                    $item->$field = '{}';
                }
                if(!$is_none){
                    $intersection = array_intersect(json_decode($item->$field,true),$inputData);
                    if(!empty($intersection)){
                        $result[] = $item;
                    }
                }else{
                    if($item->$field=='[]'){
                        $result[] = $item;
                    }
                }
            }
            return $result;
        }
        return $items;
    }

    public function formatSeconds($seconds): string
    {
        $hour = floor($seconds/3600);
        $minute = floor(($seconds-3600 * $hour)/60);
        $seconds = floor((($seconds-3600 * $hour) - 60 * $minute) % 60);
        if($hour<10){
            $hour = "0".$hour;
        }
        if($minute<10){
            $minute = "0".$minute;
        }
        if($seconds<10){
            $seconds = "0".$seconds;
        }
        return $hour.':'.$minute.':'.$seconds;
    }

    public function transferSeconds($format)
    {
        $durationArr = explode(':', $format);
        $length = count($durationArr);
        $h=0;
        $i=0;
        $s=0;
        if($length == 3){
            $h = $durationArr[0] ?? 0;
            $i = $durationArr[1] ?? 0;
            $s = $durationArr[2] ?? 0;
        }elseif ($length == 2){
            $h = 0;
            $i = $durationArr[0] ?? 0;
            $s = $durationArr[1] ?? 0;
        }elseif ($length == 1){
            $h = 0;
            $i = 0;
            $s = $format;
        }
        return $h*3600 + $i*60 + $s;
    }

    public function handleVideoItems($lists,$display_url=false,$uid = 0,$appendInfo = false)
    {
        $_v = date('Ymd');
        foreach ($lists as &$list){
            $list = (array)$list;
            $list['gold'] = $list['gold'] / $this->goldUnit;
            $list['views'] = $list['views'] > 0 ? $this->generateRandViews($list['views']) : $this->generateRandViews(rand(5, 9));
            $list['preview_hls_url'] = $this->getPreviewPlayUrl($list['hls_url']??'');
            if(isset($list['time_at']) && ($list['time_at']>0)){
                $list['updated_at'] = date('Y-m-d H:i:s',$list['time_at']);
            }
            if (!$display_url) {
                unset($list['hls_url']);
                unset($list['dash_url']);
            }
            $domainSync = self::getDomain($list['sync']);
            //封面图处理
            $list['cover_img'] = $this->transferImgOut($list['cover_img'],$domainSync,$_v);
            if ($list['usage']??false) {
                unset($list['vs_id'], $list['vs_name'], $list['vs_gold'], $list['vs_cat'], $list['vs_sync'], $list['vs_title'], $list['vs_duration'], $list['vs_type'], $list['vs_restricted'], $list['vs_cover_img'], $list['vs_views'], $list['vs_updated_at'], $list['vs_hls_url'], $list['vs_dash_url'], $list['vs_url']);
            }
            //hls播放地址处理
            if(isset($list['hls_url'])){
                $list['hls_url'] = $domainSync . $this->transferHlsUrl($list['hls_url']??'');
            }
            $list['preview_hls_url'] = $domainSync . $list['preview_hls_url'];
            //是否点赞
            $videoRedis = $this->redis('video');
            $list['is_love'] = $videoRedis->sIsMember('videoLove_'.$uid,$list['id']) ? 1 : 0;
            //是否收藏
            $videoCollectsKey = 'videoCollects_'.$uid;
            $list['is_collect'] = $videoRedis->zScore($videoCollectsKey,$list['id']) ? 1 : 0;
            //
            unset($list['tagNames']);
            unset($list['tag']);
            unset($list['cat']);
            //标签
            isset($list['tag_kv']) && $list['tag_kv'] = json_decode($list['tag_kv'],true);
            //片名加前缀
            $list['name'] = '[海角原创]:'.$list['name'];
        }
        return $lists;
    }

    public function generateRandViews($views): string
    {
        $views = intval($views);
        $views *= 20;
        $length = strlen($views);
        if($length > 8){
            $str = substr_replace(floor($views * 0.0000001),'.',-1,0).'亿';
        }elseif($length > 4){
            $str = floor($views * 0.001) * 0.1.'万';
        }else{
            return $views;
        }
        return $str;
//        return ($views*10) * round(rand(1,9)/10,1).'万';
    }

    public function getPreviewPlayUrl($url,$type='hls'): array|string
    {
        $name = basename($url);
        $typeArr = [
            'hls' => '.m3u8',
            'dash' => '.mpd'
        ];
        return str_replace($name,'preview' . ($typeArr[$type]),$url);
    }

    public function getLocalSliceDir($pathInfo): string
    {
        return $pathInfo['dirname'].env('SLICE_DIR','/slice').'/'.$pathInfo['filename'];
    }


    /**
     * 同步封面
     * @param $img
     */
    public function syncCoverImg($coverImgPath)
    {
        $content = Storage::get($coverImgPath);
        $result = Storage::disk('sftp')->put($coverImgPath, $content);
        //
        $fileInfo = pathinfo($coverImgPath);
        $encryptFile = str_replace('/storage','/public',$fileInfo['dirname']).'/'.$fileInfo['filename'].'.htm';
        $r = Storage::disk('sftp')->put($encryptFile,$content);
        Log::info('==VideoEncryptImg==',[$encryptFile,$result,$r]);
    }

    /**
     * 截取视频封面
     * @return string
     * @throws Exception
     */
    public function generalCoverImgAtSliceDir($mp4FileName): string
    {
        $file_name = pathinfo($mp4FileName,PATHINFO_FILENAME);
        $format = new X264();
        $format->setAdditionalParameters(['-vcodec', 'copy', '-acodec', 'copy']); //跳过编码
        //$format = $format->setAdditionalParameters(['-hwaccels', 'cuda']);//GPU高效转码
        $model = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("local") //在storage/app的位置
        ->open($mp4FileName);
        $video = $model->export()->toDisk("local")->inFormat($format);
        //done 生成截图
        $frame = $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(1));
        $sliceDir = 'public'.env('SLICE_DIR','/slice');
        $cover_path = $sliceDir.'/'.$this->coverImgDir.'/'.$file_name.'/'.$file_name.'.jpg';
        $frame->save($cover_path);
        return $cover_path;
    }

    public function getVideoById($id)
    {
        return Cache::remember('cachedVideoById.'.$id, 7200, function() use($id) {
            return DB::table('video')->where('id',$id)->find($id);
        });
    }

}