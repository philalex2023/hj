<?php
namespace App\Http\Controllers\Admin;

use App\Models\Config;
use App\Models\User;
use App\Models\Withdraw;
use App\TraitClass\AdTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class DbOperateController extends BaseCurlController
{
    use PHPRedisTrait,AdTrait;
    //去掉公共模板
    public $commonBladePath = '';
    public $pageName = 'DB操作';


    public function index()
    {
        $querySql = request('querySql');
        $type = request('type');
        if($type && $querySql){
            $res = match($type){
                'select' =>  DB::connection()->select($querySql),
                'update' =>  DB::connection()->update($querySql)
            };
            // $res = DB::connection()->delete($sql);
            if(is_array($res)){
                $n = count($res);
                $r = '';
                foreach ($res as $s){
                    $r .= json_encode($s,JSON_UNESCAPED_UNICODE)."\n";
                }
                $res = $r;
            }else{
                $res = json_encode($res,JSON_UNESCAPED_UNICODE);
            }
            //$res = is_int($res) ?: json_encode($res);
        }
        return $this->display(['parameters'=>$res??'','querySql'=>$querySql,'type'=>$type,'numbers'=>$n??'']);
    }

    public function submitPost(Request $request)
    {
        /* return response()->json([
            'code' => 200,
            'msg' => '提交成功'
        ]); */
        $params = $request->all(['querySql','type']);

        return $request->wantsJson()
            ? new Response('', 204)
            : redirect()->route('admin.dbOperate.index',['querySql'=>$params['querySql'],'type'=>$params['type']]);
    }

}
