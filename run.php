<?php

include './vendor/autoload.php';

$translateObj = new \Fanyi\Translate();
$translateObj->setResourceFile('en-us.php')->run();