<?php
/**
  Plugin Name: bbPress forum utility pack
  Description: This is a utility plugin that nifty to support the management of bbpress. However, some features are the Japanese version only.
  Version: 1.1.0
  Plugin URI: https://celtislab.net/wp_plugin_bbp_utility_pack/
  Author: enomoto@celtislab
  Author URI: https://celtislab.net/
  Requires at least: 5.4
  Tested up to: 6.5
  Requires PHP: 7.4
  License: GPLv2
  Text Domain: bbp_jp_utility
  Domain Path: /languages
-------------------------------------------------------------------------------
 */
defined( 'ABSPATH' ) || exit;

/***************************************************************************
 * plugin activation/uninstall
 **************************************************************************/
if(is_admin()){ 
    function bbp_jp_utility_activation( $network_wide ) {
        //bbp登録ユーザーを判別する為に bbp_user を使用（subscriberと同じ権限）
        $role    = get_role( 'subscriber' );
        $new_cap = $role->capabilities;
        add_role( 'bbp_user', esc_html__('bbpress user', 'bbp_jp_utility'), $new_cap );
        
        // anonymous 削除ユーザーの登録データ置き換え用の匿名ユーザー作成（ユーザーの登録抹消処理から除くため権限は subscriber とする）
        $anonymous = get_user_by('login', 'anonymous');
        if($anonymous === false){
            $password = wp_generate_password( 12, false );
            $anonymous = new stdClass;
            $anonymous->user_login = 'anonymous';
            $anonymous->display_name = esc_html__('Anonymous', 'bbp_jp_utility');
            $anonymous->user_pass = $password;
            $anonymous->role = 'subscriber';
            $anonymous_id = wp_insert_user( $anonymous );
        }
    }
    register_activation_hook( __FILE__,   'bbp_jp_utility_activation' );    
            
    function bbp_jp_utility_uninstall() {
        if ( !is_multisite()) {
            //プラグイン削除してもbbpress自体に影響しないよう（ユーザー自体は残っているので） bbp_user 権限は残しておく
        	//remove_role( 'bbp_user' );
            delete_option('bbp_jp_utility' );
        } else {
            global $wpdb;
            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
            	//remove_role( 'bbp_user' );
                delete_option('bbp_jp_utility' );
            }
            switch_to_blog( $current_blog_id );
        }
    }
    register_uninstall_hook(__FILE__, 'bbp_jp_utility_uninstall');    
}         

class Celtis_bbp_utility {
    
    static $option;

    function __construct() {
        load_plugin_textdomain('bbp_jp_utility', false, basename( dirname( __FILE__ ) ).'/languages' );        

        $default = array( 'bbp_user_role' => 'bbp_spectator', 'bbp_multi_user' => '', 'bbp_redirect' => '', 'bbp_precode' => '', 'bbp_multibyte'  => '', 'bbp_maximage' => '3', 'bbp_maxembed' => '3',  'bbp_widgetstyle' => '', 'bbp_no_login_newuser' => '3', 'bbp_no_login_user' => '370', 'bbp_unsubscribe' => '0' );
        $copt    = get_option('bbp_jp_utility');
		self::$option = wp_parse_args( (array) $copt,  $default);
        
        require_once ( __DIR__ . '/bbp-sub-utility.php' );
        $celtis_bbp_sub_utility = new Celtis_bbp_sub_utility();        
        
        add_action('admin_menu', array($this, 'option_menu')); 
        add_action('admin_init', array($this, 'action_posts'));
                
        //ログイン、登録、パスワード紛失
        add_filter('widget_display_callback', array($this, 'bbp_ajax_login_widget'), 20, 3 );
        add_filter('bbp_get_template_part',   array($this, 'bbp_form_user_template'), 10, 3 );
        add_action('wp_ajax_nopriv_custom_login',     array($this, 'ajax_custom_login'));
        add_action('wp_ajax_nopriv_custom_register',  array($this, 'ajax_custom_register'));
        add_action('wp_ajax_nopriv_custom_resetpass', array($this, 'ajax_custom_resetpass'));
        add_filter('script_loader_tag', array($this, 'bbp_script_loader_tag'), 10, 3 );
        //ログインの追加処理
        add_action('wp_login',  array($this, 'bbp_last_login'), 10, 2); 
        add_filter('bbp_allow_global_access', array($this, 'bbp_allow_global_access_filter'), 10, 1);
        add_filter('bbp_get_default_role', array($this, 'bbp_get_default_role_filter'), 10, 1 );
        //スパム対策等
        add_filter('wp_insert_post_data',  array($this, 'bbp_post_code_filter'), 10, 2 );
      	add_action('save_post', array($this, 'bbp_post_filter'), 10, 3 );
        add_filter('gettext_with_context',  array($this, 'widget_author_style'), 10, 4 );
		add_action('admin_enqueue_scripts', array( $this, 'dequeue_topic_scripts' ), 20);
		if (!is_admin() && function_exists('bbp_use_wp_editor') && bbp_use_wp_editor() ) {
            add_filter( 'mce_css', array($this, 'custom_mce_css') );                            
        }       
        //退会
        if (!empty(self::$option['bbp_unsubscribe'])){
            add_action( 'init', array($this, 'unsubscribe_init') );
        }

        //ユーザー管理画面へトピック数、返信数、登録日時、ログイン日時を追加
        add_filter( 'manage_users_columns', array( $this,'bbp_manage_users_topic' ), 12);        
        add_filter( 'manage_users_custom_column', array( $this,'bbp_manage_users_custom_topic'), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this,'bbp_manage_users_sortable_topic' ));    
        add_action( 'pre_user_query', array( $this, 'bbp_manage_users_sortable_pre_get_topic' ));    
        
