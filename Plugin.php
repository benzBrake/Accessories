<?php

/**
 * 附件下载插件 感谢 <a href="http://www.imhan.com">Hanny</a> <a href="https://dt27.org">dt27</a>
 *
 * @package Accessories
 * @author Ryan
 * @version 1.0.8
 * @dependence 9.9.2-*
 * @link https://doufu.ru
 *
 * 历史版本
 * version 1.0.8 at 2023-05-01
 * 支持多种编辑器
 * 支持图片短代码，换域名以后再也不怕图片链接不对啦
 * version 1.0.7 at 2021-05-08
 * version 1.0.6 at 2021-03-03
 * version 1.0.3 at 2020-04-14
 * 支持 EditorMD 编辑器
 * 使用单独的按钮来添加附件短码
 *
 * version 1.0.2 at 2020-04-12
 * 更换附件图片
 * 修改附件短码[attach]id[/attach]
 * 从 Attachment 改名
 * 点击附件链接自动填写[attach]id[/attach]
 * 增加原文件名下载
 * 增加内置统计功能
 *
 */
class Accessories_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('Accessories_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('Accessories_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Abstract_Comments')->contentEx = array('Accessories_Plugin', 'parse');
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('Accessories_Plugin', 'bottomJS');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('Accessories_Plugin', 'bottomJS');
        Helper::addRoute('accessories', '/accessories/[cid:digital]', 'Accessories_Action', 'action');
        Typecho_Plugin::factory('Widget_Archive')->header = array('Accessories_Plugin', 'header');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeRoute('accessories');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('useBuildInStat', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('使用内置统计'), _t('如果不想使用内置统计请关闭此选项'));
        $form->addInput($radio);

        $radio = new Typecho_Widget_Helper_Form_Element_Radio('hideRealPath', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('隐藏真实路径'), _t('如果使用云存储请关闭此项'));
        $form->addInput($radio);

        $edit = new Typecho_Widget_Helper_Form_Element_Text('cdnDomain', null, null, _t('CDN域名'), _t('兼容云插件，把本地地址替换为云存储地址'));
        $form->addInput($edit);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 插入 CSS
     * @return void
     */
    public static function header()
    {
        echo '<link rel="stylesheet" href="' . Helper::options()->pluginUrl . '/Accessories/style.css" />';
    }

    /**
     * 解析附件短代码
     *
     * @access public
     * @param array $matches 解析值
     * @return string
     */
    public static function attachCallback(array $matches): string
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $offset = $options->timezone - $options->serverTimezone;
        $attach_img = Typecho_Common::url('Accessories/attach.png', $options->pluginUrl);
        $db = Typecho_Db::get();
        $cid = $matches[1];
        $attach = $db->fetchRow($db->select()->from('table.contents')->where('type = \'attachment\' AND cid = ?', $cid));
        if (empty($attach)) {
            return "<div><img align='absmiddle' title='Accessories' src='" . $attach_img . "' /> 附件ID错误</div>";
        }
        $attach_date = "最后修改: " . date('Y-m-d H:i', $attach['modified'] + $offset);
        $attach_text = unserialize($attach['text']);
        $attach_size = round($attach_text['size'] / 1024, 1) . " KB";
        $attach_url = Typecho_Common::url('accessories/' . $cid, $options->index);
        if ($options->plugin('Accessories')->useBuildInStat) {
            $attach_views = "下载次数: " . self::getViews($cid);
        } else if (isset($options->plugins['activated']['Stat'])) {
            $attach_views = "下载次数: " . $attach['views'];
        } else {
            $attach_views = '';
        }
        return "<div class='accessories-block'><div class='accessories-notice attachment' title='AccessoriesPro'>附件</div><div class='accessories-promo'></div><div class='accessories-content'><div class='accessories-filename'><div class='img' title='AccessoriesPro'></div>附件名称：" . $attach['title'] . "</div><div class='accessories-filesize'><div class='img'></div>文件大小：" . $attach_size . "</div><div class='accessories-count'><div class='img'></div>" . $attach_views . "</div><div class='accessories-filemodified'><div class='img'></div>" . $attach_date . "</div><div class='accessories-button-group'><a class='accessories-button' href='" . $attach_url . "' target='_blank'>点击下载</a></div></div></div>";
    }

    /**
     * 解析图片短代码
     *
     * @param array $matches
     * @return string
     */
    public static function imageCallback(array $matches): string
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $image = Helper::widgetById('contents', $matches[1]);
        if (is_object($image)) {
            $basicUrl = $options->siteUrl;
            if ($options->plugin('Accessories')->cdnDomain) {
                $basicUrl = $options->plugin('Accessories')->cdnDomain;
            }
            $url = Typecho_Common::url($image->attachment->path, $basicUrl);
            $file = $image->attachment->name;
            $filename = pathinfo($file, PATHINFO_FILENAME);
            return sprintf('<img src="%s" title="%s" />', $url, $filename);
        } else {
            return "";
        }
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function parse($text, $widget, $lastResult)
    {
        $text = empty($lastResult) ? $text : $lastResult;

        if ($widget instanceof Widget_Archive || $widget instanceof Widget_Abstract_Comments) {
            $text = preg_replace_callback("/\[attach\](\d+)\[\/attach\]/is", array('Accessories_Plugin', 'attachCallback'), $text);
            $text = preg_replace_callback("/\[image\](\d+)\[\/image\]/is", array('Accessories_Plugin', 'imageCallback'), $text);
            return $text;
        } else {
            return $text;
        }
    }

    /**
     * 获取浏览次数
     */
    public static function getViews($cid)
    {
        $fields = self::getFields($cid);
        if (isset($fields->views)) {
            return $fields->views;
        }
        return 0;
    }

    /**
     * 增加浏览次数
     */
    public static function viewStat($cid)
    {
        $vieweds = Typecho_Cookie::get('__contents_viewed');
        if (empty($vieweds)) {
            $vieweds = array();
        } else {
            $vieweds = explode(',', $vieweds);
        }
        if (!in_array($cid, $vieweds)) {
            $views = intval(self::getViews($cid)) + 1;
            self::setField('views', 'int', $views, $cid);
            $vieweds[] = $cid;
            $vieweds = implode(',', $vieweds);
            Typecho_Cookie::set("__contents_viewed", $vieweds);
        }
    }

    /**
     * 获取所有字段
     *
     * @access public
     * @param int $cid
     * @return Typecho_Config
     */
    public static function getFields($cid)
    {
        $db = Typecho_Db::get();
        $fields = array();
        $rows = $db->fetchAll($db->select()->from('table.fields')
            ->where('cid = ?', $cid));

        foreach ($rows as $row) {
            $fields[$row['name']] = $row[$row['type'] . '_value'];
        }
        return new Typecho_Config($fields);
    }

    /**
     * 设置单个字段
     *
     * @param string $name
     * @param string $type
     * @param string $value
     * @param integer $cid
     * @access public
     * @return integer
     */
    public function setField($name, $type, $value, $cid)
    {
        $db = Typecho_Db::get();
        if (empty($name) || !in_array($type, array('str', 'int', 'float'))) {
            return false;
        }

        $exist = $db->fetchRow($db->select('cid')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));

        if (empty($exist)) {
            return $db->query($db->insert('table.fields')
                ->rows(array(
                    'cid' => $cid,
                    'name' => $name,
                    'type' => $type,
                    'str_value' => 'str' == $type ? $value : null,
                    'int_value' => 'int' == $type ? intval($value) : 0,
                    'float_value' => 'float' == $type ? floatval($value) : 0,
                )));
        } else {
            return $db->query($db->update('table.fields')
                ->rows(array(
                    'type' => $type,
                    'str_value' => 'str' == $type ? $value : null,
                    'int_value' => 'int' == $type ? intval($value) : 0,
                    'float_value' => 'float' == $type ? floatval($value) : 0,
                ))
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * 插件是否启用
     * @param string $pluginName
     * @return bool
     */
    public static function isPluginEnabled(string $pluginName): bool
    {
        return array_key_exists($pluginName, Typecho_Plugin::export()['activated']);
    }

    /**
     * 添加功能JS
     * 点击附件链接自动填写[attach]id[/attach]
     * insertTextAtCursor 来自插件 TePass
     *
     * @return void
     * @date 2020-04-12
     */
    public static function bottomJS()
    {
        $requestUri = Typecho_Request::getInstance()->getRequestUri();
        $options = Typecho_Widget::widget('Widget_Options');
        if (strpos($requestUri, 'write-post.php') !== false || strpos($requestUri, 'write-page.php') !== false) {
            $htmlSource = ob_get_contents();
            if (self::isPluginEnabled("Handsome")) {
                $config = Typecho_Widget::widget('Widget_Options')->plugin('Handsome');
                $editorChoice = $config->editorChoice;
                if ($editorChoice === "vditor") {
                    $insertAll = 'false';
                    $htmlSource = str_replace('document.getElementById("btn-save").onclick', 'window.vditor = vditor; document.getElementById("btn-save").onclick', $htmlSource);

                }
            }
            if (self::isPluginEnabled("UEditor")) {
                $htmlSource = str_replace("var ue1 = new baidu.editor.ui.Editor();", 'window.ue1 = new baidu.editor.ui.Editor();', $htmlSource);
            }
            ob_clean();
            print $htmlSource;
            ob_end_flush();
        }
        ?>
        <style>
            #file-list .info {
                position: relative;
            }

            #file-list li .accessories {
                width: 32px;
                height: 32px;
                background-size: cover;
                position: absolute;
                cursor: pointer;
                right: 0;
                top: -16px;
            }
            #file-list li[data-image="0"] .accessories {
                background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0Ij48cGF0aCBkPSJNMTQgMTMuNVY4QzE0IDUuNzkwODYgMTIuMjA5MSA0IDEwIDRDNy43OTA4NiA0IDYgNS43OTA4NiA2IDhWMTMuNUM2IDE3LjA4OTkgOC45MTAxNSAyMCAxMi41IDIwQzE2LjA4OTkgMjAgMTkgMTcuMDg5OSAxOSAxMy41VjRIMjFWMTMuNUMyMSAxOC4xOTQ0IDE3LjE5NDQgMjIgMTIuNSAyMkM3LjgwNTU4IDIyIDQgMTguMTk0NCA0IDEzLjVWOEM0IDQuNjg2MjkgNi42ODYyOSAyIDEwIDJDMTMuMzEzNyAyIDE2IDQuNjg2MjkgMTYgOFYxMy41QzE2IDE1LjQzMyAxNC40MzMgMTcgMTIuNSAxN0MxMC41NjcgMTcgOSAxNS40MzMgOSAxMy41VjhIMTFWMTMuNUMxMSAxNC4zMjg0IDExLjY3MTYgMTUgMTIuNSAxNUMxMy4zMjg0IDE1IDE0IDE0LjMyODQgMTQgMTMuNVoiPjwvcGF0aD48L3N2Zz4=");
            }
            #file-list li[data-image="1"] .accessories {
                background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjI0IiBoZWlnaHQ9IjI0Ij48cGF0aCBkPSJNMi45OTE4IDIxQzIuNDQ0MDUgMjEgMiAyMC41NTUxIDIgMjAuMDA2NlYzLjk5MzRDMiAzLjQ0NDc2IDIuNDU1MzEgMyAyLjk5MTggM0gyMS4wMDgyQzIxLjU1NiAzIDIyIDMuNDQ0OTUgMjIgMy45OTM0VjIwLjAwNjZDMjIgMjAuNTU1MiAyMS41NDQ3IDIxIDIxLjAwODIgMjFIMi45OTE4Wk0yMCAxNVY1SDRWMTlMMTQgOUwyMCAxNVpNMjAgMTcuODI4NEwxNCAxMS44Mjg0TDYuODI4NDMgMTlIMjBWMTcuODI4NFpNOCAxMUM2Ljg5NTQzIDExIDYgMTAuMTA0NiA2IDlDNiA3Ljg5NTQzIDYuODk1NDMgNyA4IDdDOS4xMDQ1NyA3IDEwIDcuODk1NDMgMTAgOUMxMCAxMC4xMDQ2IDkuMTA0NTcgMTEgOCAxMVoiPjwvcGF0aD48L3N2Zz4=");
            }
        </style>
        <script type="text/javascript">
            function Editor(editor) {
                if (editor instanceof HTMLElement) {
                    this.replaceSelection = function (text) {
                        insertTextAtCursor(editor, text);
                    }
                } else if (typeof editor.replaceSelection !== "undefined") {
                    this.replaceSelection = function (text) {
                        editor.replaceSelection(text);
                    }
                } else if (typeof editor.insertAtCursor !== "undefined") {
                    this.replaceSelection = function (text) {
                        editor.insertAtCursor(text);
                    }
                } else if (typeof editor.updateValue !== "undefined") {
                    this.replaceSelection = function (text) {
                        editor.updateValue(text);
                    }
                } else if (typeof editor.setContent !== "undefined") {
                    this.replaceSelection = function (text) {
                        editor.execCommand('inserthtml', text);
                    }
                } else {
                    this.replaceSelection = function (text) {
                        alert("不支持你编辑器，请禁用查插件")
                    }
                }
            }

            function insertTextAtCursor(insertField, insertValue) {
                //IE 浏览器
                if (document.selection) {
                    let sel;
                    insertField.focus();
                    sel = document.selection.createRange();
                    sel.text = insertValue;
                    sel.select();
                }
                //FireFox、Chrome等
                else if (insertField.selectionStart || insertField.selectionStart == '0') {
                    let startPos = insertField.selectionStart;
                    let endPos = insertField.selectionEnd;
                    // 保存滚动条
                    let restoreTop = insertField.scrollTop;
                    insertField.value = insertField.value.substring(0, startPos) + insertValue + insertField.value.substring(endPos, insertField.value.length);
                    if (restoreTop > 0) {
                        insertField.scrollTop = restoreTop;
                    }
                    insertField.selectionStart = startPos + insertValue.length;
                    insertField.selectionEnd = startPos + insertValue.length;
                    insertField.focus();
                } else {
                    insertField.value += insertValue;
                    insertField.focus();
                }
            }

            let AccFreeEditor;
            if (typeof window.XEditor !== "undefined") {
                AccFreeEditor = new Editor(window.XEditor);
            } else if (typeof window.postEditormd !== "undefined") {
                AccFreeEditor = new Editor(window.postEditormd);
            } else if (typeof window.vditor !== "undefined") {
                AccFreeEditor = new Editor(window.vditor);
            } else if (typeof window.ue1 !== "undefined") {
                AccFreeEditor = new Editor(window.ue1);
            } else {
                AccFreeEditor = new Editor($("#text")[0]);
            }
            $(document).ready(function () {
                function addInsertLink(el) {
                    let name = $('.insert', el).html();
                    let html = '<i title="<?php _e("Accessories:插入附件[");?>' + name + ']" class="accessories" href="#"></i>';
                    if (!($('.accessories', el).length > 0)) {
                        $('.info', el).append(html);
                    }
                }

                $('#file-list li').each(function () {
                    addInsertLink(this);
                });
                $('#file-list li .accessories').on('click', function () {
                    let t = $(this), pp = t.parent().parent(), a = $('.insert', pp);
                    if (pp.data('image') == 0) {
                        AccFreeEditor.replaceSelection('[attach]' + pp.data('cid') + '[/attach]');
                    } else {
                        AccFreeEditor.replaceSelection('[image]' + pp.data('cid') + '[/image]');
                    }
                });
                Typecho.uploadComplete = function (file) {
                    $('#file-list li').each(function () {
                        addInsertLink(this);
                    });
                };
            });
        </script>
        <?php
    }
}
