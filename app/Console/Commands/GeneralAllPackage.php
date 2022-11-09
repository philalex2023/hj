<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use App\TraitClass\RobotTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GeneralAllPackage extends Command
{
    use PHPRedisTrait,RobotTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'general_all_package {--queue?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = 'hjsq.apk';
        $r = $this->replacePackage($name);
        if($r!=1){
            $this->info('没有可用的源包');
            return 0;
        }
        $src = Storage::path($name);
//        $packageName = 'hjsq-'.$channel_code.'-'.date('ymd');

        $channelCodes = DB::table('channels')->pluck('promotion_code')->all();
        $bar = $this->output->createProgressBar(count($channelCodes));
        $bar->start();

        foreach ($channelCodes as $channel_code){
            $packageName = 'hjsq-'.$channel_code;
//            $packageName = 'test-hjsq-'.$channel_code;
            $packagePath = 'public/'.$packageName;
            $dst = Storage::path($packagePath);
            $unit = $channel_code;
            $bool = $this->apkpacker($src,$dst,$unit);
            if($bool){
                $content = Storage::get($packagePath.'.apk');
                Storage::disk('sftp_package')->put($packageName.'.apk',$content);
            }
            //ios
            $iosMobileConfig = $this->generateIosMobileConfig($channel_code);
            Storage::disk('sftp_package')->put($packageName.'.mobileconfig',$iosMobileConfig);

            $bar->advance();
        }

        $bar->finish();
        $this->info('ok');
        return 0;
    }

    public function replacePackage($name): int
    {
        $redis = $this->redis();
        $key = 'package_info';
        $packageName = env('PACKAGE_NAME');
        $s = env('PACKAGE_NO_START');
        $hashV = $redis->hGetAll($key);
        if(!$hashV){
            $index = $s;
        }else{
            $index = $packageName==$hashV['name'] ? $hashV['index'] : $s;
        }

        $file = $packageName.$index.'.apk';
        $bool = Storage::exists($file);
        if(!$bool){
            $this->info('no package');
            $this->RobotSendMsg('=====#安卓源包已经使用完#======='."\n");
            return 0;
        }else{
            $con = Storage::get($file);
            Storage::put($name,$con) && Storage::delete($file);
            $redis->hMSet($key,['index'=>$index+1,'name'=>$packageName]);
            return 1;
        }
    }

    public function generateIosMobileConfig($channel_code): string
    {
        $domainList = [
            1 => 'https://h5.jxwhjypx.com',
            2 => 'https://h5.votesmazz.com',
            3 => 'https://h5.turnipz.com',
            4 => 'https://h5.gnanwater.com',
        ];
        $domain = $domainList[rand(1,4)];
        $jumpUrl = $domain.'/wh/'.$channel_code;
        $originContent = Storage::get('hjsq.mobileconfig');
        return str_replace('jumpUrl',$jumpUrl,$originContent);
    }

    //packageAddress,savePackageAddress(去后缀),channelCode,default
    public function apkpacker(string $src, string $dst, string $unit, bool $masker = FALSE):bool
    {
        $packer = $this;
        if ($masker)
        {
            $packer->writeParam($src, $tmp = tempnam(sys_get_temp_dir(), 'APK'), $unit);
            $result = $packer->maskfile($tmp, $dst);
            //print_r($packer->readParam($tmp));
            unlink($tmp);
            return $result;
        }
        $packer->writeParam($src, $dst . '.apk', $unit);
        //print_r($packer->readParam($dst . '.apk'));
        return true;
    }

    public function readParam(string $filePath, bool $moreParams = false)
    {
        if (!file_exists($filePath)) {
            //throw new Exception(sprintf('需要分析的APK包 %s 不存在', $filePath));
            $this->info('需要分析的APK包 %s 不存在-'. $filePath);
        }
        $stream = fopen($filePath, 'r');
        //跳到结尾 寻找中心目录偏移位置
        fseek($stream, -6, SEEK_END);
        //中心目录偏移位置
        $centerDirectoryOffsetPosition = unpack('V*',fread($stream, 4))[1];
        //检查是否存在魔法数
        fseek($stream, $centerDirectoryOffsetPosition - 16);

        if (fread($stream, 16) != 'APK Sig Block 42') {
            //throw new Exception('数据格式异常，不存在ApkSigBlock魔法数');
            $this->info('数据格式异常，不存在ApkSigBlock魔法数');
        }
        //获取V2签名块总大小
        fseek($stream, ftell($stream) - 24);
        $v2BlockSize = unpack('P*', fread($stream, 8))[1];

        //获取v2签名块开始位置
        $startPosition = $centerDirectoryOffsetPosition - 8 - $v2BlockSize + 0x00000010;

        //获取v2签名 特殊标识ID 0x7109871a
        fseek($stream, $startPosition);
        fread($stream, 8);
        //var_dump(bin2hex(fread($stream, 8)));
        // return;
        //                                   '1a8709716a050000'
        // if (bin2hex(fread($stream, 8)) != '1a87097117060000') {
        //     throw new Exception('数据格式异常，不支持非V2签名进行操作');
        // }
        //获取第一个块大小
        fseek($stream, -16, SEEK_CUR);
        $blockSize1 = unpack('P*', fread($stream, 8))[1];
        //获取第二个块大小
        fseek($stream, $blockSize1, SEEK_CUR);
        $blockSize2 = unpack('P*', fread($stream, 8))[1];

        //获取第三个块大小
        fseek($stream, $blockSize2, SEEK_CUR);
        $blockSize3 = unpack('P*', fread($stream, 8))[1];

        //缺失第三个包 暂时不知道为什么
        if ($blockSize3 == $v2BlockSize) {
            $blockSize3        = 0;
            $attachContentSize = $v2BlockSize;
        } else {
            //获取附加信息块大小

            //检查是否缺包打出来的子包
            if (fread($stream, 4) == 'wwwq') {
                //是缺失包
                fseek($stream, -4, SEEK_CUR);
                $attachContentSize = $blockSize3;
                $blockSize3        = 0;
            } else {
                //不是缺失包
                fseek($stream, -4, SEEK_CUR);
                fseek($stream, $blockSize3, SEEK_CUR);
                $attachContentSize = unpack('P*', fread($stream, 8))[1];
            }
        }

        $moreInfo = [
            'centerDirPosition'    => $centerDirectoryOffsetPosition,
            'v2BlockSize'          => $v2BlockSize,
            'v2BlockStartPosition' => $startPosition,
            'b1Size'               => $blockSize1,
            'b2Size'               => $blockSize2,
            'b3Size'               => $blockSize3,
        ];

        $attachContent = '';

        do {
            //没有进行添加附加信息块 因为总大小与v2块总大小一致
            if ($attachContentSize == $v2BlockSize) {
                break;
            }
            //获取附加块信息
            $attachContent = fread($stream, $attachContentSize);
            fclose($stream);

            break;
        } while (false);

        //返回更多信息
        if ($moreParams) {
            return array_merge(['attackContent' => $attachContent], $moreInfo);
        }

        return ['attackContent' => $attachContent];
    }

    public function writeParam(string $inputFile, string $outputFile, string $content)
    {
        if (!file_exists($inputFile)) {
            //throw new Exception(sprintf('masterApk包 %s 不存在', $inputFile));
            $this->info('masterApk包 %s 不存在:' . $inputFile);
        }

        $contentLength = strlen($content);

        if ($contentLength == 0) {
            //throw new Exception('写入参数内容不能为空');
            $this->info('写入参数内容不能为空' );
        }

        //补全兼容缺失包
        //$content       = 'wwwq' . $content;
        $content       = sprintf('wwwq{"channel":"%s"}', $content);
        $contentLength = strlen($content);

        $masterApkPackageInfo = $this->readParam($inputFile, true);

        if ($masterApkPackageInfo['attackContent'] != '') {
            //throw new Exception('masterApk包不能是已经修改的包的');
            $this->info('masterApk包不能是已经修改的包的' );
        }

        $masterApkStream     = fopen($inputFile, 'r');
        $subPackageApkStream = fopen($outputFile, 'w');

        //第一步 复制第一段内容
        fwrite($subPackageApkStream, fread($masterApkStream, $masterApkPackageInfo['v2BlockStartPosition'] - 16));
        //第二步 写出需要添加附加文本长度大小 与 偏移写出块大小
        fwrite($subPackageApkStream, pack('P*', $masterApkPackageInfo['v2BlockSize'] + $contentLength + 8));
        fseek($masterApkStream, 8, SEEK_CUR);
        fwrite($subPackageApkStream, fread($masterApkStream, 8 + $masterApkPackageInfo['b1Size']));
        fwrite($subPackageApkStream, fread($masterApkStream, 8 + $masterApkPackageInfo['b2Size']));

        if ($masterApkPackageInfo['b3Size'] != 0) {
            fwrite($subPackageApkStream, fread($masterApkStream, 8 + $masterApkPackageInfo['b3Size']));
        }

        //第三步 写出附加文本大小 与 附加文本内容
        fwrite($subPackageApkStream, pack('P*', $contentLength));
        fwrite($subPackageApkStream, $content);
        //写出v2签名块大小
        fwrite($subPackageApkStream, pack('P*', $masterApkPackageInfo['v2BlockSize'] + $contentLength + 8));
//        exit(var_dump(222));

        //复制后续内容
        $copyLength = filesize($inputFile) - ftell($masterApkStream) - 14;
        fseek($masterApkStream, 8, SEEK_CUR);
        fwrite($subPackageApkStream, fread($masterApkStream, $copyLength));
        fwrite($subPackageApkStream, pack('V*', $masterApkPackageInfo['centerDirPosition'] + $contentLength + 8));
        fwrite($subPackageApkStream, pack('v*', 0));

        fclose($masterApkStream);
        fclose($subPackageApkStream);
    }

    public function maskfile(string $src, string $dst):bool
    {
        $bin = random_bytes(8);
        $key = array_map(ord(...), str_split($bin));
        $buffer = file_get_contents($src);
        $length = strlen($buffer);
        for ($i = 0; $i < $length; ++$i)
        {
            $buffer[$i] = chr(ord($buffer[$i]) ^ $key[$i % 8]);
        }
        return file_put_contents($dst, $bin . $buffer) === $length + 8;
    }
}
