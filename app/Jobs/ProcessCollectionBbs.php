<?php

namespace App\Jobs;

use App\Models\CommBbs;
use App\TraitClass\CurlTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCollectionBbs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CurlTrait;

    public function decodeImgUrl($data)
    {
        $d = 0;
        $e = "ABCD*EFGHIJKLMNOPQRSTUVWX#YZabcdefghijklmnopqrstuvwxyz1234567890";
        $t = preg_replace("/[^A-Za-z0-9\*\#]/","",$data);
        //var_dump($t);
        $str = "";
        $len = strlen($t);
        while ($d < $len){
            $r = strpos($e,$t[$d++]) ;
            $s = strpos($e,$t[$d++]) ;
            $c = $d < $len ? strpos($e, $t[$d++]) : 64;
            $u = $d < $len ? strpos($e, $t[$d++]) : 64;
            $o = $r << 2 | $s >> 4;
            $i = (15 & $s) << 4 | $c >> 2;
            $a = (3 & $c) << 6 | $u;
            $str .= chr($o);
            64 != $c && ($str .= chr($i));
            64 != $u && ($str .= chr($a));
            //l += String.fromCharCode(o),
        }
        //var_dump($t);
        $con = substr(strstr($str,','),1);
        return base64_decode($con);
    }

    public function getImgSrcValue($matchIMG): array
    {
        $srcArr = [];
        foreach ($matchIMG[0] as $key => $imgTag){
            //进一步提取 img标签中的 src属性信息
            $pattern_src = '/\bsrc\b\s*=\s*[\'\"]?([^\'\"]*)[\'\"]?/i';
            preg_match_all($pattern_src,$imgTag,$matchSrc);
            if (isset($matchSrc[1])){
                foreach ($matchSrc[1] as $src){
                    //将匹配到的src信息压入数组
                    $res = file_get_contents($src);
                    Log::info('getImgSrcValue',[$src]);
                    $imgContent = $this->decodeImgUrl($res);
                    $file_name = md5(date('ym').pathinfo($src,PATHINFO_FILENAME));
                    $imgFile = '/upload/collection/'.$file_name.'/'.$file_name.'.htm';
                    Storage::disk('ftp')->put($imgFile,$imgContent); //save
                    $srcArr[] = $imgFile;
                }
            }
        }
        return $srcArr;
    }

    public function getVideoSrcValue($matchIMG): array
    {
        $srcArr = [];
        foreach ($matchIMG[0] as $key => $imgTag){
            //进一步提取 img标签中的 src属性信息
            $pattern_src = '/\bsrc\b\s*=\s*[\'\"]?([^\'\"]*)[\'\"]?/i';
            preg_match_all($pattern_src,$imgTag,$matchSrc);
            if (isset($matchSrc[1])){
                foreach ($matchSrc[1] as $src){
                    //将匹配到的src信息压入数组
                    $pathInfo = pathinfo($src);
                    $file_name = md5(date('ym').$pathInfo['filename']);
                    $m3u8Content = $this->curlByUrl($src);

                    $tmpPath = '/public/slice/hls/'.$file_name.'/tmp.m3u8';
                    $put = Storage::disk('ftp')->put($tmpPath,$m3u8Content); //save
                    $localFile = env('RES_ROOT').$tmpPath;
                    Log::info('putM3u8TmpFile',[$put]);
                    $texts = file($localFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $m3u8Text = '';
                    foreach ($texts as &$line){
                        if(str_contains($line, '#EXT-X-KEY')){
                            $arr = explode('"',$line);
                            $keyUrl = $pathInfo['dirname'].'/'.$arr[1];
                            $keyContent = $this->curlByUrl($keyUrl);
                            Storage::disk('ftp')->put('/public/slice/hls/'.$file_name.'/'.$arr[1],$keyContent);
                        }
                        if(str_contains($line, 'https://')){
                            $tsContent = $this->curlByUrl($line);
                            $line = pathinfo($line,PATHINFO_BASENAME);
                            Storage::disk('ftp')->put('/public/slice/hls/'.$file_name.'/'.$line,$tsContent);
                        }
                        $m3u8Text .= $line."\r\n";
                    }
                    $m3u8File = '/public/slice/hls/'.$file_name.'/'.$file_name.'.m3u8';
                    $put2 = Storage::disk('ftp')->put($m3u8File,$m3u8Text); //save
                    Log::info('putM3u8File',[$put2]);
                    Storage::disk('ftp')->delete($tmpPath);
                    $srcArr[] = $m3u8File;
                }
            }
        }
        return $srcArr;
    }

    public array $resArr = [];

    public int $id = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($resArr,$id)
    {
        //
        $this->resArr = $resArr;
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $resArr = $this->resArr;
        $r = [
            'id' => $this->id,
            'title' => $resArr['data']['title'],
            'content' => $resArr['data']['content'],
            'thumbs' => [],
            'videos' => [],
            'video_picture' => [],
        ];
        if(isset($resArr['data']['attachments']) && !empty($resArr['data']['attachments'])){
            foreach ($resArr['data']['attachments'] as $attachment){
                if($attachment['category']=='video'){
                    $coverSourceCon = file_get_contents($attachment['coverUrl']);
                    Log::info('getCoverUrl',[$attachment['coverUrl'],(bool)$coverSourceCon]);
                    $coverContent = $this->decodeImgUrl($coverSourceCon);
                    $file_name = md5(date('ym').pathinfo($attachment['coverUrl'],PATHINFO_FILENAME));
                    $coverFile = '/public/slice/coverImg/'.$file_name.'/'.$file_name.'.htm';
                    Storage::disk('ftp')->put($coverFile,$coverContent); //save
                    $r['video_picture'][] = $coverFile;
                }
            }
        }
        //提取文字、图片和视频
        //图片
        preg_match_all('/<img\b.*?(?:\>|\/>)/i',$r['content'],$matchIMG);
        if (isset($matchIMG[0])){
            //print_r($matchIMG);
            foreach ($matchIMG as $imgEle){
                $r['content'] = str_replace($imgEle,'',$r['content']);
            }
            $r['thumbs'] = $this->getImgSrcValue($matchIMG);
        }
        //视频
        $pattern_VideoTag = '/<video\b.*?(?:\>|\/>)/i';
        preg_match_all($pattern_VideoTag,$r['content'],$matchVideo);
        if(isset($matchVideo[0])){
            foreach ($matchVideo as $videoEle){
                $r['content'] = str_replace($videoEle,'',$r['content']);
            }
            $r['videos'] = $this->getVideoSrcValue($matchVideo);
        }
        //文字
        $r['content'] = strip_tags($r['content']);

        $insertData = [
            'author_id' => $r['id'],
            'category_id' => 12,
            'status' => 1,
            'thumbs' => json_encode($r['thumbs'],JSON_UNESCAPED_UNICODE),
            'content' => trim(json_encode($r['content'],JSON_UNESCAPED_UNICODE),'"'),
            'title' => trim(json_encode($r['title'],JSON_UNESCAPED_UNICODE),'"'),
            'video' => json_encode($r['videos'],JSON_UNESCAPED_UNICODE),
            'video_picture' => json_encode($r['video_picture'],JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        CommBbs::query()->insert($insertData);
    }
}
