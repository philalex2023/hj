<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$useSecret = \Illuminate\Support\Env::get("MIDDLEWARE_SECRET");

Route::group([
    'namespace'=>'Api',
    'middleware' => $useSecret ? ['secret'] : []
],function (){
    Route::get('config', 'ConfigController@ack');   //配置
    Route::post('robotsUpdate', 'ConfigController@robotsUpdate');   //机器人更新
    Route::post('login', 'AuthController@login')->name('login');
});

$payNames = [
    'YK', //YK
    'AX', //艾希
    'YL', //盈联
    'KF', //咖啡
    'NY', //能源
];

Route::group([
    'namespace'=>'Api',
],function () use ($payNames){
    //Route::post('kfUrl','ForwardController@kfAgent'); //客服转发服务链接
    if(\Illuminate\Support\Env::get("APP_ENV")!=='production'){
        Route::post('aes_en', 'TestController@aes_en');
        Route::post('aes_de', 'TestController@aes_de');
    }
    Route::get('pullOriginVideo', 'ConfigController@pullOriginVideo');
    foreach ($payNames as $n){
        Route::post('callback'.$n, 'PayCallbackController@callback'.$n);  //支付回调
    }
    Route::any('checkAdByQrcode','UserController@checkAdByQrcode'); //扫描二维码获取原账号信息
});

