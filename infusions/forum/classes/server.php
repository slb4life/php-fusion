<?php
/*-------------------------------------------------------+
| PHP-Fusion Content Management System
| Copyright (C) PHP-Fusion Inc
| https://www.php-fusion.co.uk/
+--------------------------------------------------------+
| Filename: Forum.php
| Author: Chan (Frederick MC Chan)
+--------------------------------------------------------+
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
+--------------------------------------------------------*/
namespace PHPFusion\Forums;

use PHPFusion\Forums\Threads\Attachment;

abstract class ForumServer {

    protected static $forum_settings = array();

    /* Forum icons */
    static private $forum_icons = array(
        'forum' => 'fa fa-folder fa-fw',
        'thread' => 'fa fa-comments-o fa-fw',
        'link' => 'fa fa-link fa-fw',
        'question' => 'fa fa-mortar-board fa-fw',
        'new' => 'fa fa-lightbulb-o fa-fw',
        'poll' => 'fa fa-pie-chart fa-fw',
        'lock' => 'fa fa-lock fa-fw',
        'image' => 'fa fa-file-picture-o fa-fw',
        'file' => 'fa fa-file-zip-o fa-fw',
        'tracked' => 'fa fa-bell-o fa-fw',
        'hot' => 'fa fa-heartbeat fa-fw',
        'sticky' => 'fa fa-thumb-tack fa-fw',
        'reads' => 'fa fa-ticket fa-fw',
    );

    /**
     * Return array of icons or all icons
     * @return array
     */
    public static function get_ForumIcons($type = '') {
        if (isset(self::$forum_icons[$type])) {
            return self::$forum_icons[$type];
        }
        return self::$forum_icons;
    }

    /**
     * Set and Modify Forum Icons
     * @param array $icons
     * @return array
     */
    public static function set_forumIcons(array $icons = array()) {
        self::$forum_icons = array(
            'forum' => !empty($icons['main']) ? $icons['main'] : 'fa fa-folder fa-fw',
            'thread' => !empty($icons['thread']) ? $icons['thread'] : 'fa fa-chat-o fa-fw',
            'link' => !empty($icons['link']) ? $icons['link'] : 'fa fa-link fa-fw',
            'question' => !empty($icons['question']) ? $icons['question'] : 'fa fa-mortar-board fa-fw',
            'new' => !empty($icons['new']) ? $icons['new'] : 'fa fa-lightbulb-o fa-fw',
            'poll' => !empty($icons['poll']) ? $icons['poll'] : 'fa fa-pie-chart fa-fw',
            'lock' => !empty($icons['lock']) ? $icons['lock'] : 'fa fa-lock fa-fw',
            'image' => !empty($icons['image']) ? $icons['image'] : 'fa fa-file-picture-o fa-fw',
            'file' => !empty($icons['file']) ? $icons['file'] : 'fa fa-file-zip-o fa-fw',
            'tracked' => !empty($icons['tracked']) ? $icons['tracked'] : 'fa fa-bell-o fa-fw',
            'hot' => !empty($icons['hot']) ? $icons['hot'] : 'fa fa-heartbeat fa-fw',
            'sticky' => !empty($icons['sticky']) ? $icons['sticky'] : 'fa fa-thumb-tack fa-fw',
            'reads' => !empty($icons['reads']) ? $icons['reads'] : 'fa fa-ticket fa-fw',
        );
    }

    /**
     * Verify Forum ID
     * @param $forum_id
     * @return bool|string
     */
    public static function verify_forum($forum_id) {
        if (isnum($forum_id)) {
            return (bool) dbcount("('forum_id')", DB_FORUMS, "forum_id='".$forum_id."' AND ".groupaccess('forum_access')." ") ? TRUE : FALSE;
        }
        return FALSE;
    }

    /**
     * Verify Thread ID
     * @param $thread_id
     * @return bool|string
     */
    public static function verify_thread($thread_id) {
        if (isnum($thread_id)) {
            return (bool) dbcount("('forum_id')", DB_FORUM_THREADS, "thread_id='".$thread_id."'") ? TRUE : FALSE;
        }
        return FALSE;
    }

    /**
     * Verify Post ID
     * @param $post_id
     * @return bool|string
     */
    public static function verify_post($post_id) {
        if (isnum($post_id)) {
            return (bool) dbcount("('post_id')", DB_FORUM_POSTS, "post_id='".$post_id."'") ? TRUE : FALSE;
        }
        return FALSE;
    }

    /**
     * Forum Settings
     * @return array
     */
    public static function get_forum_settings() {
        if (empty(self::$forum_settings)) {
            self::$forum_settings = get_settings('forum');
        }
        return (array) self::$forum_settings;
    }



