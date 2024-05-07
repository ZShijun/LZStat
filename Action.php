<?php

namespace TypechoPlugin\LZStat;

use Typecho\Cookie;
use Typecho\Db;
use Widget\ActionInterface;
use Widget\Base;

class Action extends Base implements ActionInterface
{
    public function stat($fieldName)
    {
        if (isset($this->request->cid)) {
            $cid = $this->request->filter('int')->get('cid');
            if ($fieldName == 'viewsNum') {
                $total = self::updateViews($cid);
            } else {
                $total = self::updateLikes($cid);
            }
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

    /**
     * 根据文章ID更新浏览量
     * 
     * @param string $cid 文章ID
     * @return int 总浏览数
     */
    public static function updateViews(string $cid)
    {
        $key = '__views';
        $fieldName = 'viewsNum';
        $views = json_decode(Cookie::get($key, '[]'), true);
        if (array_key_exists($cid, $views)) {
            return $views[$cid];
        } else {
            $total = self::updateStatField($cid, $fieldName);
            $views[$cid] = $total;
            Cookie::set($key, json_encode($views), strtotime('tomorrow'));
            return $total;
        }
    }

    /**
     * 根据文章ID更新点赞量
     * 
     * @param string $cid 文章ID
     * @return int 总点赞数
     */
    public static function updateLikes(string $cid)
    {
        $key = '__likes';
        $fieldName = 'likesNum';
        $likes = json_decode(Cookie::get($key, '[]'), true);
        $total = 0;
        if (array_key_exists($cid, $likes)) {
            $total = self::updateStatField($cid, $fieldName, false);
            unset($likes[$cid]);
        } else {
            $total = self::updateStatField($cid, $fieldName);
            $likes[$cid] = $total;
        }

        Cookie::set($key, json_encode($likes), strtotime('tomorrow'));
        return $total;
    }

    /**
     * 更新统计字段（浏览量，点赞量）
     * 
     * @param string $cid 文章ID
     * @param string $fieldName 统计字段
     * @param bool $isInc true为自增，false为自减
     * @return int
     */
    private static function updateStatField(string $cid, string $fieldName, bool $isInc = true)
    {
        $db = Db::get();
        $tableName = $db->getPrefix() . 'contents';
        $sql = "UPDATE $tableName SET $fieldName = $fieldName";
        if ($isInc) {
            $sql .= ' + 1';
        } else {
            $sql .= ' - 1';
        }
        $sql .= " WHERE cid = $cid";
        $db->query($sql);

        $result = $db->fetchRow($db->select($fieldName)->from($tableName)->where('cid = ?', $cid));
        return $result[$fieldName];
    }
}
