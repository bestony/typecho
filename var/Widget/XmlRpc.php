<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
/**
 * Typecho Blog Platform
 *
 * @copyright  Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license    GNU General Public License 2.0
 * @version    $Id$
 */

/**
 * XmlRpc接口
 *
 * @author blankyao
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Widget_XmlRpc extends Widget_Abstract_Contents implements Widget_Interface_Do
{
    /**
     * 当前错误
     *
     * @access private
     * @var IXR_Error
     */
    private $error;

    /**
     * wordpress风格的系统选项
     *
     * @access private
     * @var array
     */
    private $_wpOptions;

    /**
     * 已经使用过的组件列表
     *
     * @access private
     * @var array
     */
    private $_usedWidgetNameList = array();

    /**
     * 获取扩展字段
     *
     * @access private
     * @param Widget_Abstract_Contents $content
     * @return array
     */
    private function getPostExtended(Widget_Abstract_Contents $content)
    {
        //根据客户端显示来判断是否显示html代码
        $agent = $this->request->getAgent();
        $text = '';

        switch (true) {
            case false !== strpos($agent, 'wp-iphone'):   // wordpress iphone客户端
            case false !== strpos($agent, 'wp-blackberry'):  // 黑莓
            case false !== strpos($agent, 'wp-andriod'):  // andriod
            case false !== strpos($agent, 'plain-text'):  // 这是预留给第三方开发者的接口, 用于强行调用非所见即所得数据
            case $this->options->xmlrpcMarkdown:
                $text = $content->text;
                break;
            default:
                $text = $content->content;
                break;
        }

        $post = explode('<!--more-->', $text, 2);
        return array(
            $this->options->xmlrpcMarkdown? $post[0] : Typecho_Common::fixHtml($post[0]),
            isset($post[1]) ? Typecho_Common::fixHtml($post[1]) : null
        );
    }

    /**
     * 将typecho的状态类型转换为wordperss的风格
     *
     * @access private
     * @param string $status typecho的状态
     * @param string $type 内容类型
     * @return string
     */
    private function typechoToWordpressStatus($status, $type = 'post')
    {
        if ('post' == $type) {
            /** 文章状态 */
            switch ($status) {
                case 'waiting':
                    return 'pending';
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('page' == $type) {
            switch ($status) {
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('comment' == $type) {
            switch ($status) {
                case 'publish':
                case 'approved':
                    return 'approve';
                case 'waiting':
                    return 'hold';
                case 'spam':
                    return $status;
                default:
                    return 'approve';
            }
        }

        return '';
    }

    /**
     * 将wordpress的状态类型转换为typecho的风格
     *
     * @access private
     * @param string $status wordpress的状态
     * @param string $type 内容类型
     * @return string
     */
    private function wordpressToTypechoStatus($status, $type = 'post')
    {
        if ('post' == $type) {
            /** 文章状态 */
            switch ($status) {
                case 'pending':
                    return 'waiting';
                case 'publish':
                case 'draft':
                case 'private':
                case 'waiting':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('page' == $type) {
            switch ($status) {
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } elseif ('comment' == $type) {
            switch ($status) {
                case 'approve':
                case 'publish':
                case 'approved':
                    return 'approved';
                case 'hold':
                case 'waiting':
                    return 'waiting';
                case 'spam':
                    return $status;
                default:
                    return 'approved';
            }
        }

        return '';
    }

    /**
     * 代理工厂方法,将类静态化放置到列表中
     *
     * @access public
     * @param string $alias 组件别名
     * @param mixed $params 传递的参数
     * @param mixed $request 前端参数
     * @param boolean $enableResponse 是否允许http回执
     * @return object
     * @throws Typecho_Exception
     */
    private function singletonWidget($alias, $params = null, $request = null, $enableResponse = true)
    {
        $this->_usedWidgetNameList[] = $alias;
        return Typecho_Widget::widget($alias, $params, $request, $enableResponse);
    }

    /**
     * 如果这里没有重载, 每次都会被默认执行
     *
     * @access public
     * @param boolen $run 是否执行
     * @return void
     */
    public function execute($run = false)
    {
        if ($run) {
            parent::execute();
        }

        // 临时保护模块
        $this->security->enable(false);

        $this->_wpOptions = array(
            // Read only options
            'software_name'		=> array(
                'desc'			=> _t('软件名称'),
                'readonly'		=> true,
                'value'			=> $this->options->software
            ),
            'software_version'	=> array(
                'desc'			=> _t('软件版本'),
                'readonly'		=> true,
                'value'			=> $this->options->version
            ),
            'blog_url'			=> array(
                'desc'			=> _t('博客地址'),
                'readonly'		=> true,
                'option'		=> 'siteUrl'
            ),
            'home_url'          => array(
                'desc'          => _t('博客首页地址'),
                'readonly'      => true,
                'option'        => 'siteUrl'
            ),
            'login_url'         => array(
                'desc'          => _t('登录地址'),
                'readonly'      => true,
                'value'         => $this->options->siteUrl.'admin/login.php'
            ),
             'admin_url'        => array(
                'desc'          => _t('管理区域的地址'),
                'readonly'      => true,
                'value'         => $this->options->siteUrl.'admin/'
            ),

            'post_thumbnail'    => array(
                'desc'          => _t('文章缩略图'),
                'readonly'      => true,
                'value'         => false
            ),

            // Updatable options
            'time_zone'			=> array(
                'desc'			=> _t('时区'),
                'readonly'		=> false,
                'option'		=> 'timezone'
            ),
            'blog_title'		=> array(
                'desc'			=> _t('博客标题'),
                'readonly'		=> false,
                'option'			=> 'title'
            ),
            'blog_tagline'		=> array(
                'desc'			=> _t('博客关键字'),
                'readonly'		=> false,
                'option'		=> 'description'
            ),
            'date_format'		=> array(
                'desc'			=> _t('日期格式'),
                'readonly'		=> false,
                'option'		=> 'postDateFormat'
            ),
            'time_format'		=> array(
                'desc'			=> _t('时间格式'),
                'readonly'		=> false,
                'option'		=> 'postDateFormat'
            ),
            'users_can_register'	=> array(
                'desc'			=> _t('是否允许注册'),
                'readonly'		=> false,
                'option'		=> 'allowRegister'
            )
        );
    }

    /**
     * 检查权限
     *
     * @access public
     * @return void
     */
    public function checkAccess($name, $password, $level = 'contributor')
    {
        if ($this->user->login($name, $password, true)) {
            /** 验证权限 */
            if ($this->user->pass($level, true)) {
                $this->user->execute();
                return true;
            } else {
                $this->error = new IXR_Error(403, _t('权限不足'));
                return false;
            }
        } else {
            $this->error = new IXR_Error(403, _t('无法登陆, 密码错误'));
            return false;
        }
    }

    /**
     * 获取pageId指定的page
     * about wp xmlrpc api, you can see http://codex.wordpress.org/XML-RPC
     *
     * @param int $blogId
     * @param int $pageId
     * @param string $userName
     * @param string $password
     * @access public
     * @return struct $pageStruct
     */
    public function wpGetPage($blogId, $pageId, $userName, $password)
    {
        /** 检查权限 */
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        /** 获取页面 */
        try {
            /** 由于Widget_Contents_Page_Edit是从request中获取参数, 因此我们需要强行设置flush一下request */
            /** widget方法的第三个参数可以指定强行转换传入此widget的request参数 */
            /** 此组件会进行复杂的权限检测 */
            $page = $this->singletonWidget('Widget_Contents_Page_Edit', null, "cid={$pageId}");
        } catch (Typecho_Widget_Exception $e) {
            /** 截获可能会抛出的异常(参见 Widget_Contents_Page_Edit 的 execute 方法) */
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        /** 对文章内容做截取处理，以获得description和text_more*/
        list($excerpt, $more) = $this->getPostExtended($page);

        $pageStruct = array(
            'dateCreated'   => new IXR_Date($this->options->timezone + $page->created),
            'userid'        => $page->authorId,
            'page_id'       => $page->cid,
            'page_status'   => $this->typechoToWordpressStatus($page->status, 'page'),
            'description'   => $excerpt,
            'title'         => $page->title,
            'link'          => $page->permalink,
            'permaLink'     => $page->permalink,
            'categories'    => $page->categories,
            'excerpt'       => $page->description,
            'text_more'     => $more,
            'mt_allow_comments' => intval($page->allowComment),
            'mt_allow_pings'    => intval($page->allowPing),
            'wp_slug'       => $page->slug,
            'wp_password'   => $page->password,
            'wp_author'     => $page->author->name,
            'wp_page_parent_id' => '0',
            'wp_page_parent_title' => '',
            'wp_page_order' => $page->order,     //meta是描述字段, 在page时表示顺序
            'wp_author_id'  => $page->authorId,
            'wp_author_display_name' => $page->author->screenName,
            'date_created_gmt'  => new IXR_Date($page->created),
            'custom_fields'     => array(),
            'wp_page_template'  =>  $page->template
        );

        return $pageStruct;
    }

    /**
     * 获取所有的page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return array(contains $pageStruct)
     */
    public function wpGetPages($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        /** 过滤type为page的contents */
        /** 同样需要flush一下, 需要取出所有status的页面 */
        $pages = $this->singletonWidget('Widget_Contents_Page_Admin', null, 'status=all');

        /** 初始化要返回的数据结构 */
        $pageStructs = array();

        while ($pages->next()) {
            /** 对文章内容做截取处理，以获得description和text_more*/
            list($excerpt, $more) = $this->getPostExtended($pages);
            $pageStructs[] = array(
                'dateCreated'            => new IXR_Date($this->options->timezone + $pages->created),
                'userid'                 => $pages->authorId,
                'page_id'                => intval($pages->cid),
                'page_status'            => $this->typechoToWordpressStatus(($pages->hasSaved || 'page_draft' == $pages->type) ? 'draft' : $pages->status, 'page'),
                'description'            => $excerpt,
                'title'                  => $pages->title,
                'link'                   => $pages->permalink,
                'permaLink'              => $pages->permalink,
                'categories'             => $pages->categories,
                'excerpt'                => $pages->description,
                'text_more'              => $more,
                'mt_allow_comments'      => intval($pages->allowComment),
                'mt_allow_pings'         => intval($pages->allowPing),
                'wp_slug'                => $pages->slug,
                'wp_password'            => $pages->password,
                'wp_author'              => $pages->author->name,
                'wp_page_parent_id'      => 0,
                'wp_page_parent_title'   => '',
                'wp_page_order'          => intval($pages->order),     //meta是描述字段, 在page时表示顺序
                'wp_author_id'           => $pages->authorId,
                'wp_author_display_name' => $pages->author->screenName,
                'date_created_gmt'       => new IXR_Date($pages->created),
                'custom_fields'          => array(),
                'wp_page_template'       =>  $pages->template
            );
        }

        return $pageStructs;
    }

    /**
     * 撰写一个新page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param struct $content
     * @param bool $publish
     * @access public
     * @return void
     */
    public function wpNewPage($blogId, $userName, $password, $content, $publish)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }
        $content['post_type'] = 'page';
        $this->mwNewPost($blogId, $userName, $password, $content, $publish);
    }

    /**
     * 删除pageId指定的page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $pageId
     * @access public
     * @return bool
     */
    public function wpDeletePage($blogId, $userName, $password, $pageId)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }

        /** 删除页面 */
        try {
            /** 此组件会进行复杂的权限检测 */
            $this->singletonWidget('Widget_Contents_Page_Edit', null, "cid={$pageId}", false)->deletePage();
        } catch (Typecho_Widget_Exception $e) {
            /** 截获可能会抛出的异常(参见 Widget_Contents_Page_Edit 的 execute 方法) */
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        return true;
    }

    /**
     * 编辑pageId指定的page
     *
     * @param int $blogId
     * @param int $pageId
     * @param string $userName
     * @param string $password
     * @param struct $content
     * @param bool $publish
     * @access public
     * @return bool
     */
    public function wpEditPage($blogId, $pageId, $userName, $password, $content, $publish)
    {
        $content['post_type'] = 'page';
        $this->mwEditPost($pageId, $userName, $password, $content, $publish);
    }


    /**
     * 编辑postId指定的post
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postId
     * @param struct $content
     * @access public
     * @return bool
     */
    public function wpEditPost($blogId, $userName, $password, $postId, $content)
    {
        $post = $this->singletonWidget('Widget_Archive', 'type=single', 'cid=' . $postId, false);
        if ($post->type == 'attachment') {
            $attachment['title'] = $content['post_title'];
            $attachment['slug'] = $content['post_excerpt'];

            $text = unserialize($post->text);
            $text['description'] = $content['description'];

            $attachment['text'] = serialize($text);

            /** 更新数据 */
            $updateRows = $this->update($attachment, $this->db->sql()->where('cid = ?', $postId));
            return true;
        }
        return $this->mwEditPost($blogId, $postId, $userName, $password, $content);
    }

    /**
     * 获取page列表，没有wpGetPages获得的详细
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return array
     */
    public function wpGetPageList($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return ($this->error);
        }
        $pages = $this->singletonWidget('Widget_Contents_Page_Admin', null, 'status=all');
        /**初始化*/
        $pageStructs = array();

        while ($pages->next()) {
            $pageStructs[] = array(
                'dateCreated'       => new IXR_Date($this->options->timezone + $pages->created),
                'date_created_gmt'  => new IXR_Date($this->options->timezone + $pages->created),
                'page_id'           => $pages->cid,
                'page_title'        => $pages->title,
                'page_parent_id'    => '0',
            );
        }

        return $pageStructs;
    }

    /**
     * 获得一个由blog所有作者的信息组成的数组
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return struct
     */
    public function wpGetAuthors($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return ($this->error);
        }

        /** 构建查询*/
        $select = $this->db->select('table.users.uid', 'table.users.name', 'table.users.screenName')->from('table.users');
        $authors = $this->db->fetchAll($select);

        $authorStructs = array();
        foreach ($authors as $author) {
            $authorStructs[] = array(
                'user_id'       => $author['uid'],
                'user_login'    => $author['name'],
                'display_name'  => $author['screenName']
            );
        }

        return $authorStructs;
    }

    /**
     * 添加一个新的分类
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param struct $category
     * @access public
     * @return void
     */
    public function wpNewCategory($blogId, $userName, $password, $category)
    {
        if (!$this->checkAccess($userName, $password)) {
            return ($this->error);
        }

        /** 开始接受数据 */
        $input['name'] = $category['name'];
        $input['slug'] = Typecho_Common::slugName(empty($category['slug']) ? $category['name'] : $category['slug']);
        $input['parent'] = isset($category['parent_id']) ? $category['parent_id'] :
            (isset($category['parent']) ? $category['parent'] : 0);
        $input['description'] = isset($category['description']) ? $category['description'] : $category['name'];
        $input['do'] = 'insert';

        /** 调用已有组件 */
        try {
            /** 插入 */
            $categoryWidget = $this->singletonWidget('Widget_Metas_Category_Edit', null, $input, false);
            $categoryWidget->action();
            return $categoryWidget->mid;
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        return new IXR_Error(403, _t('无法添加分类'));
    }

    /**
     * 获取由给定的string开头的链接组成的数组
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param string $category
     * @param int $max_results
     * @access public
     * @return array
     */
    public function wpSuggestCategories($blogId, $userName, $password, $category, $max_results)
    {
        if (!$this->checkAccess($userName, $password)) {
            return ($this->error);
        }

        $meta = $this->singletonWidget('Widget_Abstract_Metas');

        /** 构造出查询语句并且查询*/
        $key = Typecho_Common::filterSearchQuery($category);
        $key = '%' . $key . '%';
        $select = $meta->select()->where('table.metas.type = ? AND (table.metas.name LIKE ? OR slug LIKE ?)', 'category', $key, $key);

        /** 不要category push到contents的容器中 */
        $categories = $this->db->fetchAll($select);

        /** 初始化categorise数组*/
        $categoryStructs = array();
        foreach ($categories as $category) {
            $categoryStructs[] = array(
                'category_id'   => $category['mid'],
                'category_name' => $category['name'],
            );
        }

        return $categoryStructs;
    }

    /**
     * 获取用户
     *
     * @access public
     * @param string $userName 用户名
     * @param string $password 密码
     * @return array
     */
    public function wpGetUsersBlogs($userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $struct = array();
        $struct[] = array(
            'isAdmin'   => $this->user->pass('administrator', true),
            'url'       => $this->options->siteUrl,
            'blogid'    => '1',
            'blogName'  => $this->options->title,
            'xmlrpc'    => $this->options->xmlRpcUrl
        );
        return $struct;
    }

    /**
     * 获取用户
     *
     * @access public
     * @param string $userName 用户名
     * @param string $password 密码
     * @return array
     */
    public function wpGetProfile($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $struct = array(
            'user_id'       => $this->user->uid,
            'username'      => $this->user->name,
            'first_name'    => '',
            'last_name'     => '',
            'registered'    => new IXR_Date($this->options->timezone +  $this->user->created),
            'bio'           => '',
            'email'         => $this->user->mail,
            'nickname'      => $this->user->screenName,
            'url'           => $this->user->url,
            'display_name'  => $this->user->screenName,
            'roles'         => $this->user->group
        );
        return $struct;
    }

    /**
     * 获取标签列表
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetTags($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $struct = array();
        $tags = $this->singletonWidget('Widget_Metas_Tag_Cloud');

        while ($tags->next()) {
            $struct[] = array(
                'tag_id'    =>  $tags->mid,
                'name'      =>  $tags->name,
                'count'     =>  $tags->count,
                'slug'      =>  $tags->slug,
                'html_url'  =>  $tags->permalink,
                'rss_url'   =>  $tags->feedUrl
            );
        }

        return $struct;
    }

    /**
     * 删除分类
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $categoryId
     * @return array
     */
    public function wpDeleteCategory($blogId, $userName, $password, $categoryId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }

        try {
            $this->singletonWidget('Widget_Metas_Category_Edit', null, 'do=delete&mid=' . intval($categoryId), false);
            return true;
        } catch (Typecho_Exception $e) {
            return false;
        }
    }

    /**
     * 获取评论数目
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $postId
     * @return array
     */
    public function wpGetCommentCount($blogId, $userName, $password, $postId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $stat = $this->singletonWidget('Widget_Stat', null, 'cid=' . intval($postId), false);

        return array(
            'approved' => $stat->currentPublishedCommentsNum,
            'awaiting_moderation' => $stat->currentWaitingCommentsNum,
            'spam' => $stat->currentSpamCommentsNum,
            'total_comments' => $stat->currentCommentsNum
        );
    }


    /**
     * 获取文章类型列表
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPostFormats($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        return array(
            'standard' => _t('标准')
        );
    }

    /**
     * 获取文章状态列表
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPostStatusList($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        return array(
            'draft'     =>  _t('草稿'),
            'pending'   =>  _t('待审核'),
            'publish'   =>  _t('已发布')
        );
    }

    /**
     * 获取页面状态列表
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPageStatusList($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }

        return array(
            'draft'     =>  _t('草稿'),
            'publish'   =>  _t('已发布')
        );
    }



    /**
     * 获取评论状态列表
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetCommentStatusList($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        return array(
            'hold'      =>  _t('待审核'),
            'approve'   =>  _t('显示'),
            'spam'      =>  _t('垃圾')
        );
    }

    /**
     * 获取页面模板
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPageTemplates($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }

        $templates = array_flip($this->getTemplates());
        $templates['Default'] = '';

        return $templates;
    }

    /**
     * 获取系统选项
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array
     */
    public function wpGetOptions($blogId, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = array();
        if (empty($options)) {
            $options = array_keys($this->_wpOptions);
        }

        foreach ($options as $option) {
            if (isset($this->_wpOptions[$option])) {
                $struct[$option] = $this->_wpOptions[$option];
                if (isset($struct[$option]['option'])) {
                    $struct[$option]['value'] = $this->options->{$struct[$option]['option']};
                    unset($struct[$option]['option']);
                }
            }
        }

        return $struct;
    }

    /**
     * 设置系统选项
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array
     */
    public function wpSetOptions($blogId, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = array();
        foreach ($options as $option => $value) {
            if (isset($this->_wpOptions[$option])) {
                $struct[$option] = $this->_wpOptions[$option];
                if (isset($struct[$option]['option'])) {
                    $struct[$option]['value'] = $this->options->{$struct[$option]['option']};
                    unset($struct[$option]['option']);
                }

                if (!$this->_wpOptions[$option]['readonly'] && isset($this->_wpOptions[$option]['option'])) {
                    if ($this->db->query($this->db->update('table.options')
                    ->rows(array('value' => $value))
                    ->where('name = ?', $this->_wpOptions[$option]['option'])) > 0) {
                        $struct[$option]['value'] = $value;
                    }
                }
            }
        }

        return $struct;
    }

    /**
     * 获取评论
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @return array
     */
    public function wpGetComment($blogId, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $comment = $this->singletonWidget('Widget_Comments_Edit', null, 'do=get&coid=' . intval($commentId), false);

        if (!$comment->have()) {
            return new IXR_Error(404, _t('评论不存在'));
        }

        if (!$comment->commentIsWriteable()) {
            return new IXR_Error(403, _t('没有获取评论的权限'));
        }

        return array(
            'date_created_gmt'		=> new IXR_Date($this->options->timezone + $comment->created),
            'user_id'				=> $comment->authorId,
            'comment_id'			=> $comment->coid,
            'parent'				=> $comment->parent,
            'status'				=> $this->typechoToWordpressStatus($comment->status, 'comment'),
            'content'				=> $comment->text,
            'link'					=> $comment->permalink,
            'post_id'				=> $comment->cid,
            'post_title'			=> $comment->title,
            'author'				=> $comment->author,
            'author_url'			=> $comment->url,
            'author_email'			=> $comment->mail,
            'author_ip'				=> $comment->ip,
            'type'					=> $comment->type
        );
    }

    /**
     * 获取评论列表
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array
     */
    public function wpGetComments($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $input = array();
        if (!empty($struct['status'])) {
            $input['status'] = $this->wordpressToTypechoStatus($struct['status'], 'comment');
        } else {
            $input['__typecho_all_comments'] = 'on';
        }

        if (!empty($struct['post_id'])) {
            $input['cid'] = $struct['post_id'];
        }

        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = abs(intval($struct['number']));
        }

        if (!empty($struct['offset'])) {
            $offset = abs(intval($struct['offset']));
            $input['page'] = ceil($offset / $pageSize);
        }

        $comments = $this->singletonWidget('Widget_Comments_Admin', 'pageSize=' . $pageSize, $input, false);
        $commentsStruct = array();

        while ($comments->next()) {
            $commentsStruct[] = array(
                'date_created_gmt'		=> new IXR_Date($this->options->timezone + $comments->created),
                'user_id'				=> $comments->authorId,
                'comment_id'			=> $comments->coid,
                'parent'				=> $comments->parent,
                'status'				=> $this->typechoToWordpressStatus($comments->status, 'comment'),
                'content'				=> $comments->text,
                'link'					=> $comments->permalink,
                'post_id'				=> $comments->cid,
                'post_title'			=> $comments->title,
                'author'				=> $comments->author,
                'author_url'			=> $comments->url,
                'author_email'			=> $comments->mail,
                'author_ip'				=> $comments->ip,
                'type'					=> $comments->type
            );
        }

        return $commentsStruct;
    }

    /**
     * 获取评论
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @return boolean
     */
    public function wpDeleteComment($blogId, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $commentId = abs(intval($commentId));
        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', $commentId);

        if (!$commentWidget->commentIsWriteable($where)) {
            return new IXR_Error(403, _t('无法编辑此评论'));
        }

        return intval($this->singletonWidget('Widget_Abstract_Comments')->delete($where)) > 0;
    }

    /**
     * 编辑评论
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @param array $struct
     * @return boolean
     */
    public function wpEditComment($blogId, $userName, $password, $commentId, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $commentId = abs(intval($commentId));
        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', $commentId);

        if (!$commentWidget->commentIsWriteable($where)) {
            return new IXR_Error(403, _t('无法编辑此评论'));
        }

        $input = array();

        if (isset($struct['date_created_gmt'])) {
            $input['created'] = $struct['date_created_gmt']->getTimestamp() - $this->options->timezone + $this->options->serverTimezone;
        }

        if (isset($struct['status'])) {
            $input['status'] = $this->wordpressToTypechoStatus($struct['status'], 'comment');
        }

        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }

        if (isset($struct['author'])) {
            $input['author'] = $struct['author'];
        }

        if (isset($struct['author_url'])) {
            $input['url'] = $struct['author_url'];
        }

        if (isset($struct['author_email'])) {
            $input['mail'] = $struct['author_email'];
        }

        $result = $commentWidget->update((array) $input, $where);

        if (!$result) {
            return new IXR_Error(404, _t('评论不存在'));
        }

        return true;
    }

    /**
     * 更新评论
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $path
     * @param array $struct
     * @return int
     */
    public function wpNewComment($blogId, $userName, $password, $path, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        if (is_numeric($path)) {
            $post = $this->singletonWidget('Widget_Archive', 'type=single', 'cid=' . $path, false);
        } else {
            /** 检查目标地址是否正确*/
            $pathInfo = Typecho_Common::url(substr($path, strlen($this->options->index)), '/');
            $post = Typecho_Router::match($pathInfo);
        }

        /** 这样可以得到cid或者slug*/
        if (!isset($post) || !($post instanceof Widget_Archive) || !$post->have() || !$post->is('single')) {
            return new IXR_Error(404, _t('这个目标地址不存在'));
        }

        $input = array();
        $input['permalink'] = $post->pathinfo;
        $input['type']  = 'comment';

        if (isset($struct['comment_author'])) {
            $input['author'] = $struct['author'];
        }

        if (isset($struct['comment_author_email'])) {
            $input['mail'] = $struct['author_email'];
        }

        if (isset($struct['comment_author_url'])) {
            $input['url'] = $struct['author_url'];
        }

        if (isset($struct['comment_parent'])) {
            $input['parent'] = $struct['comment_parent'];
        }

        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }

        try {
            $commentWidget =  $this->singletonWidget('Widget_Feedback', 'checkReferer=false', $input, false);
            $commentWidget->action();
            return intval($commentWidget->coid);
        } catch (Typecho_Exception $e) {
            return new IXR_Error(500, $e->getMessage());
        }

        return new IXR_Error(403, _t('无法添加评论'));
    }



    /**
     * 获取媒体文件
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param struct $struct
     * @return boolean
     */
    public function wpGetMediaLibrary($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }


        $input = array();

        if (!empty($struct['parent_id'])) {
            $input['parent'] = $struct['parent_id'];
        }

        if (!empty($struct['mime_type'])) {
            $input['mime'] = $struct['mime_type'];
        }

        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = abs(intval($struct['number']));
        }

        if (!empty($struct['offset'])) {
            $input['page'] = abs(intval($struct['offset'])) + 1;
        }

        $attachments = $this->singletonWidget('Widget_Contents_Attachment_Admin', 'pageSize=' . $pageSize, $input, false);
        $attachmentsStruct = array();

        while ($attachments->next()) {
            $attachmentsStruct[] = array(
                'attachment_id'         => $attachments->cid,
                'date_created_gmt'      => new IXR_Date($this->options->timezone + $attachments->created),
                'parent'                => $attachments->parent,
                'link'                  => $attachments->attachment->url,
                'title'                 => $attachments->title,
                'caption'               => $attachments->slug,
                'description'           => $attachments->attachment->description,
                'metadata'              => array(
                    'file'  =>  $attachments->attachment->path,
                    'size'  =>  $attachments->attachment->size,
                ),
                'thumbnail'             => $attachments->attachment->url,

            );
        }
        return $attachmentsStruct;
    }

    /**
     * 获取媒体文件
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param int $attachmentId
     * @return boolean
     */
    public function wpGetMediaItem($blogId, $userName, $password, $attachmentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }


        $attachment = $this->singletonWidget('Widget_Contents_Attachment_Edit', null, "cid={$attachmentId}");
        $struct = array(
            'attachment_id'         => $attachment->cid,
            'date_created_gmt'      => new IXR_Date($this->options->timezone + $attachment->created),
            'parent'                => $attachment->parent,
            'link'                  => $attachment->attachment->url,
            'title'                 => $attachment->title,
            'caption'               => $attachment->slug,
            'description'           => $attachment->attachment->description,
            'metadata'              => array(
                'file'  =>  $attachment->attachment->path,
                'size'  =>  $attachment->attachment->size,
            ),
            'thumbnail'             => $attachment->attachment->url,

        );
        return $struct;
    }



    /**
     * MetaWeblog API
     * about MetaWeblog API, you can see http://www.xmlrpc.com/metaWeblogApi
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $content
     * @param bool $publish
     * @access public
     * @return int
     */
    public function mwNewPost($blogId, $userName, $password, $content, $publish)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        /** 取得content内容 */
        $input = array();
        $type = isset($content['post_type']) && 'page' == $content['post_type'] ? 'page' : 'post';

        $input['title'] = trim($content['title']) == null ? _t('未命名文档') : $content['title'];

        if (isset($content['slug'])) {
            $input['slug'] = $content['slug'];
        } elseif (isset($content['wp_slug'])) {
            //fix issue 338, wlw只发送这个
            $input['slug'] = $content['wp_slug'];
        }

        $input['text'] = !empty($content['mt_text_more']) ? $content['description']
            . "\n<!--more-->\n" . $content['mt_text_more'] : $content['description'];
        $input['text'] = $this->pluginHandle()->textFilter($input['text'], $this);

        $input['password'] = isset($content["wp_password"]) ? $content["wp_password"] : null;
        $input['order'] = isset($content["wp_page_order"]) ? $content["wp_page_order"] : null;

        $input['tags'] = isset($content['mt_keywords']) ? $content['mt_keywords'] : null;
        $input['category'] = array();

        if (isset($content['postId'])) {
            $input['cid'] = $content['postId'];
        }

        if ('page' == $type && isset($content['wp_page_template'])) {
            $input['template'] = $content['wp_page_template'];
        }

        if (isset($content['dateCreated'])) {
            /** 解决客户端与服务器端时间偏移 */
            $input['created'] = $content['dateCreated']->getTimestamp() - $this->options->timezone + $this->options->serverTimezone;
        }

        if (!empty($content['categories']) && is_array($content['categories'])) {
            foreach ($content['categories'] as $category) {
                if (!$this->db->fetchRow($this->db->select('mid')
                ->from('table.metas')->where('type = ? AND name = ?', 'category', $category))) {
                    $result = $this->wpNewCategory($blogId, $userName, $password, array('name' => $category));
                    if (true !== $result) {
                        return $result;
                    }
                }

                $input['category'][] = $this->db->fetchObject($this->db->select('mid')
                ->from('table.metas')->where('type = ? AND name = ?', 'category', $category)
                ->limit(1))->mid;
            }
        }

        $input['allowComment'] = (isset($content['mt_allow_comments']) && (1 == $content['mt_allow_comments']
        || 'open' == $content['mt_allow_comments'])) ? 1 : ((isset($content['mt_allow_comments']) && (0 == $content['mt_allow_comments']
        || 'closed' == $content['mt_allow_comments'])) ? 0 : $this->options->defaultAllowComment);

        $input['allowPing'] = (isset($content['mt_allow_pings']) && (1 == $content['mt_allow_pings']
        || 'open' == $content['mt_allow_pings'])) ? 1 : ((isset($content['mt_allow_pings']) && (0 == $content['mt_allow_pings']
        || 'closed' == $content['mt_allow_pings'])) ? 0 : $this->options->defaultAllowPing);

        $input['allowFeed'] = $this->options->defaultAllowFeed;
        $input['do'] = $publish ? 'publish' : 'save';
        $input['markdown'] = $this->options->xmlrpcMarkdown;

        /** 调整状态 */
        if (isset($content["{$type}_status"])) {
            $status = $this->wordpressToTypechoStatus($content["{$type}_status"], $type);
            $input['visibility'] = isset($content["visibility"]) ? $content["visibility"] : $status;
            if ('publish' == $status || 'waiting' == $status || 'private' == $status) {
                $input['do'] = 'publish';

                if ('private' == $status) {
                    $input['private'] = 1;
                }
            } else {
                $input['do'] = 'save';
            }
        }

        /** 对未归档附件进行归档 */
        $unattached = $this->db->fetchAll($this->select()->where('table.contents.type = ? AND
        (table.contents.parent = 0 OR table.contents.parent IS NULL)', 'attachment'), array($this, 'filter'));

        if (!empty($unattached)) {
            foreach ($unattached as $attach) {
                if (false !== strpos($input['text'], $attach['attachment']->url)) {
                    if (!isset($input['attachment'])) {
                        $input['attachment'] = array();
                    }

                    $input['attachment'][] = $attach['cid'];
                }
            }
        }

        /** 调用已有组件 */
        try {
            /** 插入 */
            if ('page' == $type) {
                $this->singletonWidget('Widget_Contents_Page_Edit', null, $input, false)->action();
            } else {
                $this->singletonWidget('Widget_Contents_Post_Edit', null, $input, false)->action();
            }

            return $this->singletonWidget('Widget_Notice')->getHighlightId();
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 编辑post
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param struct $content
     * @param bool $publish
     * @access public
     * @return int
     */
    public function mwEditPost($postId, $userName, $password, $content, $publish = true)
    {
        $content['postId'] = $postId;
        return $this->mwNewPost(1, $userName, $password, $content, $publish);
    }

    /**
     * 获取指定id的post
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @access public
     * @return void
     */
    public function mwGetPost($postId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        try {
            $post = $this->singletonWidget('Widget_Contents_Post_Edit', null, "cid={$postId}");
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        /** 对文章内容做截取处理，以获得description和text_more*/
        list($excerpt, $more) = $this->getPostExtended($post);
        /** 只需要分类的name*/
        $categories = Typecho_Common::arrayFlatten($post->categories, 'name');
        $tags = Typecho_Common::arrayFlatten($post->tags, 'name');

        $postStruct = array(
                'dateCreated'   => new IXR_Date($this->options->timezone + $post->created),
                'userid'        => $post->authorId,
                'postid'       => $post->cid,
                'description'   => $excerpt,
                'title'         => $post->title,
                'link'          => $post->permalink,
                'permaLink'     => $post->permalink,
                'categories'    => $categories,
                'mt_excerpt'    => $post->description,
                'mt_text_more'  => $more,
                'mt_allow_comments' => intval($post->allowComment),
                'mt_allow_pings'    => intval($post->allowPing),
                'mt_keywords'	=> implode(', ', $tags),
                'wp_slug'       => $post->slug,
                'wp_password'   => $post->password,
                'wp_author'     => $post->author->name,
                'wp_author_id'  => $post->authorId,
                'wp_author_display_name' => $post->author->screenName,
                'date_created_gmt'  =>  new IXR_Date($post->created),
                'post_status'   => $this->typechoToWordpressStatus($post->status, 'post'),
                'custom_fields' => array(),
                'sticky'        => 0
        );

        return $postStruct;
    }

    /**
     * 获取前$postsNum个post
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postsNum
     * @access public
     * @return postStructs
     */
    public function mwGetRecentPosts($blogId, $userName, $password, $postsNum)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $posts = $this->singletonWidget('Widget_Contents_Post_Admin', "pageSize={$postsNum}", 'status=all');

        $postStructs = array();
        /** 如果这个post存在则输出，否则输出错误 */
        while ($posts->next()) {
            /** 对文章内容做截取处理，以获得description和text_more*/
            list($excerpt, $more) = $this->getPostExtended($posts);

            /** 只需要分类的name*/
            /** 可以用flatten函数处理 */
            $categories = Typecho_Common::arrayFlatten($posts->categories, 'name');
            $tags = Typecho_Common::arrayFlatten($posts->tags, 'name');

            $postStructs[] = array(
                    'dateCreated'            => new IXR_Date($this->options->timezone + $posts->created),
                    'userid'                 => $posts->authorId,
                    'postid'                 => $posts->cid,
                    'description'            => $excerpt,
                    'title'                  => $posts->title,
                    'link'                   => $posts->permalink,
                    'permaLink'              => $posts->permalink,
                    'categories'             => $categories,
                    'mt_excerpt'             => $posts->description,
                    'mt_text_more'           => $more,
                    'wp_more_text'           => $more,
                    'mt_allow_comments'      => intval($posts->allowComment),
                    'mt_allow_pings'         => intval($posts->allowPing),
                    'mt_keywords'	         => implode(', ', $tags),
                    'wp_slug'                => $posts->slug,
                    'wp_password'            => $posts->password,
                    'wp_author'              => $posts->author->name,
                    'wp_author_id'           => $posts->authorId,
                    'wp_author_display_name' => $posts->author->screenName,
                    'date_created_gmt'       => new IXR_Date($posts->created),
                    'post_status'            => $this->typechoToWordpressStatus(($posts->hasSaved || 'post_draft' == $posts->type) ? 'draft' : $posts->status, 'post'),
                    'custom_fields'          => array(),
                    'wp_post_format'         => 'standard',
                    'date_modified'          => new IXR_Date($this->options->timezone + $posts->modified),
                    'date_modified_gmt'      => new IXR_Date($posts->modified),
                    'wp_post_thumbnail'      => '',
                    'sticky'                 => 0
            );
        }

        return $postStructs;
    }

    /**
     * 获取所有的分类
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return categoryStructs
     */
    public function mwGetCategories($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return ($this->error);
        }

        $categories = $this->singletonWidget('Widget_Metas_Category_List');

        /** 初始化category数组*/
        $categoryStructs = array();
        while ($categories->next()) {
            $categoryStructs[] = array(
                'categoryId'            => $categories->mid,
                'parentId'              => $categories->parent,
                'categoryName'          => $categories->name,
                'categoryDescription'   => $categories->description,
                'description'           => $categories->name,
                'htmlUrl'               => $categories->permalink,
                'rssUrl'                => $categories->feedUrl,
            );
        }

        return $categoryStructs;
    }

    /**
     * mwNewMediaObject
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $data
     * @access public
     * @return void
     */
    public function mwNewMediaObject($blogId, $userName, $password, $data)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $result = Widget_Upload::uploadHandle($data);

        if (false === $result) {
            return IXR_Error(500, _t('上传失败'));
        } else {
            $insertId = $this->insert(array(
                'title'     =>  $result['name'],
                'slug'      =>  $result['name'],
                'type'      =>  'attachment',
                'status'    =>  'publish',
                'text'      =>  serialize($result),
                'allowComment'      =>  1,
                'allowPing'         =>  0,
                'allowFeed'         =>  1
            ));

            $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $insertId)
                    ->where('table.contents.type = ?', 'attachment'), array($this, 'push'));

            /** 增加插件接口 */
            $this->pluginHandle()->upload($this);

            return array(
                'file' => $this->attachment->name,
                'url'  => $this->attachment->url
            );
        }
    }

    /**
     * 获取 $postNum个post title
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postNum
     * @access public
     * @return postTitleStructs
     */
    public function mtGetRecentPostTitles($blogId, $userName, $password, $postsNum)
    {
        if (!$this->checkAccess($userName, $password)) {
            return ($this->error);
        }

        /** 读取数据*/
        $posts = $this->singletonWidget('Widget_Contents_Post_Admin', "pageSize=$postsNum", 'status=all');

        /**初始化*/
        $postTitleStructs = array();
        while ($posts->next()) {
            $postTitleStructs[] = array(
                'dateCreated'       => new IXR_Date($this->options->timezone + $posts->created),
                'userid'            => $posts->authorId,
                'postid'            => $posts->cid,
                'title'             => $posts->title,
                'date_created_gmt'  => new IXR_Date($this->options->timezone + $posts->created)
            );
        }

        return $postTitleStructs;
    }

    /**
     * 获取分类列表
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return categories
     */
    public function mtGetCategoryList($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return ($this->error);
        }

        $categories = $this->singletonWidget('Widget_Metas_Category_List');

        /** 初始化categorise数组*/
        $categoryStructs = array();
        while ($categories->next()) {
            $categoryStructs[] = array(
                'categoryId'   => $categories->mid,
                'categoryName' => $categories->name,
            );
        }
        return $categoryStructs;
    }

    /**
     * 获取指定post的分类
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @access public
     * @return void
     */
    public function mtGetPostCategories($postId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        try {
            $post = $this->singletonWidget('Widget_Contents_Post_Edit', null, "cid={$postId}");
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        /** 格式化categories*/
        $categories = array();
        foreach ($post->categories as $category) {
            $categories[] = array(
                'categoryName'      => $category['name'],
                'categoryId'        => $category['mid'],
                'isPrimary'         => true
            );
        }
        return $categories;
    }

    /**
     * 修改post的分类
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param string $categories
     * @access public
     * @return bool
     */
    public function mtSetPostCategories($postId, $userName, $password, $categories)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }

        try {
            $post = $this->singletonWidget('Widget_Contents_Post_Edit', null, "cid={$postId}");
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        $post->setCategories(
            $postId,
            Typecho_Common::arrayFlatten($categories, 'categoryId'),
            'publish' == $post->status
        );
        return true;
    }

    /**
     * 发布(重建)数据
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @access public
     * @return bool
     */
    public function mtPublishPost($postId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }

        /** 过滤id为$postId的post */
        $select = $this->select()->where('table.contents.cid = ? AND table.contents.type = ?', $postId, 'post')->limit(1);

        /** 提交查询 */
        $post = $this->db->fetchRow($select, array($this, 'push'));
        if ($this->authorId != $this->user->uid && !$this->checkAccess($userName, $password, 'administrator')) {
            return new IXR_Error(403, '权限不足.');
        }

        /** 暂时只做成发布*/
        $content = array();
        $this->update($content, $this->db->sql()->where('table.contents.cid = ?', $postId));
    }

    /**
     * 取得当前用户的所有blog
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return void
     */
    public function bloggerGetUsersBlogs($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $struct = array();
        $struct[] = array(
            'isAdmin'  => $this->user->pass('administrator', true),
            'url'	  => $this->options->siteUrl,
            'blogid'   => '1',
            'blogName' => $this->options->title,
            'xmlrpc'   => $this->options->xmlRpcUrl
        );

        return $struct;
    }

    /**
     * 返回当前用户的信息
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return void
     */
    public function bloggerGetUserInfo($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $struct = array(
            'nickname'  => $this->user->screenName,
            'userid'    => $this->user->uid,
            'url'       => $this->user->url,
            'email'     => $this->user->mail,
            'lastname'  => '',
            'firstname' => ''
        );

        return $struct;
    }

    /**
     * 获取当前作者的一个指定id的post的详细信息
     *
     * @param int $blogId
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @access public
     * @return void
     */
    public function bloggerGetPost($blogId, $postId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        try {
            $post = $this->singletonWidget('Widget_Contents_Post_Edit', null, "cid={$postId}");
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        $categories = Typecho_Common::arrayFlatten($post->categories, 'name');

        $content = '<title>' . $post->title . '</title>';
        $content .= '<category>' . implode(',', $categories) . '</category>';
        $content .= stripslashes($post->text);

        $struct = array(
            'userid'        => $post->authorId,
            'dateCreated'   => new IXR_Date($this->options->timezone + $post->created),
            'content'       => $content,
            'postid'        => $post->cid
        );
        return $struct;
    }

    /**
     * bloggerDeletePost
     * 删除文�
     * @param mixed $blogId
     * @param mixed $userName
     * @param mixed $password
     * @param mixed $publish
     * @access public
     * @return bool
     */
    public function bloggerDeletePost($blogId, $postId, $userName, $password, $publish)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        try {
            $this->singletonWidget('Widget_Contents_Post_Edit', null, "cid={$postId}", false)->deletePost();
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 获取当前作者前postsNum个post
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postsNum
     * @access public
     * @return void
     */
    public function bloggerGetRecentPosts($blogId, $userName, $password, $postsNum)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        //todo:限制数量
        $posts = $this->singletonWidget('Widget_Contents_Post_Admin', "pageSize=$postsNum", 'status=all');

        $postStructs = array();
        while ($posts->next()) {
            $categories = Typecho_Common::arrayFlatten($posts->categories, 'name');

            $content = '<title>' . $posts->title . '</title>';
            $content .= '<category>' . implode(',', $categories) . '</category>';
            $content .= stripslashes($posts->text);

            $struct = array(
                'userid'        => $posts->authorId,
                'dateCreated'   => new IXR_Date($this->options->timezone + $posts->created),
                'content'       => $content,
                'postid'        => $posts->cid,
            );
            $postStructs[] = $struct;
        }
        if (null == $postStructs) {
            return new IXR_Error('404', '没有任何文章');
        }
        return $postStructs;
    }

    /**
     * bloggerGetTemplate
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $template
     * @access public
     * @return void
     */
    public function bloggerGetTemplate($blogId, $userName, $password, $template)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        /** todo:暂时先返回true*/
        return true;
    }

    /**
     * bloggerSetTemplate
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $content
     * @param mixed $template
     * @access public
     * @return void
     */
    public function bloggerSetTemplate($blogId, $userName, $password, $content, $template)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        /** todo:暂时先返回true*/
        return true;
    }

    /**
     * pingbackPing
     *
     * @param string $source
     * @param string $target
     * @access public
     * @return void
     */
    public function pingbackPing($source, $target)
    {
        /** 检查目标地址是否正确*/
        $pathInfo = Typecho_Common::url(substr($target, strlen($this->options->index)), '/');
        $post = Typecho_Router::match($pathInfo);

        /** 检查源地址是否合法 */
        $params = parse_url($source);
        if (false === $params || !in_array($params['scheme'], array('http', 'https'))) {
            return new IXR_Error(16, _t('源地址服务器错误'));
        }

        if (!Typecho_Common::checkSafeHost($params['host'])) {
            return new IXR_Error(16, _t('源地址服务器错误'));
        }

        /** 这样可以得到cid或者slug*/
        if (!($post instanceof Widget_Archive) || !$post->have() || !$post->is('single')) {
            return new IXR_Error(33, _t('这个目标地址不存在'));
        }

        if ($post) {
            /** 检查是否可以ping*/
            if ($post->allowPing) {

                /** 现在可以ping了，但是还得检查下这个pingback是否已经存在了*/
                $pingNum = $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                ->from('table.comments')->where(
                    'table.comments.cid = ? AND table.comments.url = ? AND table.comments.type <> ?',
                    $post->cid,
                    $source,
                    'comment'
                ))->num;

                if ($pingNum <= 0) {
                    /** 检查源地址是否存在*/
                    if (!($http = Typecho_Http_Client::get())) {
                        return new IXR_Error(16, _t('源地址服务器错误'));
                    }

                    try {
                        $http->setTimeout(5)->send($source);
                        $response = $http->getResponseBody();

                        if (200 == $http->getResponseStatus()) {
                            if (!$http->getResponseHeader('x-pingback')) {
                                preg_match_all("/<link[^>]*rel=[\"']([^\"']*)[\"'][^>]*href=[\"']([^\"']*)[\"'][^>]*>/i", $response, $out);
                                if (!isset($out[1]['pingback'])) {
                                    return new IXR_Error(50, _t('源地址不支持PingBack'));
                                }
                            }
                        } else {
                            return new IXR_Error(16, _t('源地址服务器错误'));
                        }
                    } catch (Exception $e) {
                        return new IXR_Error(16, _t('源地址服务器错误'));
                    }

                    /** 现在开始插入以及邮件提示了 $response就是第一行请求时返回的数组*/
                    preg_match("/\<title\>([^<]*?)\<\/title\\>/is", $response, $matchTitle);
                    $finalTitle = Typecho_Common::removeXSS(trim(strip_tags($matchTitle[1])));

                    /** 干掉html tag，只留下<a>*/
                    $text = Typecho_Common::stripTags($response, '<a href="">');

                    /** 此处将$target quote,留着后面用*/
                    $pregLink = preg_quote($target);

                    /** 找出含有target链接的最长的一行作为$finalText*/
                    $finalText = '';
                    $lines = explode("\n", $text);

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (null != $line) {
                            if (preg_match("|<a[^>]*href=[\"']{$pregLink}[\"'][^>]*>(.*?)</a>|", $line)) {
                                if (strlen($line) > strlen($finalText)) {
                                    /** <a>也要干掉，*/
                                    $finalText = Typecho_Common::stripTags($line);
                                }
                            }
                        }
                    }

                    /** 截取一段字*/
                    if (null == trim($finalText)) {
                        return new IXR_Error('17', _t('源地址中不包括目标地址'));
                    }

                    $finalText = '[...]' . Typecho_Common::subStr($finalText, 0, 200, '') . '[...]';

                    $pingback = array(
                        'cid'       =>  $post->cid,
                        'created'   =>  $this->options->time,
                        'agent'     =>  $this->request->getAgent(),
                        'ip'        =>  $this->request->getIp(),
                        'author'    =>  Typecho_Common::subStr($finalTitle, 0, 150, '...'),
                        'url'       =>  Typecho_Common::safeUrl($source),
                        'text'      =>  $finalText,
                        'ownerId'   =>  $post->author->uid,
                        'type'      =>  'pingback',
                        'status'    =>  $this->options->commentsRequireModeration ? 'waiting' : 'approved'
                    );

                    /** 加入plugin */
                    $pingback = $this->pluginHandle()->pingback($pingback, $post);

                    /** 执行插入*/
                    $insertId = $this->singletonWidget('Widget_Abstract_Comments')->insert($pingback);

                    /** 评论完成接口 */
                    $this->pluginHandle()->finishPingback($this);

                    return $insertId;

                /** todo:发送邮件提示*/
                } else {
                    return new IXR_Error(48, _t('PingBack已经存在'));
                }
            } else {
                return IXR_Error(49, _t('目标地址禁止Ping'));
            }
        } else {
            return new IXR_Error(33, _t('这个目标地址不存在'));
        }
    }

    /**
     * 回收变量
     *
     * @access public
     * @param string $methodName 方法
     * @return void
     */
    public function hookAfterCall($methodName)
    {
        if (!empty($this->_usedWidgetNameList)) {
            foreach ($this->_usedWidgetNameList as $key => $widgetName) {
                $this->destory($widgetName);
                unset($this->_usedWidgetNameList[$key]);
            }
        }
    }

    /**
     * 入口执行方法
     *
     * @access public
     * @return void
     */
    public function action()
    {
        if (0 == $this->options->allowXmlRpc) {
            throw new Typecho_Widget_Exception(_t('请求的地址不存在'), 404);
        }

        if (isset($this->request->rsd)) {
            echo
<<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
    <service>
        <engineName>Typecho</engineName>
        <engineLink>http://www.typecho.org/</engineLink>
        <homePageLink>{$this->options->siteUrl}</homePageLink>
        <apis>
            <api name="WordPress" blogID="1" preferred="true" apiLink="{$this->options->xmlRpcUrl}" />
            <api name="Movable Type" blogID="1" preferred="false" apiLink="{$this->options->xmlRpcUrl}" />
            <api name="MetaWeblog" blogID="1" preferred="false" apiLink="{$this->options->xmlRpcUrl}" />
            <api name="Blogger" blogID="1" preferred="false" apiLink="{$this->options->xmlRpcUrl}" />
        </apis>
    </service>
</rsd>
EOF;
        } elseif (isset($this->request->wlw)) {
            echo
<<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<manifest xmlns="http://schemas.microsoft.com/wlw/manifest/weblog">
    <options>
        <supportsKeywords>Yes</supportsKeywords>
        <supportsFileUpload>Yes</supportsFileUpload>
        <supportsExtendedEntries>Yes</supportsExtendedEntries>
        <supportsCustomDate>Yes</supportsCustomDate>
        <supportsCategories>Yes</supportsCategories>

        <supportsCategoriesInline>Yes</supportsCategoriesInline>
        <supportsMultipleCategories>Yes</supportsMultipleCategories>
        <supportsHierarchicalCategories>Yes</supportsHierarchicalCategories>
        <supportsNewCategories>Yes</supportsNewCategories>
        <supportsNewCategoriesInline>Yes</supportsNewCategoriesInline>
        <supportsCommentPolicy>Yes</supportsCommentPolicy>

        <supportsPingPolicy>Yes</supportsPingPolicy>
        <supportsAuthor>Yes</supportsAuthor>
        <supportsSlug>Yes</supportsSlug>
        <supportsPassword>Yes</supportsPassword>
        <supportsExcerpt>Yes</supportsExcerpt>
        <supportsTrackbacks>Yes</supportsTrackbacks>

        <supportsPostAsDraft>Yes</supportsPostAsDraft>

        <supportsPages>Yes</supportsPages>
        <supportsPageParent>No</supportsPageParent>
        <supportsPageOrder>Yes</supportsPageOrder>
        <requiresXHTML>True</requiresXHTML>
        <supportsAutoUpdate>No</supportsAutoUpdate>

    </options>
</manifest>
EOF;
        } else {
            $api = array(
                /** WordPress API */
                'wp.getPage'                => array($this, 'wpGetPage'),
                'wp.getPages'               => array($this, 'wpGetPages'),
                'wp.newPage'                => array($this, 'wpNewPage'),
                'wp.deletePage'             => array($this, 'wpDeletePage'),
                'wp.editPage'               => array($this, 'wpEditPage'),
                'wp.getPageList'            => array($this, 'wpGetPageList'),
                'wp.getAuthors'             => array($this, 'wpGetAuthors'),
                'wp.getCategories'          => array($this, 'mwGetCategories'),
                'wp.newCategory'            => array($this, 'wpNewCategory'),
                'wp.suggestCategories'      => array($this, 'wpSuggestCategories'),
                'wp.uploadFile'             => array($this, 'mwNewMediaObject'),

                /** New Wordpress API since 2.9.2 */
                'wp.getUsersBlogs'          => array($this, 'wpGetUsersBlogs'),
                'wp.getTags'                => array($this, 'wpGetTags'),
                'wp.deleteCategory'         => array($this, 'wpDeleteCategory'),
                'wp.getCommentCount'        => array($this, 'wpGetCommentCount'),
                'wp.getPostStatusList'      => array($this, 'wpGetPostStatusList'),
                'wp.getPageStatusList'      => array($this, 'wpGetPageStatusList'),
                'wp.getPageTemplates'       => array($this, 'wpGetPageTemplates'),
                'wp.getOptions'             => array($this, 'wpGetOptions'),
                'wp.setOptions'             => array($this, 'wpSetOptions'),
                'wp.getComment'             => array($this, 'wpGetComment'),
                'wp.getComments'            => array($this, 'wpGetComments'),
                'wp.deleteComment'          => array($this, 'wpDeleteComment'),
                'wp.editComment'            => array($this, 'wpEditComment'),
                'wp.newComment'             => array($this, 'wpNewComment'),
                'wp.getCommentStatusList'   => array($this, 'wpGetCommentStatusList'),

                /** New Wordpress API after 2.9.2 */
                'wp.getProfile'             => array($this, 'wpGetProfile'),
                'wp.getPostFormats'         => array($this, 'wpGetPostFormats'),
                'wp.getMediaLibrary'        => array($this, 'wpGetMediaLibrary'),
                'wp.getMediaItem'           => array($this, 'wpGetMediaItem'),
                'wp.editPost'               => array($this, 'wpEditPost'),

                /** Blogger API */
                'blogger.getUsersBlogs'     => array($this, 'bloggerGetUsersBlogs'),
                'blogger.getUserInfo'       => array($this, 'bloggerGetUserInfo'),
                'blogger.getPost'           => array($this, 'bloggerGetPost'),
                'blogger.getRecentPosts'    => array($this, 'bloggerGetRecentPosts'),
                'blogger.getTemplate'       => array($this, 'bloggerGetTemplate'),
                'blogger.setTemplate'       => array($this, 'bloggerSetTemplate'),
                'blogger.deletePost'        => array($this, 'bloggerDeletePost'),

                /** MetaWeblog API (with MT extensions to structs) */
                'metaWeblog.newPost'        => array($this, 'mwNewPost'),
                'metaWeblog.editPost'       => array($this, 'mwEditPost'),
                'metaWeblog.getPost'        => array($this, 'mwGetPost'),
                'metaWeblog.getRecentPosts' => array($this, 'mwGetRecentPosts'),
                'metaWeblog.getCategories'  => array($this, 'mwGetCategories'),
                'metaWeblog.newMediaObject' => array($this, 'mwNewMediaObject'),

                /** MetaWeblog API aliases for Blogger API */
                'metaWeblog.deletePost'     => array($this, 'bloggerDeletePost'),
                'metaWeblog.getTemplate'    => array($this, 'bloggerGetTemplate'),
                'metaWeblog.setTemplate'    => array($this, 'bloggerSetTemplate'),
                'metaWeblog.getUsersBlogs'  => array($this, 'bloggerGetUsersBlogs'),

                /** MovableType API */
                'mt.getCategoryList'        => array($this, 'mtGetCategoryList'),
                'mt.getRecentPostTitles'    => array($this, 'mtGetRecentPostTitles'),
                'mt.getPostCategories'      => array($this, 'mtGetPostCategories'),
                'mt.setPostCategories'      => array($this, 'mtSetPostCategories'),
                'mt.publishPost'            => array($this, 'mtPublishPost'),

                /** PingBack */
                'pingback.ping'             => array($this,'pingbackPing'),
                // 'pingback.extensions.getPingbacks' => array($this,'pingbackExtensionsGetPingbacks'),

                /** hook after */
                'hook.afterCall'            => array($this, 'hookAfterCall'),
            );

            if (1 == $this->options->allowXmlRpc) {
                unset($api['pingback.ping']);
            }

            /** 直接把初始化放到这里 */
            new IXR_Server($api);
        }
    }
}
