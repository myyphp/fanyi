<?php

namespace Fanyi;
use \Exception;
use \SplDoublyLinkedList;

class Translate {
    private $curl;
    private $url = 'https://fanyi.baidu.com';
    private $host = 'fanyi.baidu.com';
    private $open_proxy = true;
    private $timeout_limit = 30;
    private $filePath = '';//资源文件所在位置
    private $cookie_file = '';
    private $proxy_host = '192.168.0.109';//代理服务器的ip
    private $proxy_port = '8888';//代理服务器的端口
    protected $phantomjs_bin_path = "D:\\app\\phantomjs\\bin";//phantomjs所在绝对路径，用来解析js
    protected $resource_path = './resource/';
    protected $output_path = './output/';
    protected $cookie_path = './cookie/';

    const PHP_LANG_FILE = 1;//tp的lang语言包类型，key 为 中文， val 为英文
    const JAVA_LANG_FILE = 2;//java的lang语言包类型

    protected $lang_file_type;
    private $resouce_data;

    public function __construct()
    {
        $this->curl = curl_init();
        $this->cookie_file = $this->cookie_path . 'cookie';
        $this->lang_file_type = self::PHP_LANG_FILE;
        $this->resouce_data = new ResourceData();
    }

    /**
     * 指定cookie文件名
     * @param string $cookieFile cookie文件名
     * @return resource|string
     */
    public function setCookieFile($cookieFile)
    {
        $this->cookie_file = $this->cookie_path . $cookieFile;
        return $this;
    }

    /**
     * @param string $resource_file 应该放在 ./resource 目录下
     * @return self
     * @throws Exception
     */
    public function setResourceFile($resource_file)
    {
        if (!file_exists($this->resource_path .$resource_file)) {
            throw new Exception('file ('.$resource_file.') not found');
        }

        $this->filePath = $this->resource_path . $resource_file;
        return $this;
    }

    /**
     * 设置资源类型
     *
     * @param $type
     */
    public function setResourceType($type)
    {

    }

    /**
     * 处理资源数据
     * @return array|mixed
     */
    public function handleResourceData()
    {
        $data = [];
        switch ($this->lang_file_type) {
            case self::PHP_LANG_FILE:
                $this->getDataFromTPLangFile();
                break;
            case self::JAVA_LANG_FILE:
                break;
        }

        return $data;
    }

    /**
     * 从Tp 的lang 包中提取中英文信息：为了保留注释内容而没有直接使用数组方式
     * @param bool $key_is_cn key内容为中文
     * @return mixed
     */
    public function getDataFromTPLangFile($key_is_cn = true)
    {
        //读取文件内容
        $fp = fopen($this->filePath, 'r');
        while (!feof($fp)) {
            $line = fgets($fp);
            $metaObj = new ResourceDataMeta();
            $metaObj->setOrign($line);

            if (strpos($line, '=>') !== false) {
                $content = array_map(function ($v) {
                    return trim($v);
                } ,explode('=>', $line));

                if ($key_is_cn) {
                    $cn = $content[0];
                    $en = $content[1];
                } else {
                    $cn = $content[1];
                    $en = $content[0];
                }

                $metaObj->setCn($cn);
                $metaObj->setEn($en);

                $waitReplace = str_replace($en, $metaObj->getEnPlaceholder(), $metaObj->getOrign());
                //$waitReplace = str_replace($cn, $waitReplace, $line);//暂时不处理 英 =》 汉
                $metaObj->setWaitReplaceStr($waitReplace);

                /*$this->_printMsg($metaObj->getCn());
                $this->_printMsg($metaObj->getEn());
                $this->_printMsg($metaObj->getOrign());
                $this->_printMsg($metaObj->getWaitReplaceStr());exit;*/
            }

            //放入队列结构等待处理
            $this->resouce_data->addData($metaObj);
        }
    }