    /**
     * Get Recent Topics per forum.
     * @param int $forum_id - all if 0.
     * @return mixed
     */
    public static function get_recentTopics($forum_id = 0) {

        $forum_settings = self::get_forum_settings();

        $result = dbquery("SELECT tt.*, tf.*, tp.post_id, tp.post_datestamp,
			u.user_id, u.user_name as last_user_name, u.user_status as last_user_status, u.user_avatar as last_user_avatar,
			uc.user_id AS s_user_id, uc.user_name AS author_name, uc.user_status AS author_status, uc.user_avatar AS author_avatar,
			count(v.post_id) AS vote_count
			FROM ".DB_FORUM_THREADS." tt
			INNER JOIN ".DB_FORUMS." tf ON (tt.forum_id=tf.forum_id)
			LEFT JOIN ".DB_FORUM_POSTS." tp on (tt.thread_lastpostid = tp.post_id)
			LEFT JOIN ".DB_USERS." u ON u.user_id=tt.thread_lastuser
			LEFT JOIN ".DB_USERS." uc ON uc.user_id=tt.thread_author
			LEFT JOIN ".DB_FORUM_VOTES." v ON v.thread_id = tt.thread_id AND tp.post_id = v.post_id
			".(multilang_table("FO") ? "WHERE tf.forum_language='".LANGUAGE."' AND" : "WHERE")."
			".groupaccess('tf.forum_access')." AND tt.thread_hidden='0'
			".($forum_id ? "AND forum_id='".intval($forum_id)."'" : '')."
			GROUP BY thread_id ORDER BY tt.thread_lastpost LIMIT 0, ".$forum_settings['threads_per_page']."");
        $info['rows'] = dbrows($result);
        if ($info['rows'] > 0) {
            // need to throw moderator as an object

            while ($data = dbarray($result)) {
                $data['moderators'] = self::moderator()->parse_forum_mods($data['forum_mods']);
                $info['item'][$data['thread_id']] = $data;
            }
        }
        return $info;
    }


    /**
     * Forum Breadcrumbs Generator
     * @param $forum_index
     */
    function forum_breadcrumbs($forum_index, $forum_id = "") {

        $locale = fusion_get_locale("", FORUM_LOCALE);

        if (empty($forum_id)) {
            $forum_id =  isset($_GET['forum_id']) && isnum($_GET['forum_id']) ? $_GET['forum_id'] : 0;
        }
        /* Make an infinity traverse */
        function breadcrumb_arrays($index, $id, &$crumb = false) {
            if (isset($index[get_parent($index, $id)])) {
                $_name = dbarray(dbquery("SELECT forum_id, forum_name, forum_cat, forum_branch FROM ".DB_FORUMS." WHERE forum_id='".$id."'"));
                $crumb = array('link'=>INFUSIONS."forum/index.php?viewforum&amp;forum_id=".$_name['forum_id']."&amp;parent_id=".$_name['forum_cat'], 'title'=>$_name['forum_name']);
                if (isset($index[get_parent($index, $id)])) {
                    if (get_parent($index, $id) == 0) {
                        return $crumb;
                    }
                    $crumb_1 = breadcrumb_arrays($index, get_parent($index, $id));
                    $crumb = array_merge_recursive($crumb, $crumb_1); // convert so can comply to Fusion Tab API.
                }
            }
            return $crumb;
        }
        // then we make a infinity recursive function to loop/break it out.
        $crumb = breadcrumb_arrays($forum_index, $forum_id);
        // then we sort in reverse.
        if (count($crumb['title']) > 1)  { krsort($crumb['title']); krsort($crumb['link']); }
        if (count($crumb['title']) > 1) {
            foreach($crumb['title'] as $i => $value) {
                add_breadcrumb(array('link'=>$crumb['link'][$i], 'title'=>$value));
                if ($i == count($crumb['title'])-1) {
                    add_to_title($locale['global_201'].$value);
                }
            }
        } elseif (isset($crumb['title'])) {
            add_to_title($locale['global_201'].$crumb['title']);
            add_breadcrumb(array('link'=>$crumb['link'], 'title'=>$crumb['title']));
        }
    }

    public static $moderator_instance = NULL;

    /**
     * Moderator object
     * @return object
     */
    protected function moderator() {
        if (empty(self::$moderator_instance)) {
            self::$moderator_instance = new Moderator();
        }
        return (object) self::$moderator_instance;
    }

    /**
     * Forum object
     * @return object
     */
    public static $forum_instance = NULL;

    public static function forum() {
        if (empty(self::$forum_instance)) {
            self::$forum_instance = new Forum();
            self::$forum_instance->set_ForumInfo();
        }
        return (object) self::$forum_instance;
    }

    /**
     * Thread object
     * @return object
     */
    public static $thread_instance = NULL;

    public static function thread() {
        if (empty(self::$thread_instance)) {
            self::$thread_instance = new Threads\ForumThreads();
            self::$thread_instance->set_threadInfo();
        }
        return (object) self::$thread_instance;
    }

}