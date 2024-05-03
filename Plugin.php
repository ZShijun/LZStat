<?php

namespace TypechoPlugin\LZStat;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Date;
use Typecho\Db;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Utils\Helper;
use Widget\Archive;
use Widget\Options;
use Widget\User;

/**
 * 对浏览量和点赞量进行统计，并且实现加权排序
 * 权重：点赞量*100 + 浏览量
 * 
 * @package LZStat 
 * @author laozhu
 * @version 1.0.0
 * @link https://ilaozhu.com
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho\Plugin\Exception
     */
    public static function activate()
    {
        // viewsNum: 浏览量 likesNum: 点赞量
        self::ensureStatFields(['viewsNum' => '浏览次数', 'likesNum' => '点赞次数']);
        $archive = \Typecho\Plugin::factory(Archive::class);

        $archive->beforeRender = __CLASS__ . '::addViews';
        $archive->select = __CLASS__ . '::selectHandler';
        $archive->footer = __CLASS__ . '::footer';
        Helper::addAction('stat', Action::class);
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho\Plugin\Exception
     */
    public static function deactivate()
    {
        Helper::removeAction('stat');
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        $requireAxios = new Radio(
            'requireAxios',
            [
                0    => _t('否'),
                1    => _t('是')
            ],
            1,
            _t('引入Axios'),
            _t('如果项目中已存在Axios，请选择否，如果不存在或不确定，请选择是')
        );
        $form->addInput($requireAxios);

        /** 排序 */
        $orderBy = new Radio(
            'orderBy',
            [
                'created'    => _t('创建时间'),
                'viewsNum'    => _t('浏览量'),
                'likesNum'    => _t('点赞量'),
                'weight'     => _t('加权排序')
            ],
            'created',
            _t('排序方式'),
            _t('文章列表会根据选中的方式降序排序，其中，权重计算规则是：点赞量*100 + 浏览量')
        );
        $form->addInput($orderBy);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    public static function addViews($archive)
    {
        if ($archive->is('post') || $archive->is('page')) {
            self::incStatField($archive->cid, 'viewsNum');
        }
    }

    /**
     * 对统计字段进行自增
     * 
     * @param int $cid 文档ID
     * @param string $fieldName 字段名
     * @return int 自增后的值
     */
    public static function incStatField(string $cid, string $fieldName)
    {
        if (!in_array($fieldName, ['viewsNum', 'likesNum'])) {
            return 0;
        }

        $key = '__stat';
        $stat = json_decode(Cookie::get($key, '[]'), true);
        if (isset($stat[$fieldName]) && array_key_exists("{$cid}", $stat[$fieldName])) {
            return $stat[$fieldName]["{$cid}"];
        }

        $db = Db::get();
        $tableName = $db->getPrefix() . 'contents';
        $sql = "UPDATE $tableName SET $fieldName = $fieldName + 1 WHERE cid = $cid";
        $db->query($sql);

        $result = $db->fetchRow($db->select($fieldName)->from($tableName)->where('cid = ?', $cid));
        $stat[$fieldName]["{$cid}"] = $result[$fieldName];
        Cookie::set($key, json_encode($stat), strtotime('tomorrow'));
        return $result[$fieldName];
    }

    public static function selectHandler(Archive $archive)
    {
        $user = Widget::widget(User::class);
        $select = $archive
            ->select('table.contents.*', '(likesNum * 100 + viewsNum) AS weight')
            ->from('table.contents');
        if ('post' == $archive->parameter->type || 'page' == $archive->parameter->type) {
            if ($user->hasLogin()) {
                $select->where(
                    'table.contents.status = ? OR table.contents.status = ? 
                        OR (table.contents.status = ? AND table.contents.authorId = ?)',
                    'publish',
                    'hidden',
                    'private',
                    $user->uid
                );
            } else {
                $select->where(
                    'table.contents.status = ? OR table.contents.status = ?',
                    'publish',
                    'hidden'
                );
            }
        } else {
            if ($user->hasLogin()) {
                $select->where(
                    'table.contents.status = ? OR (table.contents.status = ? AND table.contents.authorId = ?)',
                    'publish',
                    'private',
                    $user->uid
                );
            } else {
                $select->where('table.contents.status = ?', 'publish');
            }
        }

        $orderBy = Widget::widget(Options::class)->plugin('LZStat')->orderBy;
        $select->where('table.contents.created < ?', Date::time())
            ->order($orderBy, Db::SORT_DESC);
        return $select;
    }

    public static function footer()
    {
        if (Widget::widget(Options::class)->plugin('LZStat')->requireAxios) {
            $axiosUrl = Common::url('LZStat/axios.min.js', Helper::options()->pluginUrl);
            echo '<script src="' . $axiosUrl . '"></script>';
        }
        echo <<<EOF
        <script>
            function stat(type){
                const sets = document.querySelectorAll('.set-' + type);
                if (sets.length > 0) {                
                    sets.forEach(function (item) {
                        item.addEventListener('click', function (e) {
                            e.stopPropagation();
                            const cid = item.dataset.cid;
                            axios.get('/action/stat?do=' + type + '&cid='+cid)
                            .then(function (response) {
                                const gets = document.querySelector('.get-' + type + '[data-cid="'+cid+'"]');
                                if (gets) {
                                    gets.textContent = response.data.total;
                                }
                            })
                            .catch(function (error) {
                                console.log(error);
                            });
                        });
                    });
                }
            }

            stat('views');
            stat('likes');
        </script>
        EOF;
    }
    /**
     * 确保统计字段在数据表中
     * 
     * @param array $fields 字段列表，格式：'字段名' => '备注'
     */
    private static function ensureStatFields(array $fields)
    {
        if (empty($fields)) {
            return;
        }

        $db = Db::get();
        $tableName = $db->getPrefix() . 'contents';
        foreach ($fields as $key => $value) {
            $sql = "SHOW COLUMNS FROM $tableName WHERE Field = '$key'";
            $result = $db->query($sql);
            if ($result->rowCount() == 0) {
                $db->query("ALTER TABLE $tableName ADD $key INT UNSIGNED NOT NULL COMMENT '$value' DEFAULT '0'");
            }
        }
    }
}