Route::group([
    'namespace'=>'Api',
    'middleware' => $useSecret ? ['auth:api','secret','scopes:check-user'] : ['auth:api','scopes:check-user']
], function() use ($payNames){
    Route::get('getAreaNum','UserController@getAreaNum'); //获取国际码
    Route::get('rechargeMethods','RechargeController@methods'); //充值方式
    Route::post('viewShare','VideoController@actionShare'); //分享
    Route::post('viewCollect','VideoController@actionCollect'); //收藏
    Route::post('viewLike','VideoController@actionLike'); //点赞/喜欢
    Route::post('viewVideo','VideoController@actionView'); //观看
    Route::get('hotWords', 'SearchController@hotWords');    //热搜关键词
    Route::get('search', 'SearchController@index');    //搜索
    Route::get('searchOption', 'SearchController@getOption');    //搜索
    Route::get('searchTag', 'SearchController@tag');    //标签内容接口
    Route::get('hotTags', 'SearchController@hotTags');    //热门标签
    Route::get('searchRecommend', 'SearchController@recommend'); //推荐
    Route::get('searchCat', 'SearchController@cat');    //分类搜索-更多
    Route::post('lists', 'HomeController@lists');      //列表
    Route::get('rechargeActivity', 'HomeController@rechargeActivity'); //充值活动
    Route::get('category', 'HomeController@category'); //分类
    Route::get('carousel', 'HomeController@carousel'); //轮播
    Route::post('commentList', 'CommentController@lists');      //评论列表
    Route::post('comment', 'CommentController@submit');      //评论
    Route::post('commentReply', 'CommentController@reply');  //回复评论
    Route::get('logout', 'AuthController@logout');   //登出接口
    Route::get('vipInfo', 'VipController@memberCards');     //会员卡
    Route::get('gold', 'VipController@gold');     //金币
    Route::get('userExtendInfo', 'UserController@extendInfo');     //用户扩展信息
    Route::post('user', 'UserController@set');     //用户设置
    Route::get('myShare', 'UserController@myShare');     //我的分享
    Route::post('billing ', 'UserController@billing');     //账单记录
    Route::post('billingClear ', 'UserController@billingClear');     //账单记录
    Route::get('myCollect', 'UserController@myCollect');     //收藏列表
    Route::get('viewHistory', 'UserController@viewHistory');     //观看历史
    Route::get('overViewHistory', 'UserController@overViewHistory');     //观看历史概览
    Route::post('bindInviteCode','UserController@bindInviteCode'); //手动绑定邀请码
    Route::post('bindPhone','UserController@bindPhone'); //绑定手机
    Route::post('sendSmsCode','UserController@sendSmsCode'); //发送短信验证码
    Route::post('findADByPhone','UserController@findADByPhone'); //手机找回账号
    Route::post('findAdByQrcode','UserController@findAdByQrcode'); //二维码找回账号

    Route::post('payBill', 'PayController@bill');  //支付

    /// 订单相关接口
    Route::post('oderCreate', 'OrderController@create');  //订单创建接口
    Route::get('orderQuery', 'OrderController@query');  //订单查询接口

    /// 社区模块
    Route::get('commCate', 'CommCateController@info');  //板块分类
    Route::get('commCity', 'CommMiscController@city');  //地区列表
    Route::get('commList', 'CommContentController@lists');  //内容列表
    Route::get('commDetail', 'CommContentController@detail');  //内容列表
    Route::post('commFocus', 'CommOperationController@foucs');  //关注用户
    Route::post('commLike', 'CommOperationController@like');  //点赞用户
    Route::get('commComment', 'CommCommentController@lists');  //帖子评论
    Route::post('commCommentPost', 'CommCommentController@post');  //发表评论
    Route::post('commReward', 'CommRewardController@action');  //打赏
    Route::get('commMessage', 'CommMessageController@lists');  //消息列表
    Route::get('commHome', 'CommHomeController@info');  //个人详情
    Route::get('commChat', 'CommChatController@lists');  //私聊-消息列表
    Route::post('commChatPost', 'CommChatController@post');  //私聊-发送消息
    Route::post('commRes', 'CommMiscController@res');  //发送资源
    Route::post('commBbs', 'CommContentController@post');  //发贴

    Route::get('communityPopularSearchVideo', 'CommunityController@popularSearchVideo');  //热门搜索
    Route::get('communityPopularSearchWords', 'CommunityController@popularSearchWords');  //热搜词
    Route::get('myData', 'CommunityController@myData');  //我的数据
    Route::get('myPurse', 'CommunityController@myPurse');  //我的钱包
    Route::get('communityRankingCate', 'CommunityController@rankingCate');  //视频榜分类
    Route::get('communityCircleCate', 'CommunityController@circleCate');  //圈子分类
    Route::get('communityCircle', 'CommunityController@circle');  //圈子更多列表
    Route::get('communityFocus', 'CommunityController@focus');  //社区关注
    Route::get('communitySquare', 'CommunityController@square');  //社区广场
    Route::get('communityTopic', 'CommunityController@topic');  //社区话题
    Route::get('communityPopularLikes', 'CommunityController@popularLikes');  //猜你喜欢
    Route::get('communityPopularCircle', 'CommunityController@popularCircle');  //热门圈子
    Route::post('communityPurchasedVideos', 'CommunityController@purchasedVideos');  //购买的视频
    Route::post('communityMyCollect', 'CommunityController@myCollect');  //收藏的视频
    Route::post('communityMore', 'CommunityController@more');  //更多
    Route::post('communityCircleFeaturedWithVideo', 'CommunityController@circleFeaturedWithVideo');  //圈子精选视频
    Route::post('communitySearchVideoByCate', 'CommunityController@searchVideoByCate');  //根据分类搜索视频
    Route::post('communitySearchVideo', 'CommunityController@searchVideo');  //搜索视频
    Route::post('communitySearchTopic', 'CommunityController@searchTopic');  //搜索话题
    Route::post('communitySearchCircle', 'CommunityController@searchCircle');  //搜索圈子
    Route::post('communitySearchMix', 'CommunityController@searchMix');  //搜索综合
    Route::post('communityPersonalLikes', 'CommunityController@personalLikes');  //喜欢
    Route::post('communityPersonalCollection', 'CommunityController@personalCollection');  //合集
    Route::post('communityPersonalWork', 'CommunityController@personalWork');  //作品
    Route::post('communityPersonalDynamic', 'CommunityController@personalDynamic');  //动态
    Route::post('myCreatedCircle', 'CommunityController@myCreatedCircle');  //创建的圈子
    Route::post('myJoinedCircle', 'CommunityController@myJoinedCircle');  //加入的圈子
    Route::post('communityPersonalInfo', 'CommunityController@personalInfo');  //个人资料页面
    Route::post('communityTopicList', 'CommunityController@topicList');  //话题列表
    Route::post('communityTopicDetail', 'CommunityController@topicDetail');  //话题详情
    Route::post('communityCircleUserList', 'CommunityController@circleUserList');  //圈友列表
    Route::post('communityFansList', 'CommunityController@fansList');  //粉丝列表
    Route::post('communityUpMasterRank', 'CommunityController@upMasterRank');  //UP主人气榜
    Route::post('communityTopicRank', 'CommunityController@topicRank');  //话题榜
    Route::post('communityCircleRank', 'CommunityController@circleRank');  //圈子榜
    Route::post('communityRankList', 'CommunityController@rankList');  //视频榜
    Route::post('communityCircleDetail', 'CommunityController@circleDetail');  //圈子详情
    Route::post('communityCollectionDetail', 'CommunityController@collectionDetail');  //合集详情
    Route::post('communityBuyCollection', 'CommunityController@buyCollection');  //解锁合集
    Route::post('communityActionEvent', 'CommunityController@actionEvent');  //加入、喜欢、关注事件
    Route::post('fromMeFocusCircle', 'CommunityController@fromMeFocusCircle');  //来自我关注的圈子
    Route::post('circleFeatured', 'CommunityController@circleFeatured');  //圈子精选
    Route::post('communityDiscuss', 'CommunityController@discuss');  //讨论
    Route::post('communityVideo', 'CommunityController@video');  //视频
    Route::post('communityWorkVideo', 'CommunityController@workVideo');  //我的作品-视频/短视频
    Route::post('communityWorkCollection', 'CommunityController@workCollection');  //我的作品-合集
    Route::post('communityCollection', 'CommunityController@collection');  //合集
    Route::post('addCircleTopic', 'CommunityController@addCircleTopic');  //创建话题
    Route::post('addCircle', 'CommunityController@addCircle');  //创建圈子

    /// 小视频模块
    Route::get('shortCate', 'VideoShortController@cate');  //视频分类
    Route::get('shortList', 'VideoShortController@lists');  //播放列表
    Route::post('shortLike', 'VideoShortController@like');  //小视频点赞
    Route::post('shortCollect', 'VideoShortController@collect');  //收藏
    Route::post('buyShortWithGold', 'VideoShortController@buyShortWithGold');  //金币购买小视频
    /// 小视频评论
    Route::post('shortCommentList', 'CommentShortController@lists');      //评论列表
    Route::post('shortComment', 'CommentShortController@submit');      //评论
    Route::post('shortCommentReply', 'CommentShortController@reply');  //回复评论

    /// 伪直播模块
    Route::get('liveList', 'FakeLiveShortController@lists');  //播放列表
    Route::post('liveCalc', 'FakeLiveShortController@calc');  //直播统计
});
