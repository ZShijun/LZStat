<?php

namespace TypechoPlugin\LZStat;

use Widget\ActionInterface;
use Widget\Base;

class Action extends Base implements ActionInterface
{
    public function stat(string $fieldName)
    {
        if (isset($this->request->cid)) {
            $cid = $this->request->filter('int')->get('cid');
            $total = Plugin::incStatField($cid, $fieldName);
        } else {
            $total = 0;
        }

        $this->response->throwJson(array('total' => $total));
    }

    public function action()
    {
        $this->on($this->request->is('do=views'))->stat('viewsNum');
        $this->on($this->request->is('do=likes'))->stat('likesNum');
    }
}