    /**
     * 执行get请求
     *
     * @param $url
     * @param bool $need_follow
     * @param array $header_set
     * @param bool $need_header
     * @return string
     */
    protected function doGetRequest($url, $need_follow = true, $header_set = [], $need_header = false) {
        curl_reset($this->curl);
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, 0);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout_limit);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_COOKIEJAR,$this->cookie_file); //保存cookie到指定文件
        curl_setopt($this->curl, CURLOPT_COOKIEFILE,$this->cookie_file); //使用cookie文件

        //HTTPS
        $this->setHttpsOpt();

        if (!empty($header_set)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header_set);//设置请求头

            foreach ($header_set as $v) {
                if ($v == 'Accept-Encoding: gzip, deflate') {
                    curl_setopt($this->curl, CURLOPT_ENCODING,'gzip');
                }
            }
        }

        if ($need_follow) {
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); //支持重定向
            curl_setopt($this->curl, CURLOPT_MAXREDIRS, 20);//最大跳转数
        }

        if ($need_header) {
            curl_setopt($this->curl, CURLOPT_HEADER, true);//需要获取返回头信息
        }

        //代理服务器地址
        $this->setCurlProxy();

        $res_page = $this->execCurl();

        return $res_page;
    }

    /**
     * 设置代理
     */
    private function setCurlProxy() {
        if ($this->open_proxy) {
            curl_setopt($this->curl, CURLOPT_PROXY, $this->proxy_host);
            curl_setopt($this->curl, CURLOPT_PROXYPORT, $this->proxy_port);
        }
    }

    /**
     *  执行curl
     *
     * @return mixed
     * @throws Exception
     */
    private function execCurl()
    {
        $res = curl_exec($this->curl);
        if (curl_errno($this->curl)) {
            echo 'Curl error: ' . curl_errno($this->curl) . '----' . curl_error($this->curl) . PHP_EOL;
            return false;
        }

        return $res;
    }

    /**
     * 获取通用请求头
     *
     * @param $host
     * @param array $headerExt
     * @return array
     */
    private function getNormalHeader($host, array $headerExt = [])
    {
        $header = [
            'Host: ' . $host,
            'Pragma: no-cache',
            'Accept: */*',
            'Accept-Language: en-US,zh-CN;q=0.7,en-GB;q=0.3',
            'User-Agent: Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0)',
            'Connection: Keep-Alive',
        ];

        $header = array_merge($header, $headerExt);

        return $header;
    }


    /**
     * 执行post请求
     *
     * @param string $url 请求url
     * @param array $header 指定请求头
     * @param string $post_data (id=1&name=asf) post表单字段
     * @param boolean $need_header 是否需要响应头
     * @param boolean $use_cookie 是否需要使用cookie
     * @return string
     */
    protected function doPostRequest($url, $header = [], $post_data = '', $need_header = false, $use_cookie = true)
    {
        curl_reset($this->curl);
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, 1);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout_limit);

        if (!empty($header)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);//设置请求头
        }

        if (!empty($post_data)) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $post_data); //指定请求行请求参数
        }

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_COOKIEJAR,$this->cookie_file); //保存cookie到指定文件

        if ($use_cookie) {
            curl_setopt($this->curl, CURLOPT_COOKIEFILE,$this->cookie_file); //使用cookie文件
        }

        $this->setHttpsOpt();

        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, false); //取消重定向

        if ($need_header) {
            curl_setopt($this->curl, CURLOPT_HEADER, true);//需要获取返回头信息
        }

        $this->setCurlProxy();
        $res = $this->execCurl();
        return $res;
    }

    private function setHttpsOpt()
    {
        //HTTPS
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 0); // 跳过证书检查
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在
    }


    protected function encryptSign($query, $gtk)
    {

        if (empty($query) || empty($gtk)) {
            throw new Exception('Sorry, must need encrypt param!');
        }

        $js_file = $this->resource_path . 'fanyi_assign.js';
        $argv = '';//将url 和 post的其他参数传递给 phantomjs
        $argv .= $query . ' ' .$gtk;
        exec($this->phantomjs_bin_path . '\phantomjs --output-encoding=utf8 ' . $js_file . ' ' . $argv, $output_main);
        if (empty($output_main[0])) {
            echo "get encrypt string failed:{$query}" . PHP_EOL;
            return '';
        }

        return $output_main[0];
    }

    /**
     * 环境检查
     */
    private function checkEnv()
    {
        //TODO 检查 phantomjs 环境是否ok

    }

    //在cli下输入数据
    private function _printMsg($msg) {
        echo $msg.PHP_EOL;
    }

    public function run()
    {

        $this->checkEnv();

        //处理数据源
        $this->handleResourceData();
        $total = $this->resouce_data->count();

        $this->_printMsg(PHP_EOL . '-------------UT-------------');

        if ($total == 0) exit($this->_printMsg('have no data'));

        //第一次请求:/ 获取 token、gtk参数 以及生成相应的cookie
        $res1 = $this->doGetRequest($this->url, true, $this->getNormalHeader($this->host, [
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'SocketLog: SocketLog(tabid=1261&client_id=)',
        ]));

        //取出token
        preg_match('/token: \'([\da-z]{32})\',/', $res1, $match_res);
        $token = isset($match_res[1]) ? $match_res[1] : '';
        if (!$token) {
            $this->_printMsg('cannot catch token');
        }

        //取出gtk
        preg_match('/window.gtk = \'(\d+\.\d+)\';/', $res1, $match_gtk_res);
        $gtk = isset($match_gtk_res[1]) ? $match_gtk_res[1] : '';
        if (!$gtk) {
            $this->_printMsg('cannot catch gtk');
        }

        //模拟第二次请求：langdetect
        $url2 = $this->url . '/langdetect';
        $post_data = http_build_query(['query' => '你好']);
        $this->doPostRequest($url2, $this->getNormalHeader($this->host, [
            'Content-Length: ' . strlen($post_data),
            'Upgrade-Insecure-Requests: 1',
            'Origin: ' . $this->url,
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Referer: ' . $this->url,
            'SocketLog: SocketLog(tabid=1261&client_id=)',
        ]), $post_data);

        $fp = fopen($this->output_path . basename($this->filePath), 'a');

        //暂时设置为不删除的模式
        $this->resouce_data->setIteratorMode(SplDoublyLinkedList::IT_MODE_KEEP);
        foreach ($this->resouce_data as $data_meta) {
            if (!$data_meta->getWaitReplaceStr()) {
                //没有待替换的内容，则不做任何处理，原样输出即可
                fwrite($fp, $data_meta->getOrign());
            } else {
                $query_origin = $data_meta->getCn();
                $query = mb_convert_encoding($query_origin, 'GBK', 'UTF-8');
                //生成 sign 参数
                $sign = $this->encryptSign($query, $gtk);
                //无法获取到sign参数也先跳过
                if (!$sign) {
                    $str = str_replace($data_meta->getEnPlaceholder(), "''", $data_meta->getWaitReplaceStr());
                    fwrite($fp, $str);
                    continue;
                }

                //模拟第三次请求：v2transapi
                $url3 = $this->url . '/v2transapi';
                $post_data = http_build_query([
                    'from' => 'zh',
                    'to' => 'en',
                    'query' => $query_origin,
                    'transtype' => 'translang',
                    'simple_means_flag' => '3',
                    'sign' => $sign,
                    'token' => $token,
                ]);

                $res3 = $this->doPostRequest($url3, $this->getNormalHeader($this->host, [
                    'Content-Length: ' . strlen($post_data),
                    'Origin: ' . $this->url,
                    'X-Requested-With: XMLHttpRequest',
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'Referer: ' . $this->url,
                    'SocketLog: SocketLog(tabid=1261&client_id=)',
                ]), $post_data);

                //取出翻译结果
                $translate_res = json_decode($res3, true);
                $translate_res = isset($translate_res['trans_result']['data'][0]['dst']) ? $translate_res['trans_result']['data'][0]['dst'] : '';

                $str = str_replace($data_meta->getEnPlaceholder(), $translate_res, $data_meta->getWaitReplaceStr());
                fwrite($fp, $str);
            }
        }
        fclose($fp);
    }
}


