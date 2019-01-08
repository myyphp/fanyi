<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/8
 * Time: 15:57
 */

namespace Fanyi;

class ResourceDataMeta
{
    /**
     * @var string $orign 当前行的原内容
     */
    private $orign;

    /**
     * @var string $enPlaceholder 英文内容的占位符
     */
    private $enPlaceholder = "'---EN---'";

    /**
     * @var string $cnPlaceholder 中文内容的占位符
     */
    private $cnPlaceholder = "'---CN---'";

    /**
     * @var string $orign 待替换的内容,其中有2个占位符， 示例："---CN---" => "---EN---"
     */
    private $waitReplaceStr;

    /**
     * @return string
     */
    public function getEnPlaceholder()
    {
        return $this->enPlaceholder;
    }

    /**
     * @param string $enPlaceholder
     */
    public function setEnPlaceholder($enPlaceholder)
    {
        $this->enPlaceholder = $enPlaceholder;
    }

    /**
     * @return string
     */
    public function getCnPlaceholder()
    {
        return $this->cnPlaceholder;
    }

    /**
     * @param string $cnPlaceholder
     */
    public function setCnPlaceholder($cnPlaceholder)
    {
        $this->cnPlaceholder = $cnPlaceholder;
    }

    /**
     * @return string
     */
    public function getWaitReplaceStr()
    {
        return $this->waitReplaceStr;
    }

    /**
     * @param string $waitReplaceStr
     */
    public function setWaitReplaceStr($waitReplaceStr)
    {
        $this->waitReplaceStr = $waitReplaceStr;
    }

    /**
     * @return string
     */
    public function getOrign()
    {
        return $this->orign;
    }

    /**
     * @param string $orign
     */
    public function setOrign($orign)
    {
        $this->orign = $orign;
    }

    /**
     * @var string $cn 中文内容
     */
    private $cn;
    /**
     * @var string $en 英文内容
     */
    private $en;

    /**
     * @return string
     */
    public function getCn()
    {
        return $this->cn;
    }

    /**
     * @param string $cn
     */
    public function setCn($cn)
    {
        $this->cn = $cn;
    }

    /**
     * @return string
     */
    public function getEn()
    {
        return $this->en;
    }

    /**
     * @param string $en
     */
    public function setEn($en)
    {
        $this->en = $en;
    }

    /**
     * 检查是否是注释内容
     * @return bool
     */
    public function isAnnotation()
    {
        return (bool)preg_match('/(\/\*.*\*\/)|(#.*?\n)|(\/\/.*?\n)|(<!--.\n)/s', $this->orign);
    }
}