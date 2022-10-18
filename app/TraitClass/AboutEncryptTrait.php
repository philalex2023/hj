<?php

namespace App\TraitClass;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait AboutEncryptTrait
{
    public function transferImgOut($img,$domain=null,$_v=null,$fixType='jpg'): string
    {
        if(!$img){
            return '';
        }
        $domain = $domain ?? env('RESOURCE_DOMAIN');
        $_v = $_v ?? 1;
        $fileInfo = pathinfo($img);
        if(!isset($fileInfo['dirname'])){
            return '';
        }
        /*if($fixType == 'auto'){
            $image_info = @getimagesize($domain . $img);
            $fixType = $image_info['mime'] ?? 'jpg';
        }*/
        return $domain . $fileInfo['dirname'].'/'.$fileInfo['filename'].'.htm?ext='.$fixType.'&_v='.$_v;
    }

    public function transferHlsUrl($url,$id=null,$_v=null): string
    {
        $_v = $_v ?? 1;
        $hlsInfo = pathinfo($url);
        if(!isset($hlsInfo['dirname'])){
            return '';
        }
        if($hlsInfo['filename']=='preview'){
            return $url;
        }
//鉴权时需要        return $hlsInfo['dirname'].'/'.$hlsInfo['filename'].'_0_1000.m3u8';
        return $hlsInfo['dirname'].'/'.$hlsInfo['filename'].'.m3u8';

    }

    public function getContentByUrl($url)
    {
        $client = new Client([
            'verify' => false,
        ]);
        return $client->get($url)->getBody()->getContents();
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function syncUpload($img,$sync=2)
    {
        $abPath = public_path().$img;
        if((file_exists($abPath) && is_file($abPath)) || Storage::disk('sftp')->exists(str_replace('/storage','/public',$img))){
            $content = @file_get_contents($abPath);
            if(!$content){
                $content = $this->getContentByUrl(VideoTrait::getDomain(env('SFTP_SYNC',1)).$img);
            }
            Log::info('==ImgFile==',[$img]);
            $put = $sync==1 ? Storage::disk('sftp1')->put($img,$content) : Storage::disk('sftp')->put($img,$content);
            //加密
            if($put){
                $fileInfo = pathinfo($img);
                $encryptFile = str_replace('/storage','/public',$fileInfo['dirname']).'/'.$fileInfo['filename'].'.htm';
                $r = $sync==1 ? Storage::disk('sftp1')->put($encryptFile,$content) : Storage::disk('sftp')->put($encryptFile,$content);
                Log::info('==encryptImg==',[$encryptFile,$r]);
            }
        }
    }

}