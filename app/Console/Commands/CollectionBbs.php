<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGetApiVideo;
use App\Models\CommBbs;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use AWS\CRT\Log;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CollectionBbs extends Command
{
    use PHPRedisTrait,DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:bbs';

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

    public function decodeImgUrl($data)
    {
        $d = 0;
        $e = "ABCD*EFGHIJKLMNOPQRSTUVWX#YZabcdefghijklmnopqrstuvwxyz1234567890";
        $t = preg_replace("/[^A-Za-z0-9\*\#]/","",$data);
        //var_dump($t);
        $str = "";
        while ($d < strlen($t)){
            $r = strpos($e,$t[$d++]) ;
            $s = strpos($e,$t[$d++]) ;
            $c = strpos($e,$t[$d++]) ;
            $u = strpos($e,$t[$d++]) ;
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

    public function getSrcValue($matchIMG): array
    {
        $srcArr = [];
        foreach ($matchIMG[0] as $key => $imgTag){
            //进一步提取 img标签中的 src属性信息
            $pattern_src = '/\bsrc\b\s*=\s*[\'\"]?([^\'\"]*)[\'\"]?/i';
            preg_match_all($pattern_src,$imgTag,$matchSrc);
            if (isset($matchSrc[1])){
                foreach ($matchSrc[1] as $src){
                    //将匹配到的src信息压入数组
                    $srcArr[] = $src;
                }
            }
        }
        return $srcArr;
    }
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $calc = 0;
        $limit = 5;
        for ($i=487807; $i<499807; ++$i){
            $apiUrl = 'https://www.hjedd.com/api/topic/'.$i;
            $response = (new Client([
                'headers' => ['Content-Type' => 'application/json']
            ]))->get($apiUrl, [
                'verify' => false
            ])->getBody()->getContents();
            //
//        print_r(json_decode($response,true));
            $resArr = json_decode($response,true);
            if($resArr['success']==1){
                $r = [
                    'id' => $i,
                    'title' => $resArr['data']['title'],
                    'content' => $resArr['data']['content'],
                    'thumbs' => [],
                    'videos' => [],
                    'video_picture' => [],
                ];
                if(isset($resArr['data']['attachments']) && !empty($resArr['data']['attachments'])){
                    foreach ($resArr['data']['attachments'] as $attachment){
                        $attachment['category']=='video' && $r['video_picture'][] = $attachment['coverUrl'];
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
                    $r['thumbs'] = $this->getSrcValue($matchIMG);
                }
                //视频
                $pattern_VideoTag = '/<video\b.*?(?:\>|\/>)/i';
                preg_match_all($pattern_VideoTag,$r['content'],$matchVideo);
                if(isset($matchVideo[0])){
                    foreach ($matchVideo as $videoEle){
                        $r['content'] = str_replace($videoEle,'',$r['content']);
                    }
                    $r['videos'] = $this->getSrcValue($matchVideo);
                }
                //文字
                $r['content'] = strip_tags($r['content']);
                ++$calc;
                print_r( $r);
                //todo 图片解密保存
                //$this->decodeImgUrl()
                $insertData = [
                    'thumbs' => json_encode($r['thumbs']),
                    'content' => json_encode($r['content']),
                    'title' => json_encode($r['title']),
                    'video' => json_encode($r['title']),
                    'video_picture' => json_encode($r['video_picture']),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                CommBbs::query()->insert($insertData);

                if($calc==$limit){
                    break;
                }
            }else{
                $this->info('error:'.$response);
            }
        }

        return 0;


    }
}
