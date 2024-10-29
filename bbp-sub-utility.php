<?php
/*
  Module : bbPress forum utility pack sub module
  Description: This is a utility plugin that nifty to support the management of bbpress.
  Version: 1.1.0
  Author: enomoto@celtislab
  License: GPLv2
*/
defined( 'ABSPATH' ) || exit;

class Celtis_bbp_sub_utility {
    
    function __construct() {
        add_filter('login_redirect', array($this, 'bbp_login_redirect'), 10, 3 );
        add_action('admin_menu', array($this, 'remove_menu')); 
        add_action('admin_bar_menu', array($this,'custom_bar_menus'), 201);
        add_filter('edit_profile_url', array($this, 'bbp_profile_url'), 10, 3);
        add_filter('user_dashboard_url', array($this, 'bbp_no_profile_url'), 10, 4);
        add_filter('map_meta_cap', array($this,'invalid_read'), 10, 4 );            
    }

    function bbp_user_role( $roles ) {
        if ( ! empty( $roles )) {
            foreach(array('bbp_keymaster', 'bbp_moderator', 'bbp_participant', 'bbp_spectator', 'bbp_blocked') as $v){
                if(in_array($v, $roles))
                    return $v;
            }
        }
        return false;
    }
    
    // bbp_user login to forum root url redirect 
    function bbp_login_redirect($redirect_to, $requested_redirect_to, $user){
        $blog_id = '';
        if(!empty($user->data->ID) && empty( $user->roles )){
            if ( is_multisite()) {
                $primary_blog = get_user_meta( $user->data->ID, 'primary_blog', true );
                if(!empty($primary_blog)){
                    $blog_id = (int)$primary_blog;
                }
            }
            $user->for_site($blog_id);  //capabilities, role get
        }
        if ( ! empty( $user->roles ) && in_array('bbp_user', $user->roles)) {
            $role = $this->bbp_user_role($user->roles);
            if(empty($role) || $role == 'bbp_spectator' || $role == 'bbp_participant'){
                if ( is_multisite()) {
                    $current_blog_id = get_current_blog_id();
                    if($blog_id != $current_blog_id){
                        switch_to_blog( $blog_id );
                    }
                    $copt = get_option('bbp_jp_utility');
                    if(!empty($copt['bbp_redirect']) && ($redirect_to == home_url() || $redirect_to == network_home_url() || false !== strpos($redirect_to, 'wp-admin'))){
                        $redirect_to = home_url( get_option( '_bbp_root_slug', 'forums' ) . '/' );
                    }
                    if($blog_id != $current_blog_id){
                        switch_to_blog( $current_blog_id );
                    }
                } else {
                    $copt = get_option('bbp_jp_utility');
                    if( !empty($copt['bbp_redirect']) && ($redirect_to == home_url() || false !== strpos($redirect_to, 'wp-admin'))){
                        $redirect_to = home_url( get_option( '_bbp_root_slug', 'forums' ) . '/' );
                    }
                }
            }
            //bbp_user last login update
            update_user_meta( $user->data->ID, 'bbp_last_login', gmdate( 'Y-m-d H:i:s' ) );
        }            
        return $redirect_to;
    }
    