        add_filter( 'manage_users_columns', array( $this,'bbp_manage_users_reply' ), 12);        
        add_filter( 'manage_users_custom_column', array( $this,'bbp_manage_users_custom_reply'), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this,'bbp_manage_users_sortable_reply' ));    
        add_action( 'pre_user_query', array( $this, 'bbp_manage_users_sortable_pre_get_reply' ));    
        
        add_filter( 'manage_users_columns', array( $this,'bbp_manage_users_registered' ), 12);        
        add_filter( 'manage_users_custom_column', array( $this,'bbp_manage_users_custom_registered'), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this,'bbp_manage_users_sortable_registered' ));
        
        add_filter( 'manage_users_columns', array( $this,'bbp_manage_users_login' ), 12);        
        add_filter( 'manage_users_custom_column', array( $this,'bbp_manage_users_custom_login'), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this,'bbp_manage_users_sortable_login' ));
        add_action( 'pre_get_users', array( $this, 'bbp_manage_users_sortable_pre_get_login' ));
        
        if(!is_admin()){
            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';            
            wp_enqueue_script( 'bbputil-js', plugins_url( "js/bbp-util{$min}.js", __FILE__ ), array(), filemtime( __DIR__ . "/js/bbp-util{$min}.js" ), true );		
            $bbputil_inline = 'const bbputil = ' . wp_json_encode( array( 
                'ajax-url'  => admin_url( 'admin-ajax.php' ),
                'forum-url' => home_url( get_option( '_bbp_root_slug', 'forums' ) . '/' ),
            ));                    
            wp_add_inline_script( 'bbputil-js', $bbputil_inline, 'before' );                    
        }
    }

    //退会処理用の初期化
    function unsubscribe_init() {
        add_action( "bbp_template_before_user_details", array($this, 'bbp_single_user_details_ob_start') );
        add_action( "bbp_template_after_user_details",  array($this, 'bbp_single_user_details_ob_get') );
        add_action( 'wp_ajax_cp_bbp_unsubscribe', array( $this, 'cp_bbp_unsubscribe' ) );
        add_action( 'deleted_user', array($this, 'bbp_multisite_deleted_user'), 10, 2 );
    }   
    
    //管理画面のトピック編集の 下書きとして保存　ボタンの消去処理を止める 
    function dequeue_topic_scripts() {
		if ( 'topic' === get_current_screen()->post_type ) {
            wp_dequeue_script('bbp-admin-topics-js');
        }
    }
    
    //TinyMCE エディターにCSSをロード
    function custom_mce_css( $mce_css ) {
        if ( ! empty( $mce_css ) ){
            $mce_css .= ',';
        }
        $mce_css .= plugins_url('bbp-tinymce.css', __FILE__);
        return $mce_css;
    }
    
    //最近のトピック/投稿のウィジェットの投稿者表示を見やすくするために div クラスでラップ
    public function widget_author_style( $translations, $text, $context, $domain ){
        if (!empty(self::$option['bbp_widgetstyle'])){            
            if($domain == 'bbpress'){
                add_filter('esc_html',  array($this, 'bbp_widget_add_html'), 10, 2 );
                //$translations : "投稿者: %1$s", $text : "by %1$s", $context : "widgets",  $domain : "bbpress"
                if($translations == '投稿者: %1$s' && $text == 'by %1$s'){
                    $translations = '<div class="widget-author-wrap">トピック作成者: %1$s</div>';
                }
                //$translations : "%2$s (%1$s) / %3$s", $text : "%1$s on %2$s %3$s", $context : "widgets",  $domain : "bbpress"
                elseif($translations == '%2$s (%1$s) / %3$s' && $text == '%1$s on %2$s %3$s'){
                    $translations = '%2$s<div class="widget-author-wrap">投稿者: %1$s</div>%3$s';
                }
                elseif($translations == '%2$s に %1$s より' && $text == '%1$s on %2$s'){
                    $translations = '%2$s<div class="widget-author-wrap">投稿者: %1$s</div>';
                }
            }
        }
        return $translations;
    }

    //bbp 2.6 esc_html が追加されて div タグが文字列化されたので対応するために追加
    public function bbp_widget_add_html( $safe_text, $text ){
        if($text === '<div class="widget-author-wrap">トピック作成者: %1$s</div>'){
            $safe_text = $text;
        } else if($text === '%2$s<div class="widget-author-wrap">投稿者: %1$s</div>%3$s'){
            $safe_text = $text;
        } else if($text === '%2$s<div class="widget-author-wrap">投稿者: %1$s</div>'){
            $safe_text = $text;
        }
        return $safe_text;
    }
    
    //ユーザー管理画面へ登録日時表示を追加
    public function bbp_manage_users_registered( $columns ) {
        $columns['registered'] = esc_html__('Registered', 'bbp_jp_utility');;
        return $columns;
    }
    public function bbp_manage_users_custom_registered( $output, $column, $user_id ) {
        if ( 'registered' === $column ) {
            $tzstring = wp_timezone_string();
            $utc = esc_html(get_userdata( $user_id )->user_registered);
            $tcobj = new DateTime($utc, new DateTimeZone('utc'));
            $tcobj->setTimeZone(new DateTimeZone($tzstring));
            $output = $tcobj->format('Y-m-d H:i:s');
        }
        return $output;
    }
    public function bbp_manage_users_sortable_registered( $columns ) {
        $columns['registered'] = array('registered', true);
        return $columns;
    }

    //ユーザー管理画面へログイン日時表示を追加
    public function bbp_manage_users_login( $columns ) {
        $columns['last_login'] = esc_html__('Last Login', 'bbp_jp_utility');;
        return $columns;
    }
    public function bbp_manage_users_custom_login( $output, $column, $user_id ) {
        if ( 'last_login' === $column ) {
            $utc = get_user_meta($user_id, 'bbp_last_login');
            if(!empty($utc[0])){
                $tzstring = wp_timezone_string();
                $tcobj = new DateTime($utc[0], new DateTimeZone('utc'));
                $tcobj->setTimeZone(new DateTimeZone($tzstring));
                $output = $tcobj->format('Y-m-d H:i:s');
            }
        }
        return $output;
    }
    public function bbp_manage_users_sortable_login( $columns ) {
        $columns['last_login'] = array('last_login', true);
        return $columns;
    }
    public function bbp_manage_users_sortable_pre_get_login( $query ) {
        if ( !empty($query) && 'last_login' === $query->get( 'orderby' ) ) {
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'meta_key', 'bbp_last_login' );
        }
    }

    //トピック数
    public function bbp_manage_users_topic( $columns ) {
        $columns['topic_count'] = esc_html__('Topic', 'bbp_jp_utility');;
        return $columns;
    }
    public function bbp_manage_users_custom_topic( $output, $column, $user_id ) {
        if ( 'topic_count' === $column ) {
            if(function_exists('bbp_get_user_topic_count_raw')){
                $output = bbp_get_user_topic_count_raw( $user_id );
            }
        }
        return $output;
    }
    public function bbp_manage_users_sortable_topic( $columns ) {
        $columns['topic_count'] = 'topic_count';
        return $columns;
    }
    public function bbp_manage_users_sortable_pre_get_topic( $query ) {
        if ( !empty($query) && 'topic_count' === $query->get( 'orderby' ) ) {
            global $wpdb;
			$where = get_posts_by_author_sql( 'topic' );
			$query->query_from .= " LEFT OUTER JOIN ( SELECT post_author, COUNT(*) as topic_count FROM $wpdb->posts $where GROUP BY post_author ) p ON ({$wpdb->users}.ID = p.post_author)";
            $query->query_where = "WHERE topic_count > 0 ";
            $order = $query->get( 'order' );
            $order = isset( $order ) ? strtoupper( $order ) : '';
            $order = ($order == 'ASC')? 'ASC' : 'DESC';
            $query->query_orderby = "ORDER BY topic_count $order ";
        }
    }

    //返信数
    public function bbp_manage_users_reply( $columns ) {
        $columns['reply_count'] = esc_html__('Reply', 'bbp_jp_utility');;
        return $columns;
    }
    public function bbp_manage_users_custom_reply( $output, $column, $user_id ) {
        if ( 'reply_count' === $column ) {
            if(function_exists('bbp_get_user_reply_count_raw')){
                $output = bbp_get_user_reply_count_raw( $user_id );
            }
        }
        return $output;
    }
    public function bbp_manage_users_sortable_reply( $columns ) {
        $columns['reply_count'] = 'reply_count';
        return $columns;
    }
    public function bbp_manage_users_sortable_pre_get_reply( $query ) {
        if ( !empty($query) && 'reply_count' === $query->get( 'orderby' ) ) {
            global $wpdb;
			$where = get_posts_by_author_sql( 'reply' );
			$query->query_from .= " LEFT OUTER JOIN ( SELECT post_author, COUNT(*) as reply_count FROM $wpdb->posts $where GROUP BY post_author ) p ON ({$wpdb->users}.ID = p.post_author)";
            $query->query_where = "WHERE reply_count > 0 ";
            $order = $query->get( 'order' );
            $order = isset( $order ) ? strtoupper( $order ) : '';
            $order = ($order == 'ASC')? 'ASC' : 'DESC';
            $query->query_orderby = "ORDER BY reply_count $order ";
        }
    }
    
    //Settings menu add
    public function option_menu() {
        $page = add_options_page( 'Forum utility pack', esc_html__('Forum utility pack', 'bbp_jp_utility'), 'manage_options', 'bbp-utility', array($this,'option_page'));
    }

	/**
	* checkbox
	*
	* @param mixed $name  - field name "options[checkbox]"
	* @param mixed $value - value false(0) / true(1) $options[checkbox]
	* @param mixed $label - label 
	*/
    static function checkbox($name, $value, $label = '') {
        return "<label><input type='checkbox' name='$name' value='1' " . checked( $value, 1, false ).  "/> $label</label>";
	}

	/**
	* dropdown list
	*
	* @param string $name - HTML field name
	* @param array  $items - array of (key => description) to display.  If description is itself an array, only the first column is used
	* @param string $selected - currently selected value
	* @param mixed  $args - arguments to modify the display
	*/
    static function dropdown($name, $items, $selected, $args = array(), $display = false) {
        $defaults = array(
            'id' => $name,
            'none' => false,
            'class' => null,
            'multiple' => false,
        );

        if (!is_array($items))
            return;

        if (empty($items))
            $items = array();

        // Items is in key => value format.  If value is itself an array, use only the 1st column
        foreach ($items as $key => &$value) {
            if (is_array($value))
                $value = array_shift($value);
        }

        extract(wp_parse_args($args, $defaults));

        // If 'none' arg provided, prepend a blank entry
        if ($none) {
            if ($none === true)
                $none = '&nbsp;';
            $items = array('' => $none) + $items;    // Note that array_merge() won't work because it renumbers indexes!
        }

        if (!$id)
            $id = $name;

        $name  = ($name) ? ' name="' . esc_html($name) . '"' : '';
        $id    = ($id)   ? ' id="'   . esc_html($id)   . '"' : '';
        $class = ($class)? ' class="'. esc_html($class). '"' : '';
        $multiple = ($multiple) ? ' multiple="multiple"' : '';

        $html  = '<select' . $name . $id . $class . $multiple  .'>';
        foreach ((array) $items as $key => $label) {
            $html .= '<option value="' . esc_html($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        if($display){
            echo wp_kses( $html, array(
                'select' => array( 'name' => true, 'id' => true, 'class' => true, 'multiple' => true ), 
                'option' => array( 'value' => true, 'selected' => true )
                ));
        } else {
            return $html;
        }
    }
    
    //Option Setting Form Display
    public function option_page() {
    ?>
    <h2>
      <?php esc_html_e('bbPress Forum nifty utility pack', 'bbp_jp_utility'); ?>
    </h2>
    <p><?php esc_html_e('This is a utility plugin that nifty to support the management of bbpress.', 'bbp_jp_utility'); ?></p>
    <form method="post" autocomplete="off">
      <?php wp_nonce_field( 'bbp-utility'); ?> 
      <table class="widefat">
        <thead>
          <tr>
            <th width='25%'><?php esc_html_e('Setting', 'bbp_jp_utility'); ?></th>
            <th width='75%'></th>
          </tr>
        </thead>
        <tbody>
          <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Auto role of bbpress user', 'bbp_jp_utility'); ?></label></th>
            <td>
                <?php $clist = array(0 => esc_html__('Invalid', 'bbp_jp_utility'),
                                  'bbp_spectator' => bbp_translate_user_role( 'Spectator' ),
                                  'bbp_participant' => bbp_translate_user_role( 'Participant' ));
                ?>
                <p><?php esc_html_e('Forum role of automatically registered bbpress user : ','bbp_jp_utility'); ?>
                <?php self::dropdown('bbp_jp_utility[bbp_user_role]', $clist, self::$option['bbp_user_role'], array(), true ) ?></p>
                <?php if(is_multisite()){
                    //マルチサイトオプション：他サイトに登録済みの bbpress ユーザーに、このサイトのフォーラム権限を自動的に与える
                    echo '<p>';
                    echo self::checkbox('bbp_jp_utility[bbp_multi_user]', self::$option['bbp_multi_user'], esc_html__('Multi-Site Option: Automatically give forum role of this site to bbpress user registered to other sites.', 'bbp_jp_utility'));
                    echo '</p>';
                } ?>
                <p class="description"><?php esc_html_e('Used in place of "Auto role" setting of bbpress plugins. Set the forum role only to bbpress user.','bbp_jp_utility'); ?></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Forums url redirect', 'bbp_jp_utility'); ?></label></th>
            <td>
                <p><?php echo self::checkbox('bbp_jp_utility[bbp_redirect]', self::$option['bbp_redirect'], esc_html__('Login from wp-login page of bbpress user is redirected to forums root page.', 'bbp_jp_utility')); ?></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Topic / Reply Filter', 'bbp_jp_utility'); ?></label></th>
            <td>
                <p><?php echo self::checkbox('bbp_jp_utility[bbp_precode]', self::$option['bbp_precode'], esc_html__('If it contains "code" tag to the post, replacing it with "pre" tag', 'bbp_jp_utility')); ?></p>
                <p><?php echo self::checkbox('bbp_jp_utility[bbp_multibyte]', self::$option['bbp_multibyte'], esc_html__('If the post does not contain Japanese treats as spam', 'bbp_jp_utility')); ?></p>
                <p><label for="bbp_maximage"><?php printf( '%s' . esc_html__(' or more image. Such a post will be treated as spam.', 'bbp_jp_utility'), '<input name="bbp_jp_utility[bbp_maximage]" type="number" step="1" min="1" id="bbp_jp_utility[bbp_maximage]" value="' . self::$option['bbp_maximage'] . '" class="small-text" />' ); ?></label></p>
                <p><label for="bbp_maxembed"><?php printf( '%s' . esc_html__(' or more embedded (YouTube / X(Twitter) etc). Such a post will be treated as spam.', 'bbp_jp_utility'), '<input name="bbp_jp_utility[bbp_maxembed]" type="number" step="1" min="1" id="bbp_jp_utility[bbp_maxembed]" value="' . self::$option['bbp_maxembed'] . '" class="small-text" />' ); ?></label></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label><?php esc_html_e('bbPress Topic / Reply Widget Style', 'bbp_jp_utility'); ?></label></th>
            <td>
                <p><?php echo self::checkbox('bbp_jp_utility[bbp_widgetstyle]', self::$option['bbp_widgetstyle'], esc_html__('To Widget of Recent Topics and Recent Replies. Mark up the author in div tag, and easy to read Japanese display.', 'bbp_jp_utility')); ?></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Not logged in account after registration', 'bbp_jp_utility'); ?></label></th>
            <td>
                <p><label for="bbp_no_login_newuser"><?php printf( '%s' . esc_html__(' days are not logged in within. Remove the accounts that are not also used once after such new registration.', 'bbp_jp_utility'), '<input name="bbp_jp_utility[bbp_no_login_newuser]" type="number" step="1" min="1" max="9" id="bbp_jp_utility[bbp_no_login_newuser]" value="' . self::$option['bbp_no_login_newuser'] . '" class="small-text" />' ); ?></label></p>
                <?php
                $users = $this->bbp_no_login_newuser( self::$option['bbp_no_login_newuser'] );
                $ucount = (is_array($users))? count($users) : 0;
                if($ucount > 0)
                    echo '<input type="hidden" name="bbp_jp_utility[bbp_del_newuser]" value="' . implode(',', $users) . '" />';
                ?>
                <p><span style="margin-right: 20px;"><?php esc_html_e('The number of that account : ', 'bbp_jp_utility'); ?><?php echo $ucount; ?></span>
                  <input id="bbp_jp_utility_delete_newuser" class="button" name="bbp_jp_utility_delete_newuser" type="submit" value="<?php esc_html_e('Delete', 'bbp_jp_utility'); ?>" />
                </p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Recently not logged in account', 'bbp_jp_utility'); ?></label></th>
            <td>
                <p><label for="bbp_no_login_user"><?php printf( '%s' . esc_html__(' days are not logged in within. Delete the account that has not been recently logged in. (User posted data is replaced with anonymous)', 'bbp_jp_utility'), '<input name="bbp_jp_utility[bbp_no_login_user]" type="number" step="10" min="10" max="990" id="bbp_jp_utility[bbp_no_login_user]" value="' . self::$option['bbp_no_login_user'] . '" class="small-text" />' ); ?></label></p>
                <?php
                $users = $this->bbp_no_login_user( self::$option['bbp_no_login_user'] );
                $ucount = (is_array($users))? count($users) : 0;
                if($ucount > 0)
                    echo '<input type="hidden" name="bbp_jp_utility[bbp_del_user]" value="' . implode(',', $users) . '" />';
                ?>
                <p><span style="margin-right: 20px;"><?php esc_html_e('The number of that account : ', 'bbp_jp_utility'); ?><?php echo $ucount; ?></span>
                  <input id="bbp_jp_utility_delete_user" class="button" name="bbp_jp_utility_delete_user" type="submit" value="<?php esc_html_e('Delete', 'bbp_jp_utility'); ?>" />
                </p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Forum unsubscribe function', 'bbp_jp_utility'); ?></label></th>
            <td>
                <?php
                   $clist = array('0' => esc_html__('Invalid', 'bbp_jp_utility'),
                                  '1' => esc_html__('To delete the account, replacing the post data to anonymous', 'bbp_jp_utility'),
                                  '2' => esc_html__('Completely delete the account and posts data', 'bbp_jp_utility'));
                ?>
                <?php self::dropdown('bbp_jp_utility[bbp_unsubscribe]', $clist, self::$option['bbp_unsubscribe'], array(), true ) ?>
                <p class="description"><?php esc_html_e('Note : Place the link for the "unsubscribe" in the forum of the user profile page. However, forum role is only to Participant or Spectator of bbpress user.','bbp_jp_utility'); ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="submit">
        <span style="margin-left: 10px;"><input type="submit" class="button-primary" name="bbp_jp_utility_save" value="<?php esc_html_e('Save Changes', 'bbp_jp_utility'); ?>" /></span>
      </p>
      <p><strong><?php esc_html_e('[Always active functions]', 'bbp_jp_utility'); ?></strong></p>
      <ol class="setting-notice">
        <li><?php esc_html_e('Added "bbpress user" (bbp_user) to user role. (same capabilities as subscriber)', 'bbp_jp_utility'); ?></li>
        <li><?php esc_html_e('Replace user register / lostpass / login form template with Ajax version.', 'bbp_jp_utility'); ?></li>
        <li><?php esc_html_e('Record login date and time of bbpress user.', 'bbp_jp_utility'); ?></li>
        <li><?php esc_html_e("bbpress user forbids access to Admin's Dashboard and Profile edit page.", 'bbp_jp_utility'); ?></li>
        <li><?php esc_html_e('Display link to forum root on Admin bar menu.', 'bbp_jp_utility'); ?></li>
        <li><?php esc_html_e('Create anonymous user for replacing posting data of unsubscribe user.', 'bbp_jp_utility'); ?></li>
        <li><?php esc_html_e('Load Japanese font designation CSS to TinyMCE editor used for posting.', 'bbp_jp_utility'); ?></li>
      </ol>
    </form>
    <?php
    }    

    //bbp utility option action request (save)
    function action_posts() {
        if (current_user_can( 'activate_plugins' )) {
            if( isset($_POST['bbp_jp_utility_save']) && isset($_POST['bbp_jp_utility']) ) {
                check_admin_referer('bbp-utility');

                self::$option['bbp_user_role'] = 0; 
                if( !empty($_POST['bbp_jp_utility']['bbp_user_role'])){
                    $allowed_role = array('bbp_participant', 'bbp_spectator');
                    $user_role = sanitize_key($_POST['bbp_jp_utility']['bbp_user_role']);
                    if (in_array($user_role, $allowed_role, true)){
                        self::$option['bbp_user_role'] = $user_role;
                    }
                }
                self::$option['bbp_multi_user']     = ( !empty($_POST['bbp_jp_utility']['bbp_multi_user'])) ? 1 : 0 ;
                self::$option['bbp_redirect']       = ( !empty($_POST['bbp_jp_utility']['bbp_redirect'])) ? 1 : 0 ;
                self::$option['bbp_precode']        = ( !empty($_POST['bbp_jp_utility']['bbp_precode'])) ? 1 : 0 ;
                self::$option['bbp_multibyte']      = ( !empty($_POST['bbp_jp_utility']['bbp_multibyte'])) ? 1 : 0 ;
                self::$option['bbp_maximage']       = ( !empty($_POST['bbp_jp_utility']['bbp_maximage']) && is_numeric($_POST['bbp_jp_utility']['bbp_maximage'])) ? intval($_POST['bbp_jp_utility']['bbp_maximage']) : 3 ;
                self::$option['bbp_maxembed']       = ( !empty($_POST['bbp_jp_utility']['bbp_maxembed']) && is_numeric($_POST['bbp_jp_utility']['bbp_maxembed'])) ? intval($_POST['bbp_jp_utility']['bbp_maxembed']) : 3 ;
                self::$option['bbp_widgetstyle']    = ( !empty($_POST['bbp_jp_utility']['bbp_widgetstyle'])) ? 1 : 0 ;
                self::$option['bbp_no_login_newuser'] = ( !empty($_POST['bbp_jp_utility']['bbp_no_login_newuser']) && is_numeric($_POST['bbp_jp_utility']['bbp_no_login_newuser'])) ? intval($_POST['bbp_jp_utility']['bbp_no_login_newuser']) : 3 ;
                self::$option['bbp_no_login_user']  = ( !empty($_POST['bbp_jp_utility']['bbp_no_login_user']) && is_numeric($_POST['bbp_jp_utility']['bbp_no_login_user'])) ? intval($_POST['bbp_jp_utility']['bbp_no_login_user']) : 370 ;
                self::$option['bbp_unsubscribe']    = ( !empty($_POST['bbp_jp_utility']['bbp_unsubscribe']) && is_numeric($_POST['bbp_jp_utility']['bbp_unsubscribe'])) ? intval($_POST['bbp_jp_utility']['bbp_unsubscribe']) : 0 ;
                
                update_option('bbp_jp_utility', self::$option );
                wp_safe_redirect(admin_url('options-general.php?page=bbp-utility'));
                exit;
            }
            elseif( isset($_POST['bbp_jp_utility_delete_newuser']) && isset($_POST['bbp_jp_utility']['bbp_del_newuser'])  ) {
                check_admin_referer('bbp-utility');
                $userid = array_map('intval', explode(',', $_POST['bbp_jp_utility']['bbp_del_newuser']));
                $users = $this->bbp_no_login_newuser( self::$option['bbp_no_login_newuser'] );
                if(!empty($users)){
                    foreach($users as $id){
                        if(in_array($id, $userid)){
                            $deleted = wp_delete_user( $id);
                        }
                    }
                }
                wp_safe_redirect(admin_url('options-general.php?page=bbp-utility'));
                exit;                    
            }
            elseif( isset($_POST['bbp_jp_utility_delete_user']) && isset($_POST['bbp_jp_utility']['bbp_del_user'])  ) {
                check_admin_referer('bbp-utility');
                $userid = array_map('intval', explode(',', $_POST['bbp_jp_utility']['bbp_del_user']));
                $users = $this->bbp_no_login_user( self::$option['bbp_no_login_user'] );
                if(!empty($users)){
                    $reassign = null;
                    $anonymous = get_user_by('login', 'anonymous');
                    if($anonymous !== false){
                        $reassign = $anonymous->ID;
                    }
                    foreach($users as $id){
                        if(in_array($id, $userid)){
                            $deleted = wp_delete_user( $id, $reassign);
                        }
                    }
                }
                wp_safe_redirect(admin_url('options-general.php?page=bbp-utility'));
                exit;                    
            }            
        }
    }
    
    //bbPress 退会用リンク
    static function bbp_single_user_unsubscribe_link() {
        $current = ( bbp_is_single_user_edit() )? 'current' : '';
        $ajax_nonce = wp_create_nonce( 'bbp-unsubscribe-user' );
        $user_id = bbp_get_displayed_user_id();

        $li_link = '<li id="bbp-single-user-unsubscribe">';
        $li_link .= '<div class="submit ' . $current . '">';
        $li_link .= '<span class="bbp-user-unsubscribe-link"><p class="hide-if-no-js"><a href="#bbp-user-wrapper" onclick="bbpUnsubscribeDialog(\'' . $ajax_nonce . '\',\'' . $user_id . '\');return false;">' . esc_html__('Unsubscribe', 'bbp_jp_utility') . '</a></p></span>';
        $li_link .= '</div>';
        $li_link .= '</li>';
        return $li_link;
    }

    static function insert_user_navigation($matches) {
        //$matches[0]  <div id="bbp-user-navigation">　内の ul タグ下のリスト全体を含むマッチデータ
        //$matches[1]  <ul>タグ下のリスト全体を含む </ul>までの記述
        $ins_content = self::bbp_single_user_unsubscribe_link();
        $content = $matches[1] . $ins_content . '</ul>';
        if(!empty($ins_content)){
            ob_start();
        ?>
<style>
    #bbp-unsubscribe-dialog .dialog-overlay{ position:fixed; left:0px; top:0px; width:100%; height:100%; background-color:rgba(0,0,0,.5); z-index:999999999;}
    #bbp-unsubscribe-dialog form { position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:66%; max-width:420px; height:auto; padding:1.5em; color:#222; background:#fff; z-index:999999999;}
    #bbp-unsubscribe-dialog .dialog-title { font-size: larger;}
    #bbp-unsubscribe-dialog .button-group { margin-top:2em; text-align:right;}
    #bbp-unsubscribe-dialog input[type="button"] { display:inline-block; text-decoration:none; font-size:1em; line-height:1.5; margin: 0 0 0 1em; padding: .5em 1em; color: #0071a1; background: #f5f5f5; border:solid 1px #0071a1; border-radius:3px; cursor:pointer;}
    #bbp-unsubscribe-dialog input[type="button"]:hover { background: #e8ffff; border-color: #016087; color: #016087;}
</style>
<div id="bbp-unsubscribe-dialog" style="display : none;">
  <div class="dialog-overlay"></div>
  <form>
    <p class="dialog-title"><strong><?php esc_html_e('Unsubscribe confirmation', 'bbp_jp_utility'); ?></strong></p>  
    <p class="dialog-content"><?php echo apply_filters( 'bbp-unsubscribe-content', esc_html__('Are you sure you want to unsubscribe from the forum.', 'bbp_jp_utility') . '<br>' . esc_html__('Unsubscribe to the account will be deleted.', 'bbp_jp_utility' )); ?></p>
    <div class="button-group">
        <input type="button" value="<?php esc_html_e('Yes', 'bbp_jp_utility'); ?>" onclick="bbpUnsubscribe(); return;" />
        <input type="button" value="<?php esc_html_e('No', 'bbp_jp_utility'); ?>" onclick="bbpUnsubscribeDialogClose();  return;" />
    </div>                  
  </form>
</div>
        <?php
            $addacript = ob_get_clean();
            $content .= $addacript; 
        }
        return $content;
    }
    
    public function bbp_single_user_details_ob_start() {
        ob_start();
    }
    
    //bbPress ユーザーホームに退会用リンクを追加
    //対象は bbp_user のみ。編集者、投稿者等のユーザーはフォーラム以外の記事もあるので基本は管理者による手作業とする
    //間違って管理者消したりすると復旧させるのが難しくなる
    public function bbp_single_user_details_ob_get() {
        $html = ob_get_clean();
        
        if ( is_user_logged_in() && bbp_is_user_home()) {  
            //登録されている本人か確認
    		$c_user_id = get_current_user_id();
            $user_id = bbp_get_displayed_user_id();
            if($c_user_id == $user_id){
                //登録フォームから自動登録されたユーザーに付与される bbp_user を対象（フォーラム権限は参加者(bbp_participant)、閲覧者(bbp_spectator)に限定）
                $user = get_userdata( $user_id );
                if ( !empty($user->roles) && in_array('bbp_user', $user->roles) && (in_array('bbp_spectator', $user->roles) || in_array('bbp_participant', $user->roles))) {
                    $html = preg_replace_callback('#(<div id="bbp-user-navigation">.+?<ul>.+)</ul>#su', "Celtis_bbp_utility::insert_user_navigation", $html);
                }
            }
        }
        echo $html;
    }

    //マルチサイト時に wp_delete_user() 関数では、対象サイトの _capability, _user_level 等の最小限のアクセス権限削除のみでアカウント自体の削除まで行わない
    //他のサイトの _capabilities が存在しない場合は _users, _signups テーブルからアカウントも同時に削除するようにする                            
    public function bbp_multisite_deleted_user($id, $reassign) {
        if ( is_multisite()) {
            global $wpdb;
            $del_user = true;
            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
        		$caps = get_user_meta( $id, $wpdb->prefix . 'capabilities');
                if(!empty($caps)){
                    $del_user = false;
                    break;
                }
            }
            switch_to_blog( $current_blog_id );
            if($del_user){
                $user = get_userdata( $id );
                if(!empty($user->user_login)){
                    $wpdb->delete( $wpdb->signups, array( 'user_login' => $user->user_login ) );

                    $meta = $wpdb->get_col( $wpdb->prepare( "SELECT umeta_id FROM $wpdb->usermeta WHERE user_id = %d", $id ) );
                    foreach ( $meta as $mid ){
                        delete_metadata_by_mid( 'user', $mid );
                    }
                    $wpdb->delete( $wpdb->users, array( 'ID' => $id ) );
                }
            }
        }        
        return;
    }
    
    //Ajax : wp_ajax_cp_bbp_unsubsribe
    public function cp_bbp_unsubscribe() {
        check_ajax_referer( 'bbp-unsubscribe-user' );
        
        $user_id = (!empty($_POST['user_id']) && is_numeric($_POST['user_id']))? intval($_POST['user_id']) : 0;
        
        if(!empty(self::$option['bbp_unsubscribe']) && $user_id > 0 && function_exists('bbp_get_user_id')){
            require_once(ABSPATH.'wp-admin/includes/user.php' );    
        
            $errflg = false;
            $user_id = bbp_get_user_id( $user_id );
            $user    = get_userdata( $user_id );
            if ( ! empty( $user->roles ) ) {
                if(in_array('bbp_user', $user->roles) && (in_array('bbp_spectator', $user->roles) || in_array('bbp_participant', $user->roles))){
                    //アカウント削除（オプションで anonymous に付け替えしていたら指定する ）
                    $reassign = null;
                    if (self::$option['bbp_unsubscribe'] == 1){
                        $anonymous = get_user_by('login', 'anonymous');
                        if($anonymous === false){
                            $errflg = true;
                        } else {
                            $reassign = $anonymous->ID;
                        }
                    }
                    if(!$errflg){
                        // アカウントと関連情報を削除
                        $deleted = wp_delete_user( $user_id, $reassign);
                        if($deleted){
                            wp_logout();
                            //フォーラムの退会手続きが完了しました
                            ob_end_clean(); //JS に json データを出力する前に念の為バッファクリア                            
                            wp_send_json_success( esc_html__('Unsubscribe procedure of the forum has been completed.','bbp_jp_utility'));
                        }
                    }   
                }
            }            
        }
        //フォーラムの退会手続きが出来ませんでした。サイトへお問い合わせください。
        ob_end_clean();
        wp_send_json_error( esc_html__('Unsubscribe procedure of the forum was not able to. Please contact us to the site.', 'bbp_jp_utility'));
    }

    //bbpress topic/reply <pre><code> --- </code></pre> を <pre> --- </pre> に再フォーマット
	public function bbp_post_code_filter($data, $postarr) {
        if (!empty(self::$option['bbp_precode'])){
            if ($data['post_type'] == 'topic' || $data['post_type'] == 'reply'){
                $content = $data['post_content'];
                $content = str_replace( "<pre><code>", "<pre>", $content );
                $content = str_replace( "</code></pre>", "</pre>", $content );
                $data['post_content'] = $content;
            }
        }
        return $data;
    }
    
    //投稿のスパムフィルター（日本語、画像枚数、埋め込み数）
	public function bbp_post_filter($post_ID, $post, $update) {

    	if (empty($post) || ($post->post_type != 'topic' && $post->post_type != 'reply')){
        	return;
        }
        remove_action( "save_post", array($this, 'bbp_post_filter'), 10, 3 );

        if($post->post_status == 'publish'){
            $spam = false;
            if(! current_user_can( 'moderate' ) ){
                if (!empty(self::$option['bbp_multibyte'])){
                    mb_regex_encoding("UTF-8");
                    if ( ! preg_match("/[ぁ-んァ-ヶー一-龠]+/u", $post->post_content)) {
                        $spam = true;
                    }
                }
                if (!$spam && !empty(self::$option['bbp_maximage'])){
                    if( preg_match_all('/<img.+?src.*?>/ui', $post->post_content, $matches)){
                        if(!empty($matches) && count( $matches[0] ) >= (int)self::$option['bbp_maximage']) {
                            $spam = true;
                        }
                    }
                }
                if (!$spam && !empty(self::$option['bbp_maxembed'])){
                    if( preg_match_all('#^\s*(https?://[^\s<>"]+)\s*$#uim', $post->post_content, $matches)){
                        if(!empty($matches) && count( $matches[1] ) >= (int)self::$option['bbp_maxembed']) {
                            $spam = true;
                        }
                    }
                }
                $spam = apply_filters( 'custom_bbp_spam_filter' , $spam );
            }
            if($spam){
                if ($post->post_type == 'topic' && function_exists('bbp_spam_topic')){
                    bbp_spam_topic( $post_ID );
                    add_filter('bbp_new_topic_redirect_to', array($this, 'bbp_new_topic_redirect_spam'), 10, 3);
                }
                elseif ($post->post_type == 'reply' && function_exists('bbp_spam_reply')){
                    bbp_spam_reply( $post_ID );
                }
                //wp_die();    //他のフィルターフックでメタデータの更新等も必要なのでここで終了することは不可
            }
        }
    }

    //トピックがスパムの場合のリダイレクト先をトピックからフォーラムへ変更
	public function bbp_new_topic_redirect_spam($redirect_url, $redirect_to, $topic_id) {
    	if ( !empty( $topic_id ) ) {
        	$topic = bbp_get_topic( $topic_id );
            if ( !empty( $topic ) && $topic->post_status == 'spam'){
                $redirect_url = bbp_get_forum_permalink( $topic->post_parent );
            }
        }
        return $redirect_url;
    }   

    //bbp_user に限定してログイン時の日時を保存する
    //登録日時は wp_users table に user_registered で登録されているが、前回のログイン日時は wp_usermeta テーブルに bbp_last_login として保存
	public function bbp_last_login($user_login, $user) {
        if ( !empty($user) && !empty( $user->roles ) && in_array('bbp_user', $user->roles)) {
    		update_user_meta( $user->ID, 'bbp_last_login', gmdate( 'Y-m-d H:i:s' ) );
        }
    }
    
    //bbp_user に新規登録後、一度もログインしていないユーザー
	public function bbp_no_login_newuser( $beforedays ) {
        $now = new DateTime("now", new DateTimeZone('utc'));
        $ckdate = $now->modify("-{$beforedays} day");
        $beforegmdate = $ckdate->format('Y-m-d');

        global $wpdb;
        //SELECT user_id, max(case when meta_key = 'bbp_last_login' THEN meta_value END) AS last_login, max(case when meta_key = 'wp_capabilities' THEN meta_value END) AS role FROM wp_usermeta GROUP BY user_id HAVING role LIKE '%%bbp_user%%' AND last_login IS NULL AND user_id IN (SELECT ID FROM wp_users AS us WHERE us.user_registered < '2016-10-14' )
        $sql = $wpdb->prepare( "SELECT user_id, max(case when meta_key = 'bbp_last_login' THEN meta_value END) AS last_login, max(case when meta_key = '{$wpdb->prefix}capabilities' THEN meta_value END) AS role FROM  $wpdb->usermeta GROUP BY user_id HAVING role LIKE '%%bbp_user%%' AND last_login IS NULL AND user_id IN (SELECT ID FROM  $wpdb->users AS us WHERE us.user_registered < %s )", $beforegmdate );
        $users = $wpdb->get_results( $sql, ARRAY_A);
        $userid = array();
        if(!empty($users)){
            $userid = array();
            foreach($users as $key => $val){
                //投稿データがないことを再確認
        		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $val['user_id'] ) );
                if(empty($post_ids)){
                    $userid[] = $val['user_id'];
                }
            }
        }
        return $userid;
    }
    
    //bbp_user 最後のログインから一定期間ログインのないユーザー
	public function bbp_no_login_user($beforedays) {
        $now = new DateTime("now", new DateTimeZone('utc'));
        $ckdate = $now->modify("-{$beforedays} day");
        $beforegmdate = $ckdate->format('Y-m-d');

        global $wpdb;
        //SELECT * FROM wp_users AS us INNER JOIN wp_usermeta AS mt ON ( us.ID = mt.user_id ) WHERE ( mt.meta_key = 'bbp_last_login' AND mt.meta_value < '21016-10-13' ) AND user_id IN ( SELECT user_id FROM wp_usermeta AS mt WHERE (mt.meta_key = 'wp_capabilities' AND mt.meta_value LIKE '%%bbp_user%%' ))
        $sql = $wpdb->prepare( "SELECT ID FROM $wpdb->users AS us INNER JOIN $wpdb->usermeta AS mt ON ( us.ID = mt.user_id ) WHERE ( mt.meta_key = 'bbp_last_login' AND mt.meta_value < %s ) AND user_id IN ( SELECT user_id FROM $wpdb->usermeta AS mt WHERE (mt.meta_key = '{$wpdb->prefix}capabilities' AND mt.meta_value LIKE '%%bbp_user%%' ))", $beforegmdate );
        $users = $wpdb->get_results( $sql, ARRAY_A);
        $userid = array();
        if(!empty($users)){
            $userid = array();
            foreach($users as $key => $val){
                $userid[] = $val['ID'];
            }
        }
        return $userid;
    }
    
    //form-user-register, form-user-lost-pass テンプレートを置き換えて登録画面にメッセージ領域を設け Ajax に対応させる
    public function bbp_form_user_template($templates, $slug, $name) {
        $is_bbpajax = false;
        if($slug == 'form' && $name == 'user-register'){
            add_filter( 'bbp_get_template_stack', array($this, 'add_template_stack'), 10, 1  );
            $templates = 'form-custom-signup.php';
            $is_bbpajax = true;
        }
        elseif($slug == 'form' && $name == 'user-lost-pass'){
            add_filter( 'bbp_get_template_stack', array($this, 'add_template_stack'), 10, 1  );
            $templates = 'form-custom-lost-pass.php';
            $is_bbpajax = true;
        }
        elseif($slug == 'form' && $name == 'user-login'){
            add_filter( 'bbp_get_template_stack', array($this, 'add_template_stack'), 10, 1  );
            $templates = 'form-custom-user-login.php';
            $is_bbpajax = true;
        }
        if($is_bbpajax && did_action( 'bbp_form_user_template' ) === 0){
            //custom template for ajax script
            do_action( 'bbp_form_user_template', $templates, $slug, $name );
        }
        return $templates;
    }
    
    public function add_template_stack($stack) {
        $template = __DIR__ . '/templates';
        if(!in_array($template, $stack)){
            $stack[] = $template;
        }
        return $stack;
    }
    
    //wp_ajax_custom_register : テンプレート form-custom-signup.php からのユーザー登録情報の処理
    public function ajax_custom_register() {
        if ( isset($_POST['user_login']) && isset($_POST['user_email'])) {
            if ( is_user_logged_in() ){
                wp_die( -1 );
            }
            check_ajax_referer( "bbp-user-register" );
            
            $data = array();
            $html = '';
           	if ( is_multisite() ) {
                $result = wpmu_validate_user_signup($_POST['user_login'], $_POST['user_email']);
                $signup_user_defaults = array(
                    'user_name'  => $result['user_name'],
                    'user_email' => $result['user_email'],
                    'errors'     => $result['errors'],
                );
                $filtered_results = apply_filters( 'signup_user_init', $signup_user_defaults );
                $user_name = $filtered_results['user_name'];
                $user_email = $filtered_results['user_email'];
                $errors = $filtered_results['errors'];
                
                if ( is_wp_error( $errors ) ) {
                    $data['result'] = 'e_register';
                    //エラー内容の出力
                    $html .= '<div class="bbp-template-notice error">';
                    if ( $errmsg = $errors->get_error_message('user_name') ) {
                        $html .= '<p class="error">'.$errmsg.'</p>';
                    }
                    if ( $errmsg = $errors->get_error_message('user_email') ) {
                        $html .= '<p class="error">'.$errmsg.'</p>';
                    }
                    if ( $errmsg = $errors->get_error_message('generic') ) {
                        $html .= '<p class="error">' . $errmsg . '</p>';
                    }
                    $html .= '</div>';
                } else {
                    $data['result'] = 'register';
                    //ブログ番号とロール権限をセット
                    $meta[ 'add_to_blog' ] = get_current_blog_id();
                    $meta[ 'new_role' ] = 'bbp_user';
                    wpmu_signup_user( $user_name, $user_email, $meta );

                    //登録後にメールが届くのでリンクをクリックする必要があることをメッセージとして表示
                    //"ユーザーを有効化するには、以下のリンクをクリックしてください:
                    //有効化すると、ログイン情報を含むメールが別途届きます。"                    
                    $html .= '<div class="bbp-template-notice info">';
                    /** wp-signup.php confirm_user_signup */
                    ob_start();
                    confirm_user_signup( $user_name, $user_email );
                    $html .= ob_get_clean();
                    $html .= '</div>';

                    //受け取った登録確認メールのリンクをクリックするとユーザー登録が実行され。下記のようなメールが届きます
                    //"こんにちは、xxxxxxxx さん。
                    //新しいアカウントを作成しました。
                    //以下の情報を使ってログインできます。
                    //ユーザー名: xxxxxxxx
                    //パスワード: xxxxxxxxxxxx
                    //http://[site name]/wp-login.php
                    //どうもありがとうございます !
                    //-- [site name] サイト 運営チーム"  
                    //  
                    //また、ブラウザ上にもユーザー名とパスワード、ログイン用のリンクが表示されます
                }                
            } else {
        		$errors = register_new_user($_POST['user_login'], $_POST['user_email']);
                if ( is_wp_error( $errors ) ) {
                    $err = $errors->get_error_code();
                    $data['result'] = 'e_register';
                    //エラー内容の出力
                	$shake_error_codes = array( 'empty_password', 'empty_email', 'invalid_email', 'invalidcombo', 'empty_username', 'invalid_username', 'incorrect_password', 'username_exists', 'email_exists', 'registerfail' );
                	$shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );
                    $html .= '<div class="bbp-template-notice error">';
                    foreach ($shake_error_codes as $msg) {
                        if ( $errmsg = $errors->get_error_message($msg) ) {
                            $html .= '<p class="error">'.$errmsg.'</p>';
                        }
                    }
                    if(!in_array( $err, $shake_error_codes)){
                        //shake_error_codes に含まれていないカスタムエラー取得
                        $errmsg = $errors->get_error_message($err);
                        if(empty($errmsg))
                            $errmsg = esc_html($err);
                        $html .= '<p class="error">'.$errmsg.'</p>';
                    } 
                    $html .= '</div>';                    
                } else {
                    //bbpress 登録フォームからの新規登録アカウントの権限を bbp_user （subscriberと同じ権限）に設定する
                    $user_id = $errors;
                    $user = get_userdata( $user_id );
                    if ( !empty( $user ) ) {
                        $user->set_role( 'bbp_user' );   //一般設定のデフォルト権限グループから変更
                    }
                    $data['result'] = 'register';
                    $html .= '<div class="bbp-template-notice info">';
                    $html .= '<p><strong>' . sprintf( '%s' . esc_html__( ' is your new username','bbp_jp_utility' ), $user->user_login) . '</strong></p>';
                    $html .= '<p>'. esc_html__('Registration complete. Please check your email.','bbp_jp_utility') . '</p>';
                    $html .= '</div>';                    
                }
            }                    
            $data['info'] = $html;
            ob_end_clean();
            wp_send_json_success($data);
        }
        wp_die( 0 );
    }

    //user.php retrieve_password() と同等処理
    public function retrieve_password() {
        return retrieve_password();
    }
    
    //wp_ajax_custom_resetpass : テンプレート form-custom-lost-pass.php からのパスワードリセットの処理
    public function ajax_custom_resetpass() {
        if ( isset($_POST['user_login'])) {
            if ( is_user_logged_in() ){
                wp_die( -1 );
            }
            check_ajax_referer( "bbp-user-lost-pass" );
            
            $data = array();
            $html = '';
			$errors = $this->retrieve_password();
            if ( is_wp_error( $errors ) ) {
                $err = $errors->get_error_code();
                $data['result'] = 'e_resetpass';
                //エラー内容の出力
                $shake_error_codes = array( 'empty_password', 'empty_email', 'invalid_email', 'invalidcombo', 'empty_username', 'invalid_username', 'incorrect_password', 'no_password_reset', 'no_password_key_update', 'retrieve_password_email_failure' );
                $shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );
                $html .= '<div class="bbp-template-notice error">';
                foreach ($shake_error_codes as $msg) {
                    if ( $errmsg = $errors->get_error_message($msg) ) {
                        $html .= '<p class="error">'. apply_filters( 'login_errors', $errmsg ) .'</p>';
                    }
                }
                if(!in_array( $err, $shake_error_codes)){
                    $errmsg = $errors->get_error_message($err);
                    if(empty($errmsg))
                        $errmsg = esc_html($err);
                    $html .= '<p class="error">'. apply_filters( 'login_errors', $errmsg ) .'</p>';
                }                
                $html .= '</div>';
            } else {
                $data['result'] = 'resetpass';
                $html .= '<div class="bbp-template-notice info">';
                $html .= '<p>' . esc_html__('Check your email for the confirmation link.','bbp_jp_utility') . '</p>';
                $html .= '</div>';
            }

            $data['info'] = $html;
            ob_end_clean();
            wp_send_json_success($data);
        }
        wp_die( 0 );
    }

    //wp_ajax_custom_login : テンプレート form-custom-user-login.php からのユーザーログイン処理
    //Ajax によりログイン処理を行うのでエラー時にページ遷移せずに結果表示を行う
    public function ajax_custom_login() {
        check_ajax_referer( "bbp-user-login" );
        
        if(!function_exists('login_header')){ // not wp-login page for invisible-recaptcha plugin
            function login_header() {
                do_action( 'login_enqueue_scripts' );
            }   
    		login_header();
        }

        $secure_cookie = '';
        // If the user wants ssl but the session is not ssl, force a secure cookie.
        if ( !empty($_POST['log']) && !force_ssl_admin() ) {
			$user_name = sanitize_user( wp_unslash( $_POST['log'] ) );
            $user = get_user_by( 'login', $user_name );

            if ( ! $user && strpos( $user_name, '@' ) ) {
                $user = get_user_by( 'email', $user_name );
            }

            if ( $user ) {
                if ( get_user_option('use_ssl', $user->ID) ) {
                    $secure_cookie = true;
                    force_ssl_admin(true);
                }
            }
        }
        
        if ( isset( $_REQUEST['redirect_to'] ) ) {
            $redirect_to = esc_url($_REQUEST['redirect_to']);
            // Redirect to https if user wants ssl
            if ( $secure_cookie && false !== strpos($redirect_to, 'wp-admin') ){
                $redirect_to = preg_replace('|^http://|', 'https://', $redirect_to);
            }
        } else {
			$redirect_to = admin_url();
        }

        $reauth = empty($_REQUEST['reauth']) ? false : true;

        $user = wp_signon( array(), $secure_cookie );

		if ( empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			if ( headers_sent() ) {
				$user = new WP_Error(
					'test_cookie',
					sprintf(
						/* translators: 1: Browser cookie documentation URL, 2: Support forums URL. */
						__( '<strong>Error:</strong> Cookies are blocked due to unexpected output. For help, please see <a href="%1$s">this documentation</a> or try the <a href="%2$s">support forums</a>.' ),
						__( 'https://wordpress.org/documentation/article/cookies/' ),
						__( 'https://wordpress.org/support/forums/' )
					)
				);
			} elseif ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[ TEST_COOKIE ] ) ) {
				// If cookies are disabled, the user can't log in even with a valid username and password.
				$user = new WP_Error(
					'test_cookie',
					sprintf(
						/* translators: %s: Browser cookie documentation URL. */
						__( '<strong>Error:</strong> Cookies are blocked or not supported by your browser. You must <a href="%s">enable cookies</a> to use WordPress.' ),
						__( 'https://wordpress.org/documentation/article/cookies/#enable-cookies-in-your-browser' )
					)
				);
			}
		}

        $requested_redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url($_REQUEST['redirect_to']) : '';
        $redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );

        // ログイン中ならエラークリア
        if ( is_user_logged_in() ){
            $user = new WP_Error();
        }

        $data = array();
        $html = '';
        if ( !is_wp_error($user) && !$reauth ) {
            if ( ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) ) {
                $copt = get_option('bbp_jp_utility');
                $redirect_to = (!empty($copt['bbp_redirect']))? home_url( get_option( '_bbp_root_slug', 'forums' ) . '/' ) : home_url();
            }
            $data['result'] = 'login_redirect';
            $html = $redirect_to; 
            
        } else {
            $errors = $user;
            $errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );

            // Clear any stale cookies.
            if ( $reauth ){
                wp_clear_auth_cookie();
            }
            if ( is_wp_error( $errors ) ) {
                $err = $errors->get_error_code();
                $data['result'] = 'e_login';
                $shake_error_codes = array( 'empty_password', 'empty_email', 'invalid_email', 'invalidcombo', 'empty_username', 'invalid_username', 'incorrect_password' );
                $shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );
                
                $html .= '<div class="bbp-template-notice error">';
                foreach ($shake_error_codes as $code) {
                    if ( $errmsg = $errors->get_error_message($code) ) {
                        $html .= '<p class="error">'. apply_filters( 'login_errors', $errmsg ) .'</p>';
                    }
                }
                if(!in_array( $err, $shake_error_codes)){
                    //shake_error_codes に含まれていないカスタムエラー取得
                    $errmsg = $errors->get_error_message($err);
                    if(empty($errmsg)){
                        $errmsg = esc_html($err);
                    }
                    $html .= '<p class="error">'. apply_filters( 'login_errors', $errmsg ) .'</p>';
                }
                $html .= '</div>';
            }
        }
        $data['info'] = $html;
        ob_end_clean();
        wp_send_json_success($data);
    }

    //dynamic_sidebar 内からの実行であるかバックトレースから判定する
    public static function in_dynamic_sidebar()
    {
        $in_flag = false;
        $trace = debug_backtrace();
        foreach ($trace as $stp) {
            if(isset($stp['function'])){
                if($stp['function'] === "dynamic_sidebar"){
                    $in_flag = true;
                    break;
                }
            }
        }
        return $in_flag;
    }

    /**
     * Filters the HTML script tag of an enqueued script.
     *
     * @param string $tag    The `<script>` tag for the enqueued script.
     * @param string $handle The script's registered handle.
     * @param string $src    The script's source URL.
     */
    //invisble-recaptcha スクリプトの callbackに bbp-ajax-submit 用 POST処理を挿入する
    public function bbp_script_loader_tag( $tag, $handle, $src ) {
		if('google-invisible-recaptcha' === $handle){
            $skey = 'HTMLFormElement.prototype.submit.call(frm);';
            $inscode = "if(null !==  frm.querySelector('.bbp-ajax-submit')){ BBPAjax_submit(BBPAjax_extparm()); return;}";
            $tag = str_replace( $skey, $inscode . PHP_EOL . $skey, $tag );
        }
		return $tag;
    }
    
    //bbp-login-widget のHTML出力を Ajax 対応版に置き換える
    public function bbp_ajax_login_widget($instance, $widget, $args) {
        if(!empty($instance) && $widget->id_base === 'bbp_login_widget'){
    		global $wp_customize;
            global $bbp_register_link_url;
            global $bbp_lostpass_link_url;
        	if(! ( isset( $wp_customize ) && $wp_customize->is_preview() )) {
            
                // Get widget settings
                //$settings = $this->parse_settings( $instance );
                $settings = bbp_parse_args( $instance, array( 'title' => '', 'register' => '', 'lostpass' => ''), 'login_widget_settings' );

                // Typical WordPress filter
                $settings['title'] = apply_filters( 'widget_title', $settings['title'], $instance, $widget->id_base );

                // bbPress filters
                $settings['title']     = apply_filters( 'bbp_login_widget_title',    $settings['title'],    $instance, $widget->id_base );
                $bbp_register_link_url = apply_filters( 'bbp_login_widget_register', $settings['register'], $instance, $widget->id_base );
                $bbp_lostpass_link_url = apply_filters( 'bbp_login_widget_lostpass', $settings['lostpass'], $instance, $widget->id_base );

                echo $args['before_widget'];

                if ( !empty( $settings['title'] ) ) {
                    echo $args['before_title'] . $settings['title'] . $args['after_title'];
                }

                if ( !is_user_logged_in() ) {
                    bbp_get_template_part( 'form', 'user-login' );  //hook -> form-custom-user-login            
                } else { ?>
                    <div class="bbp-logged-in">
                        <a href="<?php bbp_user_profile_url( bbp_get_current_user_id() ); ?>" class="submit user-submit"><?php echo get_avatar( bbp_get_current_user_id(), '40' ); ?></a>
                        <h4><?php bbp_user_profile_link( bbp_get_current_user_id() ); ?></h4>

                        <?php bbp_logout_link(); ?>
                    </div>
                <?php }

                echo $args['after_widget'];
            
                $instance = false;
            }
        }
        return $instance;
    }    
    
    //自動権限グループ	bbp_user なら強制的に自動権限の付与を許可
	public function bbp_allow_global_access_filter($enable) {
        if(!empty(self::$option['bbp_user_role'])){
        	$user = wp_get_current_user();  //current site
            if(empty( $user->roles )){
                if ( is_multisite()) {
                    if(!empty(self::$option['bbp_multi_user'])){
                        $primary_blog = get_user_meta( $user->ID, 'primary_blog', true );
                        if(!empty($primary_blog)){
                            $current_blog_id = get_current_blog_id();
                            $blog_id = (int)$primary_blog;
                            $user->for_site($blog_id);  //capabilities, role get
                            if (in_array('bbp_user', $user->roles)) {
                                $enable = true;
                            }
                            $user->for_site($current_blog_id);

                            if($enable)
                                $user->set_role( 'bbp_user' );
                        }
                    }
                }
            } elseif (in_array('bbp_user', $user->roles)) {
                $enable = true;
            }
        }
        return $enable;
    }
    //自動権限グループ	bbp_user なら bbp-jp-utility 設定の権限を付与
	public function bbp_get_default_role_filter($bbp_role) {
        if(!empty(self::$option['bbp_user_role'])){
            $user = wp_get_current_user();  //current site
            if ( ! empty( $user->roles ) && in_array('bbp_user', $user->roles)) {
                $bbp_role = self::$option['bbp_user_role'];
            }
        }
        return $bbp_role;
    }
    
	public static function init() {
        $celtis_bbp_utility = new Celtis_bbp_utility();
    }
}
add_action( 'init', array( 'Celtis_bbp_utility', 'init' ), 1 );
