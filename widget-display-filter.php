<?php
/*
  Plugin Name: Widget Display Filter
  Description: Set the display condition for each widget. Appearance -> Widget Display Filter menu added to the management page.
  Version: 1.0.0
  Plugin URI: http://celtislab.net/wp_widget_display_filter
  Author: enomoto@celtislab
  Author URI: http://celtislab.net/
  License: GPLv2
  Text Domain: wdfilter
  Domain Path: /languages
*/

if( stripos($_SERVER['REQUEST_URI'], 'widget_display_filter_manage_page' ) === false ){
    add_action( 'widgets_init', 'widget_display_filter_start', 99 );
}
else {
    add_action( 'wp_loaded', 'widget_display_filter_start' );
}

function widget_display_filter_start()
{
    $widget_display_filter = new Widget_display_filter();
}

class Widget_display_filter {
    
    public $widgets = '';
    public $editem = false;
    public $filter = array();

    public function __construct() {
        global $wp_widget_factory;

        load_plugin_textdomain('wdfilter', false, basename( dirname( __FILE__ ) ).'/languages' );

        $this->filter = get_option('widget_display_filter');
		if (!empty($this->filter['register']) && did_action( 'wp_loaded' ) == 0 ) {
            //hidden widget (There be excluded temporarily from the widget list by disabling plug-ins)
            foreach( $this->filter['register'] as $unreg_class => $unreg ) {
                foreach ( $wp_widget_factory->widgets as $widget_class => $widget ) {
                    if ( $widget_class == $unreg_class ) {
                        unregister_widget($widget_class);
                        break;
                    }
                }
            }
        }
        $this->widgets = $wp_widget_factory->widgets;
            
        add_action('admin_init', array(&$this, 'action_posts')); 
        add_action('admin_menu', array(&$this, 'my_option_menu')); 
        add_action('admin_print_styles-appearance_page_widget_display_filter_manage_page', array(&$this, 'admin_styles') );
        add_action('admin_print_scripts-appearance_page_widget_display_filter_manage_page', array(&$this, 'admin_scripts') );
            
        if ( function_exists('register_uninstall_hook') )
            register_uninstall_hook(__FILE__, 'Widget_display_filter::my_uninstall_hook');

        add_filter('widget_display_callback', array(&$this, 'widget_instance_filter'), 10, 3);
        add_action('wp_ajax_Widget_filter_postid',  array(&$this, 'widget_filter_postid'));
        add_action('wp_ajax_Widget_filter_category',  array(&$this, 'widget_filter_category'));
        add_action('wp_ajax_Widget_filter_post_tag',  array(&$this, 'widget_filter_post_tag'));
    }
    
    //Add Appearance submenu : Widget Load Filter
    public function my_option_menu()
    {
        add_theme_page( 'Widget Display Filter', __('Widget Display Filter', 'wdfilter'), 'manage_options', 'widget_display_filter_manage_page', array(&$this,'widget_display_filter_option_page'));
    }

    //Option clear when deleting plugins
    public static function my_uninstall_hook()
    {
        delete_option('widget_display_filter' );
    }

    //widget-display-filter admin page Notice message & css file 
    function admin_styles() {
        add_action( 'admin_notices', array(&$this, 'widget_filter_notice'));       
        $path = __DIR__ . '/widget-display-filter.css';
        $cssurl  = content_url() . str_replace('\\' ,'/', substr( $path, stripos($path, 'wp-content') + 10));
        wp_enqueue_style( 'widget-display-filter-css', $cssurl);
    }

    //Notice Message display
    public function widget_filter_notice() {
        $notice = get_transient('widget_display_filter_notice');
        if(!empty($notice)){
            echo "<div class='message error'><p>Widget Load Filter : $notice</p></div>";
            delete_transient('widget_display_filter_notice');
        }        
    }

