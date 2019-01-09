<?php

namespace Fanyi;
use \Exception;
use \SplDoublyLinkedList;
use Fanyi\Crawler;

class Translate {
    private $crawler;
    private $url = 'https://fanyi.baidu.com';
    private $host = 'fanyi.baidu.com';

    private $filePath = '';//资源文件所在位置

    protected $phantomjs_bin_path = "D:\\app\\phantomjs\\bin";//phantomjs所在绝对路径，用来解析js
    protected $resource_path = './resource/';
    protected $output_path = './output/';

    const PHP_LANG_FILE = 1;//tp的lang语言包类型，key 为 中文， val 为英文
    const JAVA_LANG_FILE = 2;//java的lang语言包类型

    private $openIconv = true;

    /**
     * @param boolean $openIconv
     */
    public function setOpenIconv($openIconv)
    {
        $this->openIconv = $openIconv;
    }

    protected $lang_file_type;
    private $resouce_data;

    public function __construct()
    {
        $this->lang_file_type = self::PHP_LANG_FILE;
        $this->resouce_data = new ResourceData();
        $this->crawler = new Crawler();
        $this->crawler->setOpenProxy(true);
        $this->crawler->setProxyHost('192.168.0.109');
        $this->crawler->setProxyPort(8888);
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
     * @return mixed
     */
    public function setResourceType($type)
    {
        if (!in_array($type, [self::PHP_LANG_FILE, self::JAVA_LANG_FILE])) {
            throw new \InvalidArgumentException('resource type is error');
        }
        $this->lang_file_type = $type;
        return $this;
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
                $this->getDataFromJavaLangFile();
                break;
        }

        return $data;
    }

    /**
     * 从java的lang文件处理数据
     */
    public function getDataFromJavaLangFile()
    {
        //读取文件内容
        $fp = fopen($this->filePath, 'r');
        while (!feof($fp)) {
            $line = fgets($fp);
            $metaObj = new ResourceDataMeta();
            $metaObj->setOrign($line);

            if (preg_match('/<string name=".+">(.+)<\/string>/i', $line, $match_res)) {
                $cn = $match_res[1];
                $metaObj->setCn($cn);
                $metaObj->setEn('');

                $waitReplace = str_replace($cn, $metaObj->getEnPlaceholder(), $metaObj->getOrign());
                $metaObj->setWaitReplaceStr($waitReplace);
            }

            //放入队列结构等待处理
            $this->resouce_data->addData($metaObj);
        }
        fclose($fp);
    }

    /**
     * 从Tp 的lang 包中提取中英文信息：为了保留注释内容而没有直接使用数组方式
     * @param bool $key_is_cn key内容为中文
     * @return void
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
                    $v = trim($v);
                    $v = trim($v, ",");
                    return $v;
                } ,explode('=>', $line));

                if ($key_is_cn) {
                    $cn = $content[0];
                    $en = $content[1];
                } else {
                    $cn = $content[1];
                    $en = $content[0];
                }

                if ($en == "''" || $en == '""' || !$en) {
                    $metaObj->setCn($cn);
                    $metaObj->setEn($en);

                    $waitReplace = str_replace($en, '"'.$metaObj->getEnPlaceholder().'"', $metaObj->getOrign());

                    //$waitReplace = str_replace($cn, $waitReplace, $line);//暂时不处理 英 =》 汉
                    $metaObj->setWaitReplaceStr($waitReplace);
                }
            }
            //放入队列结构等待处理
            $this->resouce_data->addData($metaObj);
        }
        fclose($fp);
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
    public function checkEnv()
    {
        //cli环境检查
        if (!preg_match("/cli/i", php_sapi_name())) {
            exit($this->_printMsg('please run in cli env...'));
        }
        //TODO 检查 phantomjs 环境是否ok

        //检查OpenSSL扩展
        if (!extension_loaded('openssl')) {
            exit($this->_printMsg('openssl extension missing...'));
        }
    }

    public function _printMsg($msg) {
        echo $msg.PHP_EOL;
    }

    public function run()
    {

        //$this->checkEnv();

        //处理数据源
        $this->handleResourceData();
        $total = $this->resouce_data->count();

        $this->_printMsg(PHP_EOL . '-------------UT-------------');

        if ($total == 0) exit($this->_printMsg('have no data'));

        //第一次请求:/ 获取 token、gtk参数 以及生成相应的cookie
        $res1 = $this->crawler->doGetRequest($this->url, true, $this->crawler->getNormalHeader($this->host, [
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
        /*$url2 = $this->url . '/langdetect';
        $post_data = http_build_query(['query' => '你好']);
        $this->crawler->doPostRequest($url2, $this->crawler->getNormalHeader($this->host, [
            'Content-Length: ' . strlen($post_data),
            'Upgrade-Insecure-Requests: 1',
            'Origin: ' . $this->url,
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Referer: ' . $this->url,
            'SocketLog: SocketLog(tabid=1261&client_id=)',
        ]), $post_data);*/

        $fp = fopen($this->output_path . basename($this->filePath), 'wa');

        //暂时设置为不删除的模式
        $this->resouce_data->setIteratorMode(SplDoublyLinkedList::IT_MODE_KEEP);
        $c = 0;
        foreach ($this->resouce_data as $data_meta) {
            $c++;
            printf("progress: [%-50s] %d%% ($c/$total) Done\r", str_repeat('=',$c/$total*50), $c/$total*100);

            if (!$data_meta->getWaitReplaceStr()) {
                //没有待替换的内容，则不做任何处理，原样输出即可
                fwrite($fp, $data_meta->getOrign());
            } else {
                //如果en已经有值了，也不做任何处理(由于已经在生产资源集时已经处理了，这里就不再处理了)
                $query_origin = $this->filterQueryCn($data_meta->getCn());

                $query = $this->openIconv ? mb_convert_encoding($query_origin, 'GBK', 'UTF-8') : $query_origin;
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

                $res3 = $this->crawler->doPostRequest($url3, $this->crawler->getNormalHeader($this->host, [
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
                $translate_res = $this->filterRes($translate_res);
                //放回对应的位置
                $str = str_replace($data_meta->getEnPlaceholder(), $translate_res, $data_meta->getWaitReplaceStr());
                fwrite($fp, $str);
            }
        }
        fclose($fp);
    }

    private function filterRes($res)
    {
        $return = trim($res);
        $return = trim($return, ".'");
        $return = str_replace('uuuuuuuuuuuu', '',  $return);
        $return = str_replace('uuuuuuuuuuu', '',  $return);
        return $return;
    }

    private function filterQueryCn($cn)
    {
        //取出中文字符串中间的空格，防止生成sign参数出现问题
        $return = preg_replace('/\s/', '', $cn);
        $return = str_replace('"', '', $return);
        $return = str_replace("'", '', $return);
        return $return;
    }
}


