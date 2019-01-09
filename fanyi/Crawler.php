<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/8
 * Time: 18:51
 */

namespace Fanyi;


class Crawler
{
    private $curl;
    private $proxyHost = '127.0.0.1';//代理服务器的ip
    private $proxyPort = 8888;//代理服务器的端口
    private $timeoutLimit = 30;
    protected $cookiePath = './cookie/';
    private $cookieFile = '';

    private $open_proxy = true;

    public function __construct()
    {
        $this->curl = curl_init();
        $this->cookieFile = $this->cookieFile . 'cookie.log';
    }

    /**
     * @param string $proxyHost
     */
    public function setProxyHost($proxyHost)
    {
        $this->proxyHost = $proxyHost;
    }

    /**
     * @param int $proxyPort
     */
    public function setProxyPort($proxyPort)
    {
        $this->proxyPort = $proxyPort;
    }

    /**
     * @param int $timeoutLimit
     */
    public function setTimeoutLimit($timeoutLimit)
    {
        $this->timeoutLimit = $timeoutLimit;
    }

    /**
     * @param string $cookiePath
     */
    public function setCookiePath($cookiePath)
    {
        $this->cookiePath = $cookiePath;
    }

    /**
     * @param boolean $open_proxy
     */
    public function setOpenProxy($open_proxy)
    {
        $this->open_proxy = $open_proxy;
    }

    /**
     * 指定cookie文件名
     * @param string $cookieFile cookie文件名
     * @return void
     */
    public function setCookieFile($cookieFile)
    {
        $this->cookieFile = $this->cookieath . $cookieFile;
    }

    /**
     * @return int
     */
    public function getTimeoutLimit()
    {
        return $this->timeoutLimit;
    }

    /**
     * @return string
     */
    public function getCookiePath()
    {
        return $this->cookiePath;
    }

    /**
     * @return boolean
     */
    public function isOpenProxy()
    {
        return $this->openProxy;
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
    public function doGetRequest($url, $need_follow = true, $header_set = [], $need_header = false) {
        curl_reset($this->curl);
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, 0);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeoutLimit);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_COOKIEJAR,$this->cookieFile); //保存cookie到指定文件
        curl_setopt($this->curl, CURLOPT_COOKIEFILE,$this->cookieFile); //使用cookie文件

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
            curl_setopt($this->curl, CURLOPT_PROXY, $this->proxyHost);
            curl_setopt($this->curl, CURLOPT_PROXYPORT, $this->proxyPort);
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
    public function getNormalHeader($host, array $headerExt = [])
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
    public function doPostRequest($url, $header = [], $post_data = '', $need_header = false, $use_cookie = true)
    {
        curl_reset($this->curl);
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, 1);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeoutLimit);

        if (!empty($header)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);//设置请求头
        }

        if (!empty($post_data)) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $post_data); //指定请求行请求参数
        }

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_COOKIEJAR,$this->cookieFile); //保存cookie到指定文件

        if ($use_cookie) {
            curl_setopt($this->curl, CURLOPT_COOKIEFILE,$this->cookieFile); //使用cookie文件
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
}