    //bbp_user admin menu bar Dashbord item remove
    public function remove_menu() {
        global $current_user;        
        if ( !empty($current_user) && (empty( $current_user->roles ) || in_array('bbp_user', $current_user->roles))) {
            $role = $this->bbp_user_role($current_user->roles);
            if(empty($role) || $role == 'bbp_spectator' || $role == 'bbp_participant'){
                remove_menu_page( 'index.php' );    //dashbord
            }
        }
    }
    public function custom_bar_menus($wp_admin_bar) {
        global $current_user;
        if ( !empty($current_user) && (empty( $current_user->roles ) || in_array('bbp_user', $current_user->roles))) {
            $frole = $this->bbp_user_role($current_user->roles);
            if(empty($frole) || $frole == 'bbp_spectator' || $frole == 'bbp_participant'){
                $wp_admin_bar->remove_menu( 'wp-logo' );
                $wp_admin_bar->remove_menu( 'site-name' );
                $wp_admin_bar->remove_menu( 'view-site' );
                $wp_admin_bar->remove_menu( 'dashboard' );
                $wp_admin_bar->remove_menu( 'search' );
                //$wp_admin_bar->remove_menu('my-account');
                //$wp_admin_bar->remove_menu('user-info');
                $wp_admin_bar->remove_menu('edit-profile');

                if ( is_multisite()) {
                    $wp_admin_bar->remove_menu( 'my-sites' );
                }                
            }
        }
        global $wp_rewrite;
        if ( !empty($wp_rewrite) && $wp_rewrite->using_permalinks() ) {
            if ( is_multisite()) {
                $wp_admin_bar->add_menu( array(
                    'id'    => 'forum-root',
                    'title' => esc_html__( 'Forum', 'bbpress' ),
                ) );

                $wp_admin_bar->add_group( array(
                    'parent' => 'forum-root',
                    'id'     => 'forum-root-list',
                ) );

                foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
                    switch_to_blog( $blog->userblog_id );

                    $siterole = $this->bbp_user_role($current_user->roles);
                    $fslug = get_option( '_bbp_root_slug' );
                    if(!empty($siterole) && !empty($fslug)){
                        $blogname = $blog->blogname;
                        if ( ! $blogname ) {
                            $blogname = preg_replace( '#^(https?://)?(www.)?#', '', get_home_url() );
                        }
                        $title = wp_html_excerpt( $blogname, 40, '&hellip;' );
                        $forum_root = home_url( trailingslashit( $fslug ) );

                    	$forum_data = $this->get_bbp_recent_data();
                        $wp_admin_bar->add_menu( array(
                            'parent'=> 'forum-root-list',
                            'id'    => 'forum-root-' . $blog->userblog_id,
                            'title' => $title,
                            'href'  => $forum_root,
                            'meta'  => array(
                                'title' => $forum_data['topic'] . $forum_data['reply'] . PHP_EOL,
                                ),
                        ) );                        
                    }
                    restore_current_blog();
                }
            } else {
                $blogname = get_bloginfo('name');
                $title = wp_html_excerpt( $blogname, 40, '&hellip;' );
                $forum_root = home_url( trailingslashit( get_option( '_bbp_root_slug', 'forums' )) );

            	$forum_data = $this->get_bbp_recent_data();
                $wp_admin_bar->add_menu( array(
                    'id'    => 'forum-root',
                    'title' => esc_html__( 'Forum', 'bbpress' ),
                    'href'  => $forum_root,
                    'meta'  => array(
                        'title' => $forum_data['topic'] . $forum_data['reply'] . PHP_EOL,
                        ),
                ) );
            }
        }            
    }

    //最近のトピックと返信をポップアップ表示
    public function get_bbp_recent_data() {
        if(!function_exists('bbp_get_public_status_id')){
            return false;
        }
        if(!function_exists('bbp_get_topic_post_type')){
            return false;
        }
        if(!function_exists('bbp_get_topic_id')){
            return false;
        }
        if(!function_exists('bbp_get_reply_post_type')){
            return false;
        }
        if(!function_exists('bbp_get_reply_id')){
            return false;
        }                    
        $recent = get_transient( 'bbp_utility_recent_data' );
        if ( empty($recent) ) {
            global $wp_query;
            global $post;
            $svpost = $post;

            $recent = array( 'topic' => '', 'reply' => '' );
            $topics = new WP_Query( array(
                'post_type'           => bbp_get_topic_post_type(),
                'post_parent'         => 'any',
                'posts_per_page'      => 3,
                'post_status'         => array( bbp_get_public_status_id() ),
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
                'order'               => 'DESC'
            ) );
            if ( $topics->have_posts() ) {
                $recent['topic'] = esc_html__( 'Recent 3 Topic', 'bbp_jp_utility' ) . PHP_EOL;
                while ( $topics->have_posts() ) {
                    $topics->the_post();
                    $topic_id = bbp_get_topic_id( $topics->post->ID );
                    $recent['topic'] .= bbp_get_topic_title( $topic_id ) . ' (' . bbp_get_topic_last_active_time( $topic_id ) . ')' . PHP_EOL;
                }
            }
            
            $replys = new WP_Query( array(
                'post_type'           => bbp_get_reply_post_type(),
                'post_status'         => array( bbp_get_public_status_id() ),
                'posts_per_page'      => 3,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ) );
            if ( $replys->have_posts() ) {
                $recent['reply'] = esc_html__( 'Recent 3 Reply', 'bbp_jp_utility' ) . PHP_EOL;
                while ( $replys->have_posts() ) {
                    $replys->the_post();
                    $reply_id   = bbp_get_reply_id( $replys->post->ID );
                    $recent['reply'] .= bbp_get_reply_topic_title( $reply_id ) . ' (' . bbp_get_time_since( get_the_time( 'U' ) ) . ')' . PHP_EOL;
                }
            }
            if ( !empty($wp_query) && !empty( $wp_query->post ) ) {
                wp_reset_postdata();
            } else {
                //編集画面の $wp_query->post が空の場合に wp_reset_postdata が機能しないので対策
                $post = $svpost;
            }
            
            set_transient( 'bbp_utility_recent_data', $recent, MINUTE_IN_SECONDS * 15 );
        }
        return $recent;
    }
    
    //site/wp-admin/profile to bbp user profile link change
    public function bbp_profile_url($url, $user_id, $scheme) {
        $user = get_userdata( $user_id );
        if ( !empty($user) && (empty($user->roles) || in_array('bbp_user', $user->roles))){
            $fslug = get_option( '_bbp_root_slug' );
            $role = $this->bbp_user_role($user->roles);
            if(!empty($fslug) && (empty($role) || $role == 'bbp_spectator' || $role == 'bbp_participant')){
                global $wp_rewrite;
        		if ( !empty($wp_rewrite) && $wp_rewrite->using_permalinks() ) {
                    $uslug = trailingslashit( $fslug ) . trailingslashit(get_option( '_bbp_user_slug', 'user' ));
                    $url = $wp_rewrite->root . $uslug . $user->data->user_nicename;
                    $url = home_url( user_trailingslashit( $url ) );
                }
            }
        }
        return $url;
    }    
    public function bbp_no_profile_url($url, $user_id, $path, $scheme) {
        if(is_multisite()){
            $user = get_userdata( $user_id );
            if ( !empty($user) && empty($user->roles)){
                $url = false;
            }
        }
        return $url;
    }
    public function invalid_read($caps, $cap, $user_id, $args) {
        if($cap === 'read'){
            if ( did_action( 'admin_menu' ) ) {
                $user = get_userdata( $user_id );
                if ( !empty($user) && isset($user->roles) && in_array('bbp_user', $user->roles)){
                    $role = $this->bbp_user_role($user->roles);
                    if((empty($role) || $role == 'bbp_spectator' || $role == 'bbp_participant')){
                        $caps[] = 'do_not_allow';
                    }
                }
            }
        }
        return $caps;
    }    
}
