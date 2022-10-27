<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Jobs\UserVideoSlice;
use App\Models\UserVideo;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class FileUploadController extends Controller
{
    static public function getUrl($relativePath)
    {
        return asset( '/storage'.ltrim($relativePath,'public'));
    }

    public function uploadImg(Request $request)
    {
        $relativePath = $request->file("file")->store('public/userImg');
        $data = array(
            'code' => 0,
            'msg'  => '上传成功',
            'data' => array(
                'src'   => self::getUrl($relativePath)
            ),
//            'params' => $params
        );
        return response()->json($data);
    }

}
