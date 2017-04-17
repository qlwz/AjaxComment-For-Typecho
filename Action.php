<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Typecho Ajax 评论 操作类
 *
 * @package AjaxComment
 * @author 情留メ蚊子
 * @version 1.0.0.0
 * @link http://www.94qing.com/
 */
class AjaxComment_Action extends Typecho_Widget implements Widget_Interface_Do {

    /**
     * 发表评论
     *
     * @access public
     * @return void
     */
    public function post_comment() {
        if ('POST' != $_SERVER['REQUEST_METHOD']) {
            header('Allow: POST');
            header('HTTP/1.1 405 Method Not Allowed');
            header('Content-Type: text/plain');
            exit;
        }

        $comment_post_ID = $this->request->filter('int')->get('comment_post_ID');
        $comment_parent = $this->request->filter('int')->get('parent');
        if ($comment_parent == 0) {
            $comment_parent = $this->request->filter('int')->get('comment_parent');
        }

        $post = Typecho_Widget::widget('Widget_Archive', 'type=single', 'cid=' . $comment_post_ID, false);
        if (!isset($post) || !($post instanceof Widget_Archive) || !$post->have() || !$post->is('single')) {
            $this->err('文章不存在');
        }

        if (!$post->allow('comment')) {
            $this->err(_t('对不起，此内容的评论功能已经关闭！'));
        }

        $options = Typecho_Widget::widget('Widget_Options');
        $settings = $options->plugin('AjaxComment');
        $user = Typecho_Widget::widget('Widget_User');

        /** 检查来源 */
        if ($options->commentsCheckReferer) {
            $referer = $this->request->getReferer();

            if (empty($referer)) {
                $this->err(_t('评论来源页错误'));
            }

            $refererPart = parse_url($referer);
            $currentPart = parse_url($post->permalink);

            if ($refererPart['host'] != $currentPart['host'] || 0 !== strpos($refererPart['path'], $currentPart['path'])) {
                //自定义首页支持
                if ('page:' . $post->cid == $options->frontPage) {
                    $currentPart = parse_url(rtrim($options->siteUrl, '/') . '/');

                    if ($refererPart['host'] != $currentPart['host'] || 0 !== strpos($refererPart['path'], $currentPart['path'])) {
                        $this->err(_t('评论来源页错误'));
                    }
                } else {
                    $this->err(_t('评论来源页错误'));
                }
            }
        }

        $comment = array();
        $comment['permalink'] = $post->pathinfo;
        $comment['type'] = 'comment';
        $comment['text'] = $this->request->text;
        $comment['parent'] = $comment_parent;

        if (!$user->hasLogin()) {
            $comment['author'] = $this->request->filter('trim')->author;
            $comment['mail'] = $this->request->filter('trim')->mail;
            $comment['url'] = $this->request->filter('trim')->url;

            /** 修正用户提交的url */
            if (!empty($comment['url'])) {
                $urlParams = parse_url($comment['url']);
                if (!isset($urlParams['scheme'])) {
                    $comment['url'] = 'http://' . $comment['url'];
                }
            }
        } else {
            $comment['author'] = $user->screenName;
            $comment['mail'] = $user->mail;
            $comment['url'] = $user->url;
        }

        if ($settings->comment_needchinese) {
            if (!preg_match('/[一-龥]/u', $comment['text'])) {
                $this->err('评论内容中必须含有中文');
            }
        }

        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array($this, 'Widget_Feedback_finishComment');

        try {
            $commentWidget = Typecho_Widget::widget('Widget_Feedback', 'checkReferer=false', $comment, false);
            $commentWidget->action();
        } catch (Exception $e) {
            $this->err($e->getMessage());
        }
    }

    /**
     * 输出错误
     *
     * @param $errmsg
     */
    public function err($errmsg) {
        header('HTTP/1.1 405 Method Not Allowed');
        echo $errmsg;
        exit;
    }

    /**
     * 评论后续处理
     *
     * @access public
     * @param object $comments 评论信息
     * @return void
     */
    public function Widget_Feedback_finishComment($comments) {
        $options = Typecho_Widget::widget('Widget_Options');
        $options->commentsPageBreak = 0;

        $_themeDir = rtrim($options->themeFile($options->theme), '/') . '/';
        $ajaxfile = $_themeDir . 'comments.php';
        if (file_exists($ajaxfile)) {
            $file_content = file_get_contents($ajaxfile);
            $check_code = "return true; ?>";
            $file_content = $check_code . $file_content . "<?php ";
            @eval($file_content);
        }

        $parameter = array('parentId' => $comments->cid);
        $comments_Archive = Typecho_Widget::widget('Widget_Comments_Archive', $parameter);
        if (!$comments_Archive->have()) {
            $this->err('获取评论出错');
        }

        $db = Typecho_Db:: get();
        $parentids = array();
        $parentids[] = $comments->coid;

        $parent = $comments->parent;
        while ($parent) {
            $parentids[] = $parent;
            $parentRows = $db->fetchRow($db->select('parent')->from('table.comments')->where('coid = ? AND status = ?', $parent, 'approved')->limit(1));
            $parent = $parentRows['parent'];
        }
        $this->getlistComments($comments, $comments_Archive, $parentids);
        return;
    }

    private function getlistComments($comments, $comments_Archive, $parentids, $lv = 0) {
        while ($comments_Archive->next()) {
            if ($comments_Archive->coid == $comments->coid) {
                /*
                if ($lv == 0 && count($comments_Archive->stack) == 1) {
                    $singleCommentOptions = '';
                } else {
                    $singleCommentOptions = 'before=&after=';
                }
                */
                $singleCommentOptions = 'before=&after=';
                $comments_Archive->length = 1;
                $comments_Archive->stack = array($comments->coid => $comments_Archive->row);
                $comments_Archive->listComments($singleCommentOptions);
                return true;
            } else if (in_array($comments_Archive->coid, $parentids)) {
                $comments_Archive->stack = $comments_Archive->children;
                if ($this->getlistComments($comments, $comments_Archive, $parentids, $lv++)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 绑定动作
     *
     * @access public
     * @return void
     */
    public function action() {
        $this->on($this->request->is('do=post'))->post_comment();
    }
}
