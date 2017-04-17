<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$this_plugin_name = 'AjaxComment';
if (basename(dirname(__FILE__)) != $this_plugin_name) {
    $now_dir = dirname(__FILE__);
    $dir_name = basename($now_dir);
    $tar_dir = __TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__ . '/' . $this_plugin_name;
    if (file_exists($tar_dir)) {
        echo '请把本插件目录名修改为：' . $this_plugin_name . '，现在的目录名为：' . $dir_name;
        exit();
    }
    if (rename($now_dir, $tar_dir)) {
        $url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $url = str_replace($dir_name, $this_plugin_name, $url);
        header('Location: ' . $url);
    } else {
        echo '请把本插件目录名修改为：' . $this_plugin_name . '，现在的目录名为：' . $dir_name;
    }
    exit();
}

/**
 * Typecho Ajax 评论
 *
 * @package AjaxComment
 * @author 情留メ蚊子
 * @version 1.0.0.1
 * @link http://www.94qing.com/
 */
class AjaxComment_Plugin implements Typecho_Plugin_Interface {
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate() {
        Typecho_Plugin::factory('Widget_Archive')->afterRender = array('AjaxComment_Plugin', 'Widget_Archive_afterRender');

        Helper::addAction('ajaxcomment', 'AjaxComment_Action');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {
        Helper::removeAction('ajaxcomment');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form) {
        $options = Typecho_Widget::widget('Widget_Options');
        $plugin_url = $options->pluginUrl . '/' . basename(dirname(__FILE__)) . '/';

        $note = '<br/><br/><br/>
        1、打开当前主题下的comments.php文件<br/>
        2、评论主体设置方法：搜索【<font style="color: red">listComments</font>】，如果为<font style="color: red">listComments()</font>则使用默认值，否则按照<font style="color: red">before</font>的元素设置<br/>
        3、评论嵌套设置方法：搜索【<font style="color: red">threadedComments</font>】，如果没有找到则使用默认，否则查找【第二个 <font style="color: red">threadedComments</font>】使用关键词上级的元素<br/>
        4、以上方法仅对标准结构的主题<br/>';

        $compression_open = new Typecho_Widget_Helper_Form_Element_Checkbox('ajaxcomment_open', array('ajaxcomment_open' => '开启 Ajax 评论'), 'ajaxcomment_open', _t('是否开启 Ajax 评论'));
        $form->addInput($compression_open);

        $comment_list_element = new Typecho_Widget_Helper_Form_Element_Text('comment_list_element', null, 'ol', '评论主体元素', '&nbsp;&nbsp;&nbsp;&nbsp; &lt;<font style="color: red">ol</font> class="comment-list">');
        $comment_list_element->input->setAttribute('style', 'float:left; width:200px;');
        $form->addInput($comment_list_element);

        $comment_list_class = new Typecho_Widget_Helper_Form_Element_Text('comment_list_class', null, 'comment-list', '评论主体样式', '&nbsp;&nbsp;&nbsp;&nbsp; &lt;ol class="<font style="color: red">comment-list</font>">');
        $comment_list_class->input->setAttribute('style', 'float:left; width:200px;');
        $form->addInput($comment_list_class);


        $comment_children_list_element = new Typecho_Widget_Helper_Form_Element_Text('comment_children_list_element', null, 'div', '评论嵌套元素', '&nbsp;&nbsp;&nbsp;&nbsp; &lt;<font style="color: red">div</font> class="comment-list">');
        $comment_children_list_element->input->setAttribute('style', 'float:left; width:200px;');
        $form->addInput($comment_children_list_element);

        $comment_children_list_class = new Typecho_Widget_Helper_Form_Element_Text('comment_children_list_class', null, 'comment-children', '评论嵌套样式', '&nbsp;&nbsp;&nbsp;&nbsp; &lt;div class="<font style="color: red">comment-children</font>">' . $note);
        $comment_children_list_class->input->setAttribute('style', 'float:left; width:200px;');
        $form->addInput($comment_children_list_class);

        $comment_needchinese = new Typecho_Widget_Helper_Form_Element_Checkbox('comment_needchinese', array('comment_needchinese' => '评论内容中必须含有中文'), 'comment_needchinese', _t('其他设置'));
        $form->addInput($comment_needchinese);

        if (!$options->commentsThreaded) {
            echo '<span style="color:#d60;"> 评论回复功能未启用, 请先在 "设置"->"评论"->"评论显示" 启用评论回复.</span><br>';
        }

        $img = '<br><br><input class="btn" onclick="openfile()" type="button" value="查看 comments.php 文件"><br><br>评论主体说明：<br><img src="' . $plugin_url . 'image/comment-list.jpg"/>';
        $img .= '<br><br><br>评论镶嵌说明：<br><img src="' . $plugin_url . 'image/comment-children.jpg"/>';
        echo '<script type="text/javascript">';
        echo 'window.onload = function() {$(\'form\').append(\'' . $img . '\');};function openfile(){window.open("theme-editor.php?theme=' . $options->theme . '&file=comments.php")}';
        echo '</script>';
    }

    public static function configHandle($settings, $isInit) {
        $db = Typecho_Db::get();
        if (!$settings['comment_list_element']) {
            $settings['comment_list_element'] = 'ol';
        }
        if (!$settings['comment_list_class']) {
            $settings['comment_list_class'] = 'comment-list';
        }
        if (!$settings['comment_children_list_element']) {
            $settings['comment_children_list_element'] = 'div';
        }
        if (!$settings['comment_children_list_class']) {
            $settings['comment_children_list_class'] = 'comment-children';
        }

        $pluginName = 'plugin:AjaxComment';
        $select = $db->select()->from('table.options')->where('name = ?', $pluginName);
        $options = $db->fetchAll($select);
        if (empty($settings)) {
            if (!empty($options)) {
                $db->query($db->delete('table.options')->where('name = ?', $pluginName));
            }
        } else {
            if (empty($options)) {
                $db->query($db->insert('table.options')->rows(array('name' => $pluginName, 'value' => serialize($settings), 'user' => 0)));
            } else {
                foreach ($options as $option) {
                    $value = unserialize($option['value']);
                    $value = array_merge($value, $settings);
                    $db->query($db->update('table.options')->rows(array('value' => serialize($value)))->where('name = ?', $pluginName)->where('user = ?', $option['user']));
                }
            }
        }
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {
    }

    public static function Widget_Archive_afterRender($archive) {
        if (!$archive->is('single')) {
            return;
        }
        if (!$archive->allow('comment')) {
            return;
        }

        $options = Typecho_Widget::widget('Widget_Options');
        $settings = $options->plugin('AjaxComment');
        if (!$settings->ajaxcomment_open) {
            return;
        }

        $plugin_url = $options->pluginUrl . '/' . basename(dirname(__FILE__)) . '/';
        ?>
        <script type="text/javascript">
            var ajaxcomment_url = '<?php echo $options->index('/action/ajaxcomment?do=post&comment_post_ID=' . $archive->cid);?>';
            var loading_div = '<div id="AjaxComment_loading" style="display:none"><img src="<?php echo $plugin_url; ?>image/loading.gif"/></div>';
            var success_div = '<div class="AjaxComment_success"><img src="<?php echo $plugin_url; ?>image/success.png" />&nbsp;提交成功.</div>';
            var error_div = '<div id="AjaxComment_error" style="display:none"><img src="<?php echo $plugin_url; ?>image/error.png"/><span id="AjaxComment_msg"></span></div>';
            var id_format = 'comment-{id}';
            var respond_id = 'respond-post-<?php echo $archive->cid;?>';
            var comments_order = '<?php echo $options->commentsOrder; ?>';

            var comment_list_element = '<?php echo $settings->comment_list_element; ?>';
            var comment_list_class = '<?php echo $settings->comment_list_class; ?>';
            var comment_list_class_one = comment_list_class.split(' ')[0];

            var comment_children_list_element = '<?php echo $settings->comment_children_list_element; ?>';
            var comment_children_list_class = '<?php echo $settings->comment_children_list_class; ?>';
            var comment_children_list_class_one = comment_children_list_class.split(' ')[0];
        </script>
        <?php
        echo '<script type="text/javascript" src="' . $plugin_url . 'js/ajaxcomment.min.js"></script>';
    }

}