<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ServerMonitor extends Command
{
    public array $cfConfig = [
        'api_token' => ' Bearer ZFVkRiq4yKYyUjZUbE366tZ4PehUxExnMIo7DtOm',
        'account_id' => 'c5859af5d571bbeb3b804247b45d735b',
    ];

    public array $host_domains = [
        'http://api.saolv4.com' => [
            '16.163.94.72' => [
                'api.saolv4.com',
                'api.saolv1.com',
            ]
        ],
        'http://47.243.176.111' => [
            '18.162.36.162' => [
                'youhuioff.com',
                'gptj365.com',
            ]
        ],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:monitor {revoke?}';

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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {
        $revoke = $this->argument('revoke');
        //下方获取chat_id
        //$apiGetChatIDUrl = 'https://api.telegram.org/bot' .$tgApiToken.'/getUpdates';
        //https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/getMe
//        $res = $this->pingAddress($address);
        foreach ($this->host_domains as $host => $domains){
            $res = Http::get($host)->ok();
            //dd($res);
            if(!$res){
                $this->sendMsg('服务器地址-'.$host.'出现异常');
                foreach ($domains as $bindHost => $domain) {
                    $zone_id = $this->checkZone($domain);
                    $this->info('###zone_id###'.$zone_id);
                    if(!$zone_id){
                        $zone_id = $this->addZone($domain);
                    }else{
                        $this->getDNSRecordAndDel($domain,$zone_id,$bindHost);
                    }
                    if(!$revoke){
                        $this->addDNSRecord($domain,$zone_id,$bindHost);
                    }else{
                        $this->sendMsg('撤销域名'.$domain.'的CF解析记录');
                    }
                }
                $this->info('###'.$host.'异常###');
            }else{
                $this->info('###'.$host.'正常###');
            }
        }

        return 0;
    }

    public function getDNSRecordAndDel($domain,$zone_id,$bindHost)
    {
        $api = 'https://api.cloudflare.com/client/v4/zones/'.$zone_id.'/dns_records';
        $input = [
            'type' => 'A',
            'name' => $domain,
            'content' => $bindHost,
            'proxied' => false,
            'account.name' => '',
            'page' => 1,
            'per_page' => 100,
            'order' => 'type',
            'direction' => 'desc',
            'match' => 'all',
        ];
        $res = (new Client([
            'headers' => ['Content-Type' => 'application/json','Authorization' => $this->cfConfig['api_token']],
            'verify' => false,
        ]))->get($api,['json' => $input]);
        $content = $res->getBody()->getContents();
        $this->info('###getDNSRecordAndDel###');
        $results = json_decode($content,true)['result'];
        foreach ($results as $result)
        {
            $this->delDNSRecord($result['zone_id'],$result['id']);
        }
    }

    public function delDNSRecord($zone_id,$recordId)
    {
        $api = 'https://api.cloudflare.com/client/v4/zones/'.$zone_id.'/dns_records/'.$recordId;
        $res = (new Client([
            'headers' => ['Content-Type' => 'application/json','Authorization' => $this->cfConfig['api_token']],
            'verify' => false,
        ]))->delete($api);
        $content = $res->getBody()->getContents();
        return json_decode($content,true)['result']['id'];
    }

    public function checkZone($domain)
    {
        $api = 'https://api.cloudflare.com/client/v4/zones';
        $input = [
            'name' => $domain,
            //'status' => 'active',
            'account.id' => $this->cfConfig['account_id'],
            'account.name' => 'foxyloon1993@gmail.com',
            'page' => 1,
            'per_page' => 500,
            //'order' => 'status',
            //'direction' => 'desc',
            'match' => 'all',
        ];
        $api = $api.'?'.http_build_query($input);
        $this->info($api);
        $res = (new Client([
            'headers' => ['Authorization' => $this->cfConfig['api_token']],
            'verify' => false,
        ]))->get($api);
        $contents = $res->getBody()->getContents();
        $this->info('###checkZone###=>'.$contents);
        $this->sendMsg('检测域名-'.$domain.'到CF');

        $results = json_decode($contents,true)['result'];
        $id = '';
        foreach ($results as $result){
            if($result['name']==$domain){
                $id = $result['id'];
            }
        }
        return $id;
    }

    public function addZone($domain)
    {
        $cfAddZoneUrl = 'https://api.cloudflare.com/client/v4/zones';
        //添加域名
        $jsonAddZoneData = [
            'name' => $domain,
            'account' => ['id'=>$this->cfConfig['account_id']],
            'jump_start' => true,
            'type' => 'full',
        ];
        $curlCF = (new Client([
            'headers' => ['Content-Type' => 'application/json','Authorization' => $this->cfConfig['api_token']],
            'verify' => false,
        ]))->post($cfAddZoneUrl,['json' => $jsonAddZoneData]);
        $responseStr = $curlCF->getBody()->getContents();
        $this->info('##addZone###');
        $this->sendMsg('添加域名'.$domain.'到CF');
        return json_decode($responseStr,true)['result']['id'];
    }

    public function addDNSRecord($domain,$zone_id,$bindHost)
    {

        //$cfDelZoneUrl = 'https://api.cloudflare.com/client/v4/zones/aa537cf634ff10c2f10f5cd35bfb882f';

        //解析
        $jsonAddRecodeData = [
            'type' => 'A',
            'name' => $domain,
            'content' => $bindHost,
            'ttl' => 60,
            'priority' => 10,
            'proxied' => true,
        ];
        $addRecodeUrl = 'https://api.cloudflare.com/client/v4/zones/'.$zone_id.'/dns_records';
        $curlCFRecode = (new Client([
            'headers' => ['Content-Type' => 'application/json','Authorization' => $this->cfConfig['api_token']],
            'verify' => false,
        ]))->post($addRecodeUrl,['json' => $jsonAddRecodeData]);
        $responseStr = $curlCFRecode->getBody()->getContents();
        $this->info($responseStr);
        $responseArr = json_decode($responseStr,true);
        if($responseArr['success']){
            $this->sendMsg('域名'.$domain.'使用CF解析成功');
        }else{
            $this->sendMsg('域名'.$domain.'使用CF解析失败');
        }
    }

    public function sendMsg($msg='')
    {
        //通知
        $tgApiToken = '5463455642:AAFPPpmsx_b4UvrQvlHZzKyd2ItxMIQnhgM';
        $apiUrl = 'https://api.telegram.org/bot' .$tgApiToken.'/sendMessage';
        $input = [
            'chat_id'=>'-1001729090537',
//            'text'=>'落地页服务器可能遭受攻击出现异常，正在进行CF设置...,响应内容:'.$responseStr,
            'text'=>$msg,
        ];
        $curl = (new Client([
            //'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'verify' => false,
        ]))->post($apiUrl,['form_params' => $input]);
        $this->info($curl->getBody()->getContents());
    }

    public function pingAddress($address): bool
    {
        $status = -1;
        if (strcasecmp(PHP_OS, 'WINNT') === 0) {
            // Windows 服务器下
            $pingresult = exec("ping -n 1 {$address}", $outcome, $status);
        } elseif (strcasecmp(PHP_OS, 'Linux') === 0) {
            // Linux 服务器下
            $pingresult = exec("ping -c 1 {$address}", $outcome, $status);
        }
        if (0 == $status) {
            $status = true;
        } else {
            $status = false;
        }
        return $status;
    }
}