    //widget-display-filter admin page js file
    function admin_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-widget' );
        wp_enqueue_script( 'jquery-ui-tabs' );
        $path = __DIR__ . '/widget-display-filter.js';
        $jsurl  = content_url() . str_replace('\\' ,'/', substr( $path, stripos($path, 'wp-content') + 10));
        wp_enqueue_script ( 'widget-display-filter', $jsurl, array('jquery', 'jquery-ui-tabs'));
    }

    //Ajax : wp_ajax_Widget_filter_postid
    public function widget_filter_postid() {
        check_ajax_referer( 'widget_display_filter' );
        $filter = get_option('widget_display_filter');
        if(is_string($_POST['hashtag'])){
            $tag = trim(stripslashes($_POST['hashtag']));
            //The temporary update Post ID filter settings Once tags exist
            if(isset($filter['display'][$tag]) && isset($_POST['in_postid'])){
                $option = $filter['display'][$tag];
                $option["in_postid"] = $_POST['in_postid'];
                $arpid = array();
                if(isset($_POST['postid'])){
                    $arpid = array_map('trim', explode(',', $_POST['postid']));
                }
                $erflg = false;
                $option["postid"] = array();
                foreach ($arpid as $pid) {
                    if($pid === ""){
                        $option["postid"][] = "";
                        break;
                    }
                    elseif(ctype_digit($pid)){
                        $pd = get_post($pid);
                        if(!empty($pd)){
                            $option["postid"][] = $pid;
                        }
                        else 
                            $erflg = true;
                    }
                    else 
                        $erflg = true;
                }
                if($erflg){
                    $notice = __('It contains invalid Post ID.','wdfilter');
                    wp_send_json_error($notice);
                }
                else { //Browser rewrite (Post ID relevant tag)
                    $html = $this->filter_stat($tag, 'postid', $option['in_postid'], implode(",", (array)$option["postid"]));
                    wp_send_json_success($html);
                }
            }
            exit;    
        }
    }

    //Ajax : wp_ajax_Widget_filter_category
    public function widget_filter_category() {
        check_ajax_referer( 'widget_display_filter' );
        $filter = get_option('widget_display_filter');
        if(is_string($_POST['hashtag'])){
            $tag = trim(stripslashes($_POST['hashtag']));
            //The temporary update categorys filter settings Once tags exist
            if(isset($filter['display'][$tag]) && isset($_POST['in_category'])){
                $option = $filter['display'][$tag];
                $option["in_category"] = $_POST['in_category'];
                $option["category"] = '';
                if(isset($_POST['category'])){
                    $option["category"] = explode(",", $_POST['category']);
                }
                //Browser rewrite (Category relevant tag)
        		$categories = (array) get_terms('category', array('get' => 'all'));
                $categoryname = array();
                foreach( $categories as $k=>$v ) {
                    if (!empty($option['category']) && in_array( $categories[$k]->term_id, $option['category']) ) {
                        $categoryname[] = $categories[$k]->name;
                    }
                }
                $html = $this->filter_stat($tag, 'category', $option['in_category'], implode(",", (array)$categoryname));
                wp_send_json_success($html);
            }
            exit;    
        }
    }

    //Ajax : wp_ajax_Widget_filter_post_yag
    public function widget_filter_post_tag() {
        check_ajax_referer( 'widget_display_filter' );
        $filter = get_option('widget_display_filter');
        if(is_string($_POST['hashtag'])){
            $tag = trim(stripslashes($_POST['hashtag']));
            //The temporary update categorys filter settings Once tags exist
            if(isset($filter['display'][$tag]) && isset($_POST['in_post_tag'])){
                $option = $filter['display'][$tag];
                $option["in_post_tag"] = $_POST['in_post_tag'];
                $option["post_tag"] = '';
                if(isset($_POST['post_tag'])){
                    $option["post_tag"] = explode(",", $_POST['post_tag']);
                }
                //Browser rewrite (post_tag relevant tag)
        		$posttags = (array) get_terms('post_tag', array('get' => 'all'));
                $tagnames = array();
                foreach( $posttags as $k=>$v ) {
                    if (!empty($option['post_tag']) && in_array( $posttags[$k]->term_id, $option['post_tag']) ) {
                        $tagnames[] = $posttags[$k]->name;
                    }
                }
                $html = $this->filter_stat($tag, 'post_tag', $option['in_post_tag'], implode(",", (array)$tagnames));
                wp_send_json_success($html);
            }
            exit;    
        }
    }

    //widget conditions data (add, update,delete)
    public function action_posts() {
        if(current_user_can( 'edit_plugins' )){
            if( isset($_POST['entry_register_filter']) ) {   //hidden widget
                check_admin_referer( 'widget_display_filter' );
                if(isset($_POST['widget_display_filter']['class'])){
                    $widget = stripslashes($_POST['widget_display_filter']['class']);
                    $option['type'] = 'register';
                    $option['class'] = $widget;
                    $this->filter['register'][$widget] = $option;
                    update_option('widget_display_filter', $this->filter );
                }
                wp_safe_redirect(admin_url('themes.php?page=widget_display_filter_manage_page'));
                exit;
            } 
            elseif( isset($_POST['entry_display_filter']) || isset($_POST['save_display_filter']) ){
                check_admin_referer( 'widget_display_filter' );
                $checkbox = array('desktop','mobile','home','archive','search','attach','page','post','post-image','post-gallery','post-video','post-audio','post-aside','post-quote','post-link','post-status','post-chat');
                $post_types = get_post_types( array('public' => true, '_builtin' => false) );                    
                foreach ( $post_types as $cptype ) {
                    $checkbox[] = $cptype;
                }
                if( isset($_POST['entry_display_filter']) ) {   //display filter hashtag add
                    if(is_string($_POST['widget_display_filter']['hashtag'])){
                        $tag = '';
                        if(preg_match('/\A#?([a-zA-Z0-9_\-]+)(\Z|\s)/u', $_POST['widget_display_filter']['hashtag'], $match)){
                            $tag = $match[1];;
                            if(empty($this->filter['display'][$tag])){
                                $option = array();
                                $option['type'] = 'display';
                                $option['hashtag'] = $tag;
                                foreach ( $checkbox as $type ) {
                                    $option[$type] = true;
                                }
                                $option["in_postid"] = 'include';
                                $option["postid"] = '';
                                $option["in_category"] = 'include';
                                $option["category"] = '';
                                $option["in_post_tag"] = 'include';
                                $option["post_tag"] = '';
                                $this->filter['display'][$tag] = $option;
                                update_option('widget_display_filter', $this->filter );
                            }
                        }
                        if(empty($tag)){
                            $notice = __('There are invalid characters in Hashtag. Use characters include Alphanumeric, Hyphens, Underscores.','wdfilter');
                            set_transient('widget_display_filter_notice', $notice, 30);
                        }
                    }
                } 
                else if( isset($_POST['save_display_filter']) ) {   //Save display options
                    $categories = (array) get_terms('category', array('get' => 'all'));
                    $posttags = (array) get_terms('post_tag', array('get' => 'all'));
                    foreach($_POST['widget_display_filter'] as $tag=>$opt) { 
                        if(!empty($this->filter['display'][$tag])){
                            $option = array();
                            $option['type'] = 'display';
                            $option['hashtag'] = $tag;
                            foreach ( $checkbox as $type ) {
                                $option[$type] = (! isset($_POST['widget_display_filter'][$tag][$type])) ? false : (bool) $_POST['widget_display_filter'][$tag][$type];
                            }
                            $option["in_postid"] = (! isset($_POST['widget_display_filter'][$tag]['in_postid'])) ? 'include' : $_POST['widget_display_filter'][$tag]['in_postid'];
                            $ids = array();
                            if(!empty($_POST['widget_display_filter'][$tag]['postid'])){
                                $ids = array_map('trim', explode(',', $_POST['widget_display_filter'][$tag]['postid']));
                            }
                            $option["postid"] = $ids;

                            $option["in_category"] = (! isset($_POST['widget_display_filter'][$tag]['in_category'])) ? 'include' : $_POST['widget_display_filter'][$tag]['in_category'];
                            $cat = '';
                            if(!empty($_POST['widget_display_filter'][$tag]['category'])){
                                $catname = array_map('trim', explode(',', $_POST['widget_display_filter'][$tag]['category']));
                                foreach( $categories as $cv ) {
                                    if (in_array( $cv->name, $catname) ) {
                                        $cat[] = $cv->term_id;
                                    }
                                }
                            }
                            $option["category"] = $cat;

                            $option["in_post_tag"] = (! isset($_POST['widget_display_filter'][$tag]['in_post_tag'])) ? 'include' : $_POST['widget_display_filter'][$tag]['in_post_tag'];
                            $ptag = '';
                            if(!empty($_POST['widget_display_filter'][$tag]['post_tag'])){
                                $tagname = array_map('trim', explode(',', $_POST['widget_display_filter'][$tag]['post_tag']));
                                foreach( $posttags as $tv ) {
                                    if (in_array( $tv->name, $tagname) ) {
                                        $ptag[] = $tv->term_id;
                                    }
                                }
                            }
                            $option["post_tag"] = $ptag;
                            $this->filter['display'][$tag] = $option;
                        }
                    }
                    update_option('widget_display_filter', $this->filter );                    
                }
                wp_safe_redirect(admin_url('themes.php?page=widget_display_filter_manage_page&action=tab_select_1'));
                exit;
            }
            else if (!empty($_GET['action'])) {
                if( $_GET['action']=='del_widget_display') {
                    check_admin_referer( 'widget_display_filter' );
                    if( !empty( $_GET['hashtag']) && isset($this->filter['display'][$_GET['hashtag']])){
                        unset($this->filter['display'][$_GET['hashtag']]);
                        update_option('widget_display_filter', $this->filter );
                    }
                    wp_safe_redirect(admin_url('themes.php?page=widget_display_filter_manage_page&action=tab_select_1'));
                    exit;
                }
                elseif( $_GET['action']=='del_widget_register') {
                    check_admin_referer( 'widget_display_filter' );
                    if( !empty( $_GET['class']) && isset($this->filter['register'][$_GET['class']])){
                        unset($this->filter['register'][$_GET['class']]);
                        update_option('widget_display_filter', $this->filter );
                    }
                    wp_safe_redirect(admin_url('themes.php?page=widget_display_filter_manage_page'));
                    exit;
                } 
                elseif( $_GET['action']=='tab_select_1') { //tab1 select
                    $this->editem = '';
                }
            }
        }
    }

    //To determine the display condition When the hash tag of title part matches
    public function widget_instance_filter( $instance, $widget, $args )
    {
        global $wp_query;
        if(!empty($instance['title'])&& preg_match('/(\A|\s)#([a-zA-Z0-9_\-]+)(\Z|\s)/u', $instance['title'], $match)){
            $hashtag = $match[2];
            $instance['title'] = trim( preg_replace('/(\A|\s)#([a-zA-Z0-9_\-]+)(\Z|\s)/u', '', $instance['title']));
            
            $filter = empty($this->filter['display'][$hashtag])? false : $this->filter['display'][$hashtag];
            if(!empty($filter) && $filter['type']==='display'){
                $df = false;
                if(wp_is_mobile()){ //device check
                    if(!empty($filter['mobile']))
                        $df = true;
                }
                else {
                    if(!empty($filter['desktop']))
                        $df = true;
                }
                if($df) {           //Post Type check
                    $df = false;
                    if(is_home() || is_front_page()){
                        if(!empty($filter['home']))
                            $df = true;
                    }
                    elseif(is_archive()){
                        if(!empty($filter['archive']))
                            $df = true;
                    }
                    elseif(is_search()){
                        if(!empty($filter['search']))
                            $df = true;
                    }
                    elseif(is_attachment()){
                        if(!empty($filter['attach']))
                            $df = true;
                        if(!empty($filter['postid']) && $filter['in_postid']==='include' && is_attachment($filter['postid']))
                            $df = true;
                        if($df){
                            if(!empty($filter['postid']) && $filter['in_postid']==='exclude' && is_attachment($filter['postid']))
                                $df = false;
                        }
                    }
                    elseif(is_page()){
                        if(!empty($filter['page']))
                            $df = true;
                        if(!empty($filter['postid']) && $filter['in_postid']==='include' && is_page($filter['postid']))
                            $df = true;
                        if($df){
                            if(!empty($filter['postid']) && $filter['in_postid']==='exclude' && is_page($filter['postid']))
                                $df = false;
                        }
                    }
                    elseif(is_single()){ //Post & Custom Post
                        $type = get_post_type( $wp_query->post);
                        if($type === 'post'){
                            $fmt = get_post_format();
                            if(!empty($filter['post']) && ($fmt == 'standard' || $fmt == false))
                                $df = true;
                            if(!empty($filter['post-image']) && $fmt == 'image')
                                $df = true;
                            if(!empty($filter['post-gallery']) && $fmt == 'gallery')
                                $df = true;
                            if(!empty($filter['post-video']) && $fmt == 'video')
                                $df = true;
                            if(!empty($filter['post-audio']) && $fmt == 'audio')
                                $df = true;
                            if(!empty($filter['post-aside']) && $fmt == 'aside')
                                $df = true;
                            if(!empty($filter['post-quote']) && $fmt == 'quote')
                                $df = true;
                            if(!empty($filter['post-link']) && $fmt == 'link')
                                $df = true;
                            if(!empty($filter['post-status']) && $fmt == 'status')
                                $df = true;
                            if(!empty($filter['post-chat']) && $fmt == 'chat')
                                $df = true;
                            if(!empty($filter['category']) && $filter['in_category']==='include' && in_category($filter['category']))
                                $df = true;
                            if(!empty($filter['post_tag']) && $filter['in_post_tag']==='include' && has_tag($filter['post_tag']))
                                $df = true;
                            if($df){
                                if(!empty($filter['category']) && $filter['in_category']==='exclude' && in_category($filter['category']))
                                    $df = false;
                                if(!empty($filter['post_tag']) && $filter['in_post_tag']==='exclude' && has_tag($filter['post_tag']))
                                    $df = false;
                            }
                        }
                        else {
                            $post_types = get_post_types( array('public' => true, '_builtin' => false) );   
                            if(!empty($post_types)){
                                foreach ( $post_types as $cptype ) {
                                    if(!empty($filter[$cptype]) && $type == $cptype){
                                        $df = true;
                                    }
                                }
                            }
                        }
                        if(!empty($filter['postid']) && $filter['in_postid']==='include' && is_single($filter['postid']))
                            $df = true;
                        if($df){
                            if(!empty($filter['postid']) && $filter['in_postid']==='exclude' && is_single($filter['postid']))
                                $df = false;
                        }
                    }
                }
                if($df === false) { //Conditions mismatch
                    $instance = false;
                }
            }
        }
        return $instance;
    }
    
    public function filter_stat( $tag, $item, $sw, $stat) {
        $hid1 = "<input type='hidden' name='widget_display_filter[$tag][in_$item]' value='$sw' />";
        $hid2 = "<input type='hidden' name='widget_display_filter[$tag][$item]' value='$stat' />";
        if(empty($stat))
            $str = $hid1 . $hid2 . '<span class="dashicons dashicons-plus" style="color: #ddd;"></span>';
        else if(empty($sw) || $sw === 'exclude')
            $str = $hid1 . $hid2 . '<span style="color: #ff0000;">' . $stat. '</span>';
        elseif(!empty($sw))
            $str = $hid1 . $hid2 . '<span style="color: #339966;">' . $stat. '</span>';
        else
            $str = $hid1 . $hid2 . $stat;
        return $str;
    }   

	static function altcheckbox($name, $value, $label = '') {
        return "<input type='hidden' name='$name' value='0'><input type='checkbox' name='$name' value='1' " . checked( $value, 1, false ).  "/><label> $label</label>";
	}
    
    public function filter_checkmark($tag, $type, $opt ) {
        $name = "widget_display_filter[$tag][$type]";
        $checked = (empty($opt[$type]))? false : true;
        $str = '<td class="altcheckbox">' . self::altcheckbox($name, $checked, '<span class="dashicons dashicons-yes"></span>') . '</td>';
        return $str;
    }   

    //Unregist filter table display
    public function register_filter_table( $default) {
    ?>
    <table class="widefat">
    <thead>
      <tr>
        <th><?php _e('Widget', 'wdfilter'); ?></th>
        <th><?php _e('Description', 'wdfilter'); ?></th>
        <th>&nbsp;</th>
      </tr>
    </thead>
    <tbody>
    <?php
        if(!empty($this->filter['register'])){
            foreach( $this->filter['register'] as $id ) {
                $opt = wp_parse_args( (array) $id,  $default);
                $widget_name = '';
                foreach ( $this->widgets as $widget_class => $widget ) {
                    if ( $widget_class == $opt['class'] ) {
                        $widget_name = $widget->name;
                        $widget_doc = $widget->widget_options['description'];
                        if(!empty($widget_doc)){
                            if(mb_strlen($widget_doc) > 90)
                                $widget_doc = mb_substr($widget_doc, 0, 90). "…"; 
                        }
                        break;
                    }
                }
                if(!empty($widget_name)){
                    echo '<tr id="load_filter_' .$opt['class']. '">';
                    echo '<td>'.$widget_name.'</td>';
                    echo '<td>'.$widget_doc.'</td>';
                    //Restore link
                    $url = wp_nonce_url( "themes.php?page=widget_display_filter_manage_page&amp;action=del_widget_register&amp;class={$opt['class']}", "widget_display_filter" ); 
                    echo "<td><a class='delete' href='$url'>" . __( 'Restore', 'wdfilter' ) . "</a></td>";
                    echo "</tr>";
                }
            }
        }
    ?>
    </tbody>
    </table>
    <?PHP
    }
    
    //Display filter table display
    public function display_filter_table( $default) {
        $post_types = get_post_types( array('public' => true, '_builtin' => false) );                    
		$categories = (array) get_terms('category', array('get' => 'all'));
        $posttags = (array) get_terms('post_tag', array('get' => 'all'));
        $chklist = array('home','archive','search','attach','page','post','post-image','post-gallery','post-video','post-audio','post-aside','post-quote','post-link','post-status','post-chat');
		$ajax_nonce = wp_create_nonce( 'widget_display_filter' );
    ?>
    <div id="wrap_wdfilter-activation-table">
        <table id="wdfilter-activation-table" class="widefat">
          <thead>
            <tr>
              <th ><?php _e('Hashtag', 'wdfilter'); ?></th>
              <th ><span title="<?php _e('Desktop Device', 'wdfilter'); ?>" class="dashicons dashicons-desktop"></span><br /><span style="font-size:xx-small">Desktop</span></th>
              <th ><span title="<?php _e('Mobile Device', 'wdfilter'); ?>" class="dashicons dashicons-smartphone"></span><br /><span style="font-size:xx-small">Mobile</span></th>
              <th ><span title="<?php _e('Singular Post ID', 'wdfilter'); ?>" style="font-size:xx-small">Post ID</span></th>
              <th ><span title="<?php _e('Home/Front-page', 'wdfilter'); ?>" class="dashicons dashicons-admin-home"></span><br /><span style="font-size:xx-small">Home</span></th>
              <th ><span title="<?php _e('Archive page', 'wdfilter'); ?>" class="dashicons dashicons-list-view"></span><br /><span style="font-size:xx-small">Archive</span></th>
              <th ><span title="<?php _e('Search page', 'wdfilter'); ?>" class="dashicons dashicons-search"></span><br /><span style="font-size:xx-small">Search</span></th>
              <th ><span title="<?php _e('Attachment page', 'wdfilter'); ?>" class="dashicons dashicons-media-default"></span><br /><span style="font-size:xx-small">Attach</span></th>
              <th ><span title="<?php _e('Static Page', 'wdfilter'); ?>" class="dashicons dashicons-admin-page"></span><br /><span style="font-size:xx-small">Page</span></th>
              <th ><span title="<?php _e('Post : Standard', 'wdfilter'); ?>" class="dashicons dashicons-admin-post"></span><br /><span style="font-size:xx-small">Post</span></th>
              <th ><span title="<?php _e('Post : Image', 'wdfilter'); ?>" class="dashicons dashicons-format-image"></span><br /><span style="font-size:xx-small">Image</span></th>
              <th ><span title="<?php _e('Post : Gallery', 'wdfilter'); ?>" class="dashicons dashicons-format-gallery"></span><br /><span style="font-size:xx-small">Gallery</span></th>
              <th ><span title="<?php _e('Post : Video', 'wdfilter'); ?>" class="dashicons dashicons-format-video"></span><br /><span style="font-size:xx-small">Video</span></th>
              <th ><span title="<?php _e('Post : Audio', 'wdfilter'); ?>" class="dashicons dashicons-format-audio"></span><br /><span style="font-size:xx-small">Audio</span></th>
              <th ><span title="<?php _e('Post : Aside', 'wdfilter'); ?>" class="dashicons dashicons-format-aside"></span><br /><span style="font-size:xx-small">Aside</span></th>
              <th ><span title="<?php _e('Post : Quote', 'wdfilter'); ?>" class="dashicons dashicons-format-quote"></span><br /><span style="font-size:xx-small">Quote</span></th>
              <th ><span title="<?php _e('Post : Link', 'wdfilter'); ?>" class="dashicons dashicons-admin-links"></span><br /><span style="font-size:xx-small">Link</span></th>
              <th ><span title="<?php _e('Post : Status', 'wdfilter'); ?>" class="dashicons dashicons-format-status"></span><br /><span style="font-size:xx-small">Status</span></th>
              <th ><span title="<?php _e('Post : Chat', 'wdfilter'); ?>" class="dashicons dashicons-format-chat"></span><br /><span style="font-size:xx-small">Chat</span></th>
              <th ><span title="<?php _e('Post Category', 'wdfilter'); ?>" class="dashicons dashicons-category"></span><br /><span style="font-size:xx-small">Category</span></th>
              <th ><span title="<?php _e('Post Tag', 'wdfilter'); ?>" class="dashicons dashicons-tag"></span><br /><span style="font-size:xx-small">Tag</span></th>
              <?php
                foreach ( $post_types as $cptype ) {
                    $title = __('Custom Post : ', 'wdfilter') . $cptype;
                    echo "<th ><span title='$title' style='font-size:xx-small'>$cptype</span></th>";
                }
              ?>
              <th colspan="1">&nbsp;</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if(!empty($this->filter['display'])){
                ?>
                <script type="text/javascript">
                  var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
                  var widget_filter = new Object();
                </script>
                <?php                
                foreach( $this->filter['display'] as $id ) {
                    $opt = wp_parse_args( (array) $id,  $default);
                    $hashtag = $opt['hashtag'];
                    if(empty($hashtag))
                        continue;
               
                    ?>
                    <script type="text/javascript">
                      widget_filter["<?php echo $hashtag; ?>"] = <?php echo json_encode( $opt ); ?>
                    </script>
                    <?php                
                    echo '<tr>';
                    echo '<td>#'.$hashtag.'</td>';
                    echo $this->filter_checkmark($hashtag, 'desktop', $opt);
                    echo $this->filter_checkmark($hashtag, 'mobile', $opt);
                    $sid = "widget-filter-postid-$hashtag";
                    $pidlist = "<span id='$sid'>" . $this->filter_stat($hashtag, 'postid', $opt['in_postid'], implode(",", (array)$opt['postid'])) . '</span>';
                    echo '<td><p class="hide-if-no-js"><a href="#wpbody-content" onclick="WidgetFilterPostid(\'' . $ajax_nonce . '\',\'' . $hashtag . '\');return false;" >'. $pidlist .'</a></p></td>';

                    foreach($chklist as $type){
                        echo $this->filter_checkmark($hashtag, $type, $opt);
                    }

                    $categoryname = array();
                    foreach( $categories as $k=>$v ) {
                        if (!empty($opt['category']) && in_array( $categories[$k]->term_id, $opt['category']) ) {
                            $categoryname[] = $categories[$k]->name;
                        }
                    }
                    $sid = "widget-filter-category-$hashtag";
                    $catlist = "<span id='$sid'>" . $this->filter_stat($hashtag, 'category', $opt['in_category'], implode(",", (array)$categoryname)) . '</span>';
                    echo '<td><p class="hide-if-no-js"><a href="#wpbody-content" onclick="WidgetFilterCategory(\'' . $ajax_nonce . '\',\'' . $hashtag . '\');return false;" >'. $catlist .'</a></p></td>';
                    
                    $tagname = array();
                    foreach( $posttags as $k=>$v ) {
                        if (!empty($opt['post_tag']) && in_array( $posttags[$k]->term_id, $opt['post_tag']) ) {
                            $tagname[] = $posttags[$k]->name;
                        }
                    }
                    $sid = "widget-filter-posttag-$hashtag";
                    $ptaglist = "<span id='$sid'>" . $this->filter_stat($hashtag, 'post_tag', $opt['in_post_tag'], implode(",", (array)$tagname)) . '</span>';
                    echo '<td><p class="hide-if-no-js"><a href="#wpbody-content" onclick="WidgetFilterPosttag(\'' . $ajax_nonce . '\',\'' . $hashtag . '\');return false;" >'. $ptaglist .'</a></p></td>';

                    foreach ( $post_types as $cptype) {
                        if(isset($opt[ $cptype])){
                            echo $this->filter_checkmark($hashtag, $cptype, $opt);
                        }
                    }
                    //Delete link
                    $url = wp_nonce_url( "themes.php?page=widget_display_filter_manage_page&amp;action=del_widget_display&amp;hashtag=$hashtag", "widget_display_filter" ); 
                    echo "<td><a class='delete' href='$url'>" . __( 'Delete', 'wdfilter' ) . "</a></td>";
                    echo "</tr>";
                }
            }
            ?>
          </tbody>
        </table>
    </div>
    <?PHP
    }
    
    //Option setting screen
    public function widget_display_filter_option_page() 
    {
        $default = array( 'type' => 'register', 'class' => false, 'hashtag' => '', 'desktop' => false, 'mobile' => false, 'home' => false, 'archive' => false, 'search' => false, 'attach' => false, 'page' => false,
            'post' => false, 'post-image' => false, 'post-gallery' => false, 'post-video' => false, 'post-audio' => false, 'post-aside' => false, 'post-quote' => false, 'post-link' => false, 'post-status' => false, 'post-chat' => false,
            'in_postid' => 'include', 'postid' => '', 'in_category' => 'include', 'category' => '', 'in_post_tag' => 'include', 'post_tag' => '');
        $post_types = get_post_types( array('public' => true, '_builtin' => false) );                    
        foreach ( $post_types as $post_type ) {
            $default[$post_type] = false;
        }
        ?>
        <script type='text/javascript' >
        /* <![CDATA[ */
          var widget_display_filter_tab = <?php echo ($this->editem === false)? '0' : '1'; ?>;
        /* ]]> */
        </script> 
        <h2><?php _e('Widget Display Filter Settings', 'wdfilter'); ?></h2>
        <p><?php _e('<strong>Hidden Widgets</strong> : Registration widget will no longer be displayed in <strong>Abailable Widgets</strong>.', 'wdfilter'); ?></p>
        <p><?php _e('<strong>Widgets Display Filter</strong> : Using Hashtag, Make the settings for the display conditions of Widgets.', 'wdfilter'); ?></p>
        <div id="widget-filter-tabs">
          <ul>
            <li><a href="#table-register-tab" ><?php _e('Hidden Widgets', 'wdfilter'); ?></a></li>
            <li><a href="#table-display-tab" ><?php _e('Widgets Display Filter', 'wdfilter'); ?></a></li>
          </ul>
          <form method="post" >
            <?php wp_nonce_field( 'widget_display_filter'); ?>
            <div id="table-register-tab" style="display : none;">
              <?php $this->register_filter_table( $default); ?>
              <br />
              <table width="100%" cellspacing="2" cellpadding="3" class="editform form-table">
                <tbody>
                  <tr>
                    <th valign="top" scope="row"><label for="widget"><?php _e('Widget', 'wdfilter'); ?>:</label></th>
                    <td>
                      <select name="widget_display_filter[class]" id="widget_register">
                      <?php
                        $inactive = array();
                        if(!empty($this->filter['register'])){
                            foreach( $this->filter['register'] as $id ) {
                                $inactive[] = $id['class'];
                            }
                        }
                        foreach ( $this->widgets as $widget_class => $widget ) {
                            if(in_array($widget_class, $inactive) == false){
                                $widget_name = esc_attr($widget->name);
                                echo "\n\t<option value=\"$widget_class\" selected>$widget_name</option>";
                            }
                        }
                      ?>
                      </select>
                      <input type="submit" class="button-primary" name="entry_register_filter" value="<?php _e('Hidden Widget', 'wdfilter'); ?>" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div id="table-display-tab" style="display : none;">
              <?php $this->display_filter_table( $default); ?>
              <br />
              <table width="100%" cellspacing="2" cellpadding="3" class="editform form-table">
                <tbody>
                  <tr>
                    <th valign="top" scope="row"><label for="hashtag"><?php _e( 'Hashtag ', 'wdfilter' ); ?></label></th>
                    <td>
                      <input id="widget_display_filter[hashtag]" class="medium-text" type="text" name="widget_display_filter[hashtag]" value="" />
                      <input id="hashtag-add" class="button" name="entry_display_filter" type="submit" value="<?php _e('Hashtag Add', 'wdfilter'); ?>" />
                      <p><?php _e('Please enter a string tag for identification.　[Use characters : alphanumeric, hyphen, underscore]','wdfilter'); ?></p>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div class="submit">
                <?php submit_button( __( 'Save Settings', 'wdfilter' ), 'primary', 'save_display_filter', false ); ?>
              </div>
              <div><strong>
                <p><?php _e('Display conditions of the widget, set from Appearance -> Widgets menu of the management page.','wdfilter'); ?></p>
                <p><?php _e('Very simple. If you enter Hashtag in Widget Title input field, its display condition is enabled.','wdfilter'); ?><br />
                <?php _e('Hashtag that can be set for each widget is only one. Between Hashtag and title should be separated by a space.','wdfilter'); ?><br />
                <?php _e('By setting the same Hashtag to multiple widgets, you can easily manage as a group.','wdfilter'); ?></p>
                <p><?php _e('* Discrimination of Desktop / Mobile device uses the wp_is_mobile function.','wdfilter'); ?></p>
              </strong></div>
            </div>
          </form>
        </div>
        <div id="postid-dialog" title="Widget Display Filter" style="display : none;">
          <form>
            <div  style="margin:10px;">
              <p><?php _e( 'Set the display condition by Post ID.', 'wdfilter' ); ?></p>
              <table class="form-table">
                <tr valign="top">
                  <td>
                    <label><input type="radio" name="widget_display_filter[in_postid]" value="include" /><?php _e('include', 'wdfilter'); ?></label>
                    <label><input type="radio" name="widget_display_filter[in_postid]" value="exclude" /><?php _e('exclude', 'wdfilter'); ?></label>
                    <br />
                    <input type="text" size="48" id="filter-postid" name="widget_display_filter[postid]" value=""/>
                    <p><span style="font-size:xx-small"><?php _e( 'Please specify Post ID separated by commas.', 'wdfilter' ) ?></span></p>
                  </td>
                </tr>
              </table>
            </div>
          </form>
        </div>    
        <div id="category-dialog" title="Widget Display Filter" style="display : none;">
          <form>
            <div  style="margin:10px;">
              <p><?php _e( 'Set the display conditions by post category.', 'wdfilter' ); ?></p>
              <table class="form-table">
                <tr valign="top">
                  <td>
                    <label><input type="radio" name="widget_display_filter[in_category]" value="include" /><?php _e('include', 'wdfilter'); ?></label>
                    <label><input type="radio" name="widget_display_filter[in_category]" value="exclude" /><?php _e('exclude', 'wdfilter'); ?></label>
                    <br />
                    <ul class="categorychecklist">
                      <?php wp_category_checklist(0,0,'',FALSE,NULL,FALSE); ?>
                    </ul>
                  </td>
                </tr>
              </table>
            </div>
          </form>
        </div>    
        <div id="posttag-dialog" title="Widget Display Filter" style="display : none;">
          <form>
            <div  style="margin:10px;">
              <p><?php _e( 'Set the display conditions by post tag.', 'wdfilter' ); ?></p>
              <table class="form-table">
                <tr valign="top">
                  <td>
                    <label><input type="radio" name="widget_display_filter[in_post_tag]" value="include" /><?php _e('include', 'wdfilter'); ?></label>
                    <label><input type="radio" name="widget_display_filter[in_post_tag]" value="exclude" /><?php _e('exclude', 'wdfilter'); ?></label>
                    <br />
                    <ul class="posttagchecklist">
                      <?php wp_terms_checklist( 0, array( 'taxonomy' => 'post_tag') ); ?>    
                    </ul>
                  </td>
                </tr>
              </table>
            </div>
          </form>
        </div>    
        <?php
    }
}
