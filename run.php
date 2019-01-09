<?php

include './vendor/autoload.php';

$translateObj = new \Fanyi\Translate();

//环境检查
$translateObj->checkEnv();

//第二个参数为：文件类型：参数：tp  、java
if ($argv[1]) {
    if (!in_array(strtolower($argv[1]), ['tp', 'java'])) {
        $translateObj->_printMsg('lang file type argv is wrong: tp | java');
        exit();
    }
    switch ($argv[1]) {
        case 'tp':
            $translateObj->setResourceType(\Fanyi\Translate::PHP_LANG_FILE);
            break;
        case 'java':
            $translateObj->setResourceType(\Fanyi\Translate::JAVA_LANG_FILE);
            break;
    }
} else {
    $translateObj->_printMsg('please set lang file type: tp | java on argv 1');
    exit();
}


//第三参数为：文件位置，会去 resource目录下查找
if (!$argv[2]) {
    $translateObj->_printMsg('please set lang file name, and the file must in ./resource/');
    exit();
}

//第四个参数控制是否转码，有时候转码会导致生成 签名参数异常，有时候不会，可以根据最终生成的结果再尝试切换
if ($argv[3]) {
    $translateObj->setOpenIconv(boolval($argv[3]));
}

$translateObj->setResourceFile($argv[2])->run();