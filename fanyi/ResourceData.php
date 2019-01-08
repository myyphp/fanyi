<?php

namespace Fanyi;
use Fanyi\ResourceDataMeta;
use \SplQueue;

class ResourceData extends SplQueue
{
    /**
     * @param \Fanyi\ResourceDataMeta $data
     */
    public function addData(ResourceDataMeta $data) {
        $this->enqueue($data);
    }
}