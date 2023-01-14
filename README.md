php-fpm、nginx服务手动重启
kill -9 `ps -ef |grep php-fpm|awk '{print $2}' `
/usr/local/php8/sbin/php-fpm
chown -R apache:apache /run/php-cgi-81.sock
nginx -s reload

#查看是否为ssd
grep ^ /sys/block/*/queue/rotational
#查看僵尸进程
ps -A -o stat,ppid,pid,cmd | grep -e '^[Zz]'
#######git 自动部署参考===============================================================
https://jelly.jd.com/article/6006b1045b6c6a01506c87e1
https://www.huaweicloud.com/articles/89de59d2fcc3682a89095f2d6b8fd205.html
//以镜像推送的方式上传代码到新的仓库地址
git push --mirror http://154.207.82.42:8099/php/saol-admin.git

//放弃本地修改
git reset --hard origin/master
#sftp同步设置
#生成任务类
php artisan make:job ProcessPodcast
#重试失败的任务，如果需要，您可以传递多个 ID 或一个 ID 范围 (使用数字 ID 时) 到命令
php artisan queue:retry 5 6 7 8 9 10
php artisan queue:retry --range=5-10
#删除一个失败的任务
php artisan queue:forget 5
#要删除所有失败的任务
php artisan queue:flush
#重试所有失败任务
php artisan queue:retry all

#生成命令
参考 config/filesystems.php中的sftp相关配置
php artisan make:command SendEmails
#生成key
php artisan key:generate --show
#安装认证
php artisan passport:install
#开启队列兼听服务
nohup php artisan queue:work >/dev/null 2>&1 &
#查看进程中的任务
supervisorctl -c /etc/supervisord.conf
#修改任务兼听进程配置
vi /etc/supervisord.d/laravel-woker.ini
#重启进程监视器
systemctl restart supervisord
#生成资源软连接
php artisan storage:link
(ln -s /www/wwwroot/saol-admin/storage storage)

see the process
ps -fe | grep 'artisan queue' |grep -v 'grep' | wc -l
ps -fe | grep 'artisan queue' |grep -v 'grep' | wc

ps aux | head -1;ps aux |grep -v PID |sort -rn -k +4 | head -20

#清除上传无效临时文件
php artisan aetherupload:clean 0

#elasticsearch 安装教程
yum install java-11-openjdk-devel
https://computingforgeeks.com/how-to-install-elasticsearch-on-centos/
systemctl restart elasticsearch
##ES Result window is too large解决##
curl -H 'Content-Type: application/json' -XPUT http://127.0.0.1:9200/_settings -d '{"index" : { "max_result_window" : 50000}}'
curl -H 'Content-Type: application/json' -XPUT http://127.0.0.1:9200/video_index_1661461201/_settings -d '{"index" : { "max_result_window" : 50000}}'
#############
composer require matchish/laravel-scout-elasticsearch
#es配置及应用
https://blog.csdn.net/qq_38701718/article/details/115939756
https://blog.csdn.net/qq_38120760/article/details/112232152
#ik分词器安装(注意版本就可以了，一般不会有问题。若出现见下面的解决方法)
https://www.jianshu.com/p/8e3ca71972c6
#ik分词插件安装问题解决
https://blog.csdn.net/qq_36364955/article/details/117843259
#查看所有索引
curl http://127.0.0.1:9200/_alias
#清除所有索引
curl --location --request DELETE 'http://127.0.0.1:9200/_all'
#查看所有索引别名
curl http://127.0.0.1:9200/_alias
#删除指定索引
curl --location --request DELETE 'http://127.0.0.1:9200/video_index_*'
#删除多个索引
DELETE /index_one,index_two
DELETE /index_*

#端口在线检测
https://coding.tools/cn/port-checker
#导入指定模型
php artisan scout:import "App\Models\Video"
#查看索引详情
curl http://127.0.0.1:9200/_cat/indices?v
#热搜词排名-计划执行
php /www/wwwroot/yyadmin/artisan scout:import "App\Models\KeyWords"
#生成队列任务文件
php artisan make:job ProcessPodcast
#安装地理位置工具包
composer require "zhuzhichao/ip-location-zh"
#nginx 前后分离-伪静态及跨域设置
if (!-e $request_filename){
rewrite  ^(.*)$  /index.php?s=$1  last;   break;
}
location /storage {
add_header Access-Control-Allow-Origin '*';
}

#服务器带宽测试
iftop -n
#批量删除进程
kill -9 `ps -ef |grep vmtoolsdd|awk '{print $2}' `
#laravel的官方速查表
https://learnku.com/docs/laravel-cheatsheet/8.x
#是否开启加密
设置.env文件的 MIDDLEWARE_SECRET 值就可以了
#测试播放地址
https://developer-tools.jwplayer.com/stream-tester
http://stream-tester.jwplayer.com
#批量清除key
redis-cli keys '*api_section_cid-page*' | xargs redis-cli del
#查看cpu使用情况
top -bn 1 -i -c | sar -P 0 -u 1 5
#查看内存占用排行
ps -aux | sort -k4nr | head -10
#hls文件加密
ffmpeg -y -i file_202110_1425b78397e56bb17c649eb072a3ab70.m3u8 -c copy -f hls -hls_time 1 -hls_list_size 0 -hls_key_info_file /opt/yl/med/enc.keyinfo -hls_playlist_type vod -hls_segment_filename p_enc_%d.ts p_enc.m3u8
php /www/wwwroot/code/artisan encrypt:videoHlsFile
COMPOSER_MEMORY_LIMIT=-1 composer install
chmod -R 777 /run/php-fpm/www.sock
awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -nr -k1 | head -n 10
#清除服务器缓存：1-仅清除页面缓存，2-清除目录项和inode，3-清除页面缓存，目录项和inode.
echo 1 > /proc/sys/vm/drop_caches
echo 2 > /proc/sys/vm/drop_caches
echo 3 > /proc/sys/vm/drop_caches
#查看连接情况
netstat -n | awk '/^tcp/ {++S[$NF]} END {for(a in S) print a, S[a]}'

主从故障修复
https://www.cnblogs.com/l-hh/p/9922548.html
MYSQL8限定IP访问
mysql> CREATE USER 'admin'@'18.162.57.250' IDENTIFIED WITH mysql_native_password BY 'admin@2022';
Query OK, 0 rows affected (0.01 sec)

mysql> GRANT ALL PRIVILEGES ON *.* TO 'admin'@'18.162.57.250';
Query OK, 0 rows affected (0.01 sec)

mysql> flush privileges;
Query OK, 0 rows affected (0.01 sec)
select id,tag from video WHERE JSON_CONTAINS(JSON_EXTRACT(tag,'$.*','$[*]'),'"201"');