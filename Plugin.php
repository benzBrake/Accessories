<?php
/**
 * 附件下载插件 感谢 <a href="http://www.imhan.com">Hanny</a> <a href="https://dt27.org">dt27</a>
 *
 * @package Accessories
 * @author Ryan
 * @version 1.0.6
 * @dependence 9.9.2-*
 * @link
 *
 * 历史版本
 *
 * version 1.0.6 at 2021-03=03
 * version 1.0.3 at 2020-04-14
 * 支持 EditorMD编辑器
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
    {}

    /**
     * 解析
     *
     * @access public
     * @param array $matches 解析值
     * @return string
     */
    public static function parseCallback($matches)
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
        $attach_date = ", 最后修改: " . date('Y-m-d H:i', $attach['modified'] + $offset);
        $attach_text = unserialize($attach['text']);
        $attach_size = round($attach_text['size'] / 1024, 1) . " KB";
        $attach_url = Typecho_Common::url('accessories/' . $cid, $options->index);
        if ($options->plugin('Accessories')->useBuildInStat) {
            $attach_views = ", 下载次数: " . self::getViews($cid);
        } else if (isset($options->plugins['activated']['Stat'])) {
            $attach_views = ", 下载次数: " . $attach['views'];
        } else {
            $attach_views = '';
        }
        $text = "<div class='attachment' style='vertical-align:middle;'><img width='12px' height='12px' align='absmiddle' title='Accessories' src='" . $attach_img . "' /> <a href='" . $attach_url . "' title='点击下载' target='_blank'>" . $attach['title'] . "</a> <span class='num'> (" . $attach_size . $attach_views . $attach_date . ") </span> </div>";

        return $text;
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
            return preg_replace_callback("/\[attach\](\d+)\[\/attach\]/is", array('Accessories_Plugin', 'parseCallback'), $text);
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
     * 添加功能JS
     * 点击附件链接自动填写[attach]id[/attach]
     * insertTextAtCursor 来自插件 TePass
     *
     * @return void
     * @date 2020-04-12
     */
    public static function bottomJS()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $attach_img = Typecho_Common::url('Accessories/attach.png', $options->pluginUrl);
        ?>
            <style>
                #file-list .info {
                    position: relative;
                }
                #file-list li .accessories {
                    background-image: url(<?php echo $attach_img; ?>);
                    width: 32px;
                    height: 32px;
                    background-size: cover;
                    position: absolute;
                    cursor: pointer;
                    right: 0;
                    top: -16px;
                }
            </style>
			<script type="text/javascript">
				function insertTextAtCursor(insertValue) {
                    if (typeof postEditormd != "undefined") {
                        // 兼容 EditorMD 编辑器
                        postEditormd.insertValue(insertValue);
                        return(false);
                    }
                    insertField = $('#text')[0]; // Typecho 原版编辑器 Textarea
					//IE 浏览器
	            	if (document.selection) {
	            	    insertField.focus();
	            	    sel = document.selection.createRange();
	            	    sel.text = insertValue;
	            	    sel.select();
	            	}
	            	 //FireFox、Chrome等
	            	else if (insertField.selectionStart || insertField.selectionStart == '0') {
	            	    var startPos = insertField.selectionStart;
	            	    var endPos = insertField.selectionEnd;
	            	    // 保存滚动条
	            	    var restoreTop = insertField.scrollTop;
	            	    insertField.value = insertField.value.substring(0, startPos) + insertValue + insertField.value.substring(endPos, 	insertField.value.length);
	            	    if (restoreTop > 0) {insertField.scrollTop = restoreTop;}
	            	    insertField.selectionStart = startPos + insertValue.length;
	            	    insertField.selectionEnd = startPos + insertValue.length;
	            	    insertField.focus();
	            	} else {
	            	    insertField.value += insertValue;
	            	    insertField.focus();
	            	}
				}
				$(document).ready(function(){
					function addInsertLink (el) {
                        name = $('.insert', el).html();
                        html = '<i title="<?php _e("Accessories:插入附件[");?>' + name  + ']" class="accessories" href="#"></i>';
                        if (!($('.accessories', el).length > 0)) {
						    $('.info', el).append(html);
                        }
                    }

					$('#file-list li').each(function () {
						addInsertLink(this);
					});
                    $('#file-list li .accessories').on('click', function() {
                        var t = $(this), pp = t.parent().parent(), a = $('.insert', pp);
					    if(pp.data('image') == 0) {
					    	insertTextAtCursor('[attach]' + pp.data('cid') + '[/attach]');
					    } else {
					    	insertTextAtCursor('![' + a.text() + '](' + pp.data('url') + ')');
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
