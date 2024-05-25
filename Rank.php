<?php

namespace TypechoPlugin\LZStat;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 榜单
 */
class Rank extends Contents
{
    /**
     * 执行函数
     *
     * @throws Db\Exception
     */
    public function execute()
    {
        $this->parameter->setDefault(['pageSize' => $this->options->postsListSize, 'orderBy' => 'created']);

        $prefix = $this->db->getPrefix();
        $this->db->fetchAll($this->db->select(
            'cid',
            'title',
            'slug',
            'created',
            'type',
            'status',
            'commentsNum',
            'allowComment',
            'viewsNum',
            'likesNum',
            '(likesNum * 100 + viewsNum) AS weight'
        )
            ->from($prefix . 'contents')
            ->where('status = ?', 'publish')
            ->where('created < ?', $this->options->time)
            ->where('type = ?', 'post')
            ->order($this->parameter->orderBy, Db::SORT_DESC)
            ->limit($this->parameter->pageSize), [$this, 'push']);
    }
}
