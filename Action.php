<?php

/**
 * Accessories_Action
 * 原文件名下载功能来自 DownloadFile
 * 
 * @package Accessories
 * @author Ryan
 * @date 2020-04-12
 */
class Accessories_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        if ($this->request->filter('int')->cid) {
            $cid = $this->request->filter('int')->cid;
            Typecho_Db::get()->fetchRow(Typecho_Db::get()->select()->from('table.contents')
                ->where('table.contents.type = ?', 'attachment')
                ->where('table.contents.cid = ?', $cid)
                ->limit(1), array($this, 'push'));

            if (!$this->have()) {
                throw new Typecho_Widget_Exception("附件文件不存在或无法读入，请与管理员联系。");
            }
            $info = unserialize($this->text);
            if ($options->plugin('Accessories')->useBuildInStat) {
                Accessories_Plugin::viewStat($cid);
            } else if (isset($options->plugins['activated']['Stat'])) {
                Stat_Plugin::viewStat($cid);
            }
            if ($options->plugin('Accessories')->hideRealPath == 1) {
                $file = @fopen($_SERVER['DOCUMENT_ROOT'] . $info['path'], "rb");
                if ($file) {
                    header("Pragma: public");
                    header("Expires: 0");
                    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                    header("Cache-Control: private", false);
                    header("Content-Type: {$info['mime']}");
                    header("Content-Disposition: attachment; filename=\"{$info['name']}\";");
                    header("Content-Transfer-Encoding: binary");
                    header("Content-Length: {$info['size']}");
                    while (!feof($file)) {
                        print(fread($file, 1024 * 8));
                        flush();
                        if (connection_status() != 0) {
                            @fclose($file);
                            die();
                        }
                    }
                    @fclose($file);
                } else {
                    throw new Typecho_Widget_Exception("附件文件不存在或无法读入，请与管理员联系。");
                }
            } else {
                if (strpos($info['path'], 'http') === 0) {
                    $this->response->redirect($info['path'], 302);
                } else {
                    if ($options->plugin('Accessories')->cdnDomain) {
                        $this->response->redirect(Typecho_Common::url($info['path'], $options->plugin('Accessories')->cdnDomain), 302);
                    } else {
                        $this->response->redirect(Typecho_Common::url($info['path'], $options->index), 302);
                    }
                }
            }
        } else {
            throw new Typecho_Widget_Exception("附件文件不存在或无法读入，请与管理员联系。");
        }
    }
}
