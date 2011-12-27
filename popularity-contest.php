<?php
/*
Plugin Name: Popularity Contest
Plugin URI: http://crowdfavorite.com/wordpress/plugins/popularity-contest/
Description: This will enable ranking of your posts by popularity; using the behavior of your visitors to determine each post's popularity. You set a value (or use the default value) for every post view, comment, etc. and the popularity of your posts is calculated based on those values. Once you have activated the plugin, you can configure the Popularity Values and View Reports. You can also use the included Widgets and Template Tags to display post popularity and lists of popular posts on your blog.
Version: 2.1
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/ 

// Copyright (c) 2005-2010 
//   Crowd Favorite, Ltd. - http://crowdfavorite.com
//   Alex King - http://alexking.org
// All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for WordPress - http://wordpress.org
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

// Special thanks to Martijn Stegink for help with WordPress 2.3 compatibility.

if (!defined('AKPC_LOADED')) : // LOADED CHECK

@define('AKPC_LOADED', true);

/* -- INSTALLATION --------------------- */

// To hide the popularity score on a per post/page basis, add a custom field to the post/page as follows:
//   name: hide_popularity
//   value: 1

// By default the view is recorded via an Ajax call from the page. If you want Popularity Contest to do this on the 
// back end set this to 0. Setting this to 0 will cause popularity contest results to improperly tally when caching is 
// turned on. It is recommended to use the API.

@define('AKPC_USE_API', 1);

// If you would like to show lists of popular posts in the sidebar, 
// take a look at how it is implemented in the included sidebar.php.

/* ------------------------------------- */

define('AKPC_VERSION', '2.1');

load_plugin_textdomain('popularity-contest');

if (is_file(trailingslashit(WP_PLUGIN_DIR).'popularity-contest.php')) {
	define('AKPC_FILE', trailingslashit(WP_PLUGIN_DIR).'popularity-contest.php');
	define('AKPC_DIR_URL', trailingslashit(WP_PLUGIN_URL));
}
else if (is_file(trailingslashit(WP_PLUGIN_DIR).'popularity-contest/popularity-contest.php')) {
	define('AKPC_FILE', trailingslashit(WP_PLUGIN_DIR).'popularity-contest/popularity-contest.php');
	define('AKPC_DIR_URL', trailingslashit(WP_PLUGIN_URL).'popularity-contest/');
}
else if (is_file(trailingslashit(TEMPLATEPATH).'plugins/popularity-contest/popularity-contest.php')) {
	define('AKPC_FILE', trailingslashit(TEMPLATEPATH).'plugins/popularity-contest/popularity-contest.php');
	define('AKPC_DIR_URL', trailingslashit(get_bloginfo('template_url')).'plugins/popularity-contest/popularity-contest.php');
}

define('CF_ADMIN_DIR', 'popularity-contest/cf-admin/');
require_once('cf-admin/cf-admin.php');

function akpc_activate() {
	if (akpc_is_multisite() && akpc_is_network_activation()) {
		akpc_activate_for_network(); // akpc_activate_for_network() is defined at bottom of file
	}
	else {
		akpc_activate_single();
	}	
}
register_activation_hook(AKPC_FILE, 'akpc_activate');

function akpc_activate_single() {
	global $akpc, $wpdb;
	if (!is_a($akpc, 'ak_popularity_contest')) {
		$akpc = new ak_popularity_contest();
	}
	if (!isset($wpdb->ak_popularity)) {
		$wpdb->ak_popularity = $wpdb->prefix . 'ak_popularity';
	}
	if (!isset($wpdb->ak_popularity_options)) {
		$wpdb->ak_popularity_options = $wpdb->prefix . 'ak_popularity_options';
	}
	
	$akpc->install();
	$akpc->upgrade();
	$akpc->mine_gap_data();
}

// -- MAIN FUNCTIONALITY

class ak_popularity_contest {
	function ak_popularity_contest() {
		$this->options = array(
			'feed_value',
			'home_value',
			'archive_value',
			'category_value',
			'tag_value',
			'single_value',
			'searcher_value',
			'comment_value',
			'pingback_value',
			'trackback_value',
			'searcher_names',
			'show_pop',
			'show_help',
			'ignore_authors',
			'post_types',
		);
		$this->feed_value = 1;
		$this->home_value = 2;
		$this->archive_value = 4;
		$this->category_value = 6;
		$this->tag_value = 6;
		$this->single_value = 10;
		$this->searcher_value = 2;
		$this->comment_value = 20;
		$this->pingback_value = 50;
		$this->trackback_value = 80;
		$this->searcher_names = 'google.com yahoo.com bing.com';
		$this->logged = 0;
		$this->show_pop = 1;
		$this->show_help = 1;
		$this->ignore_authors = 1;
		$this->top_ranked = array();
		$this->current_posts = array();
		$this->post_types = 'post,page';
	}
	
	function get_settings() {
		global $wpdb;		
		// This checks to see if the tables are in the DB for this blog
		$settings = $this->query_settings();
		
		// If the DB tables are not in place, lets check to see if we can install
		if (!count($settings)) {
			// This checks to see if we need to install, then checks if we can install
			// For the can install to work in MU the AKPC_MU_AUTOINSTALL variable must be set to 1
			if (!$this->check_install()) {
				$this->install();
			}
			if (!$this->check_install()) {
				$error = __('
<h2>Popularity Contest Installation Failed</h2>
<p>Sorry, Popularity Contest was not successfully installed. Please try again, or try one of the following options for support:</p>
<ul>
<li><a href="http://wphelpcenter.com">WordPress HelpCenter</a> (the official support provider for Popularity Contest)</li>
<li><a href="http://wordpress.org">WordPress Forums</a> (community support forums)</li>
</ul>
<p>If you are having trouble and need to disable Popularity Contest immediately, simply delete the popularity-contest.php file from within your wp-content/plugins directory.</p>
				', 'popularity-contest');
				wp_die($error);
			}
			else {
				$settings = $this->query_settings();
			}
		}
		if (count($settings)) {
			foreach ($settings as $setting) {
				if (in_array($setting->option_name, $this->options)) {
					$this->{$setting->option_name} = $setting->option_value;
				}
			}
		}
		return true;
	}
	
	function query_settings() {
		global $wpdb;
		return @$wpdb->get_results("
			SELECT *
			FROM $wpdb->ak_popularity_options
		");
	}

	/**
	 * check_install - This function checks to see if the proper tables have been added to the DB for the blog the plugin is being activated for
	 *
	 * @return bool
	 */
	/**
	 * check_install - This function checks to see if the proper tables have been added to the DB for the blog the plugin is being activated for
	 *
	 * @return bool
	 */
	function check_install() {
		global $wpdb;
		$result = $wpdb->get_results("
			SHOW TABLES LIKE '{$wpdb->prefix}ak_popularity%'
		");
		return count($result) == 2;
	}
	
	function needs_upgrade() {
		global $wpdb;
		$cols = $wpdb->get_col("
			SELECT `option_name`
			FROM `$wpdb->ak_popularity_options`
		");
		
		return (!in_array('post_types', $cols));
	}

	/**
	 * install - This function installs the proper tables in the DB for handling popularity contest items
	 *
	 * @return bool - Returns whether the table creation was successful
	 */
	function install() {
		global $wpdb;
		$tables = $wpdb->get_col("
			SHOW TABLES
		");
		if (!in_array($wpdb->ak_popularity_options, $tables)) {
			$result = $wpdb->query("
				CREATE TABLE `$wpdb->ak_popularity_options` (
					`option_name` VARCHAR( 50 ) NOT NULL, 
					`option_value` VARCHAR( 255 ) NOT NULL
				) 
			");
			if ($result === false) {
				return false;
			}
			$this->default_values();
		}
		
		if (!in_array($wpdb->ak_popularity, $tables)) {
			$result = $wpdb->query("
				CREATE TABLE `$wpdb->ak_popularity` (
					`post_id` INT( 11 ) NOT NULL ,
					`total` INT( 11 ) NOT NULL ,
					`feed_views` INT( 11 ) NOT NULL ,
					`home_views` INT( 11 ) NOT NULL ,
					`archive_views` INT( 11 ) NOT NULL ,
					`category_views` INT( 11 ) NOT NULL ,
					`tag_views` INT( 11 ) NOT NULL ,
					`single_views` INT( 11 ) NOT NULL ,
					`searcher_views` INT( 11 ) NOT NULL ,
					`comments` INT( 11 ) NOT NULL ,
					`pingbacks` INT( 11 ) NOT NULL ,
					`trackbacks` INT( 11 ) NOT NULL ,
					`last_modified` DATETIME NOT NULL ,
					KEY `post_id` ( `post_id` )
				) 
			");
			if ($result === false) {
				return false;
			}
		}
		$this->mine_data();
		return true;		
	}
	
	function upgrade() {
		$this->upgrade_20();
		$this->upgrade_21();
	}
	
	function upgrade_20() {
		global $wpdb;
	
		$wpdb->query("
			ALTER TABLE `$wpdb->ak_popularity_options`
			CHANGE `option_value` `option_value` TEXT NULL
		");
		
		$cols = $wpdb->get_col("
			SHOW COLUMNS FROM $wpdb->ak_popularity
		");
		
		//2.0 Schema
		if (!in_array('tag_views', $cols)) {
			$wpdb->query("
				ALTER TABLE `$wpdb->ak_popularity`
				ADD `tag_views` INT( 11 ) NOT NULL
				AFTER `category_views`
			");
		}
		if (!in_array('searcher_views', $cols)) {
			$wpdb->query("
				ALTER TABLE `$wpdb->ak_popularity`
				ADD `searcher_views` INT( 11 ) NOT NULL
				AFTER `single_views`
			");
		}
		$temp = new ak_popularity_contest;
		$cols = $wpdb->get_col("
			SELECT `option_name`
			FROM `$wpdb->ak_popularity_options`
		");
		if (!in_array('searcher_names', $cols)) {
			$wpdb->insert(
				$wpdb->ak_popularity_options,
				array(
					'option_name' => 'searcher_names',
					'option_value' => $temp->searcher_names
				)
			);
		}
		if (!in_array('show_pop', $cols)) {
			$wpdb->insert(
				$wpdb->ak_popularity_options,
				array(
					'option_name' => 'show_pop',
					'option_value' => $temp->show_pop
				)
			);
		}
		if (!in_array('show_help', $cols)) {
			$wpdb->insert(
				$wpdb->ak_popularity_options,
				array(
					'option_name' => 'show_help',
					'option_value' => $temp->show_help
				)
			);
		}
		if (!in_array('ignore_authors', $cols)) {
			$wpdb->insert(
				$wpdb->ak_popularity_options,
				array(
					'option_name' => 'ignore_authors',
					'option_value' => $temp->ignore_authors
				)
			);
		}
	}
	
	function upgrade_21() {
		global $wpdb;
		$cols = $wpdb->get_col("
			SELECT `option_name`
			FROM `$wpdb->ak_popularity_options`
		");
		$temp = new ak_popularity_contest;
		// 2.1 Schema
		if (!in_array('post_types', $cols)) {
			$wpdb->insert(
				$wpdb->ak_popularity_options,
				array(
					'option_name' => 'post_types',
					'option_value' => $temp->post_types
				)
			);
		}
	}
	function default_values() {
		global $wpdb;
		foreach ($this->options as $option) {
			$result = $wpdb->insert(
				$wpdb->ak_popularity_options,
				array(
					'option_name' => $option,
					'option_value' => $this->$option
				)
			);
			if ($result === false) {
				return false;
			}
		}
		return true;
	}
	
	function update_settings() {
		if (!current_user_can('manage_options')) { 
			wp_die('Unauthorized.'); 
		}
		global $wpdb;
		foreach ($this->options as $option) {
			if (isset($_POST[$option])) {
				if ($option == 'post_types') {			
					$this->$option = implode(',', $_POST[$option]);
				}				
				else if ($option != 'searcher_names') {
					$this->$option = intval($_POST[$option]);
 				}

				else {
					$this->$option = stripslashes($_POST[$option]);
				}
				
				$wpdb->update(
					$wpdb->ak_popularity_options,
					array('option_value' => $this->$option),
					array('option_name' => $option)
				);
			}
		}
		$this->recalculate_popularity();
		$this->mine_gap_data();
		$cf_tab = '';
		if (isset($_GET['cf_tab'])) {
			$cf_tab = '&cf_tab='.esc_js($_GET['cf_tab']);
		}
		header('Location: '.admin_url('/options-general.php?page='.basename(AKPC_FILE, '.php').'&updated=true'.$cf_tab));
		die();
	}
	
	function recalculate_popularity() {
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare("
			UPDATE $wpdb->ak_popularity
			SET total = (home_views * %d)
				+ (feed_views * %d)
				+ (archive_views * %d)
				+ (category_views * %d)
				+ (tag_views * %d)
				+ (single_views * %d)
				+ (searcher_views * %d)
				+ (comments * %d)
				+ (pingbacks * %d)
				+ (trackbacks * %d)
				", 
				$this->home_value, $this->feed_value , $this->archive_value,
				$this->category_value, $this->tag_value, $this->single_value,
				$this->searcher_value, $this->comment_value, $this->pingback_value,
				$this->trackback_value
				));
	}
	
	function update_post_meta($meta_id, $post_id, $meta_key, $meta_value) {
		global $wpdb;
		if ($meta_key == 'exclude_from_popularity') {
			update_post_meta($post_id, 'exclude_from_popularity', $meta_value); 
		}
	}
	
	function reset_data() {
		if (!current_user_can('manage_options')) { 
			wp_die('Unauthorized.'); 
		}
		global $wpdb;
		$result = $wpdb->query("
			TRUNCATE $wpdb->ak_popularity
		");
		if ($result === false) {
			return false;
		}

		$result = $wpdb->query("
			TRUNCATE $wpdb->ak_popularity_options
		");
		if ($result === false) {
			return false;
		}

		$this->default_values();
		return true;
	}

	function create_post_record($post_id = -1) {
		global $wpdb;
		if ($post_id == -1) {
			global $post_id;
		}
		$count = $wpdb->get_var( $wpdb->prepare("
			SELECT COUNT(post_id)
			FROM $wpdb->ak_popularity
			WHERE post_id = %d",
			$post_id
		));
		if (!intval($count)) {
			$result = $wpdb->insert( 
			 	$wpdb->ak_popularity,
				array(
					'post_id' => $post_id, 
					'last_modified' => date('Y-m-d H:i:s')
				)
			);
		}	
	}
	
	function delete_post_record($post_id = -1) {
		global $wpdb;
		if ($post_id == -1) {
			global $post_id;
		}
		$result = $wpdb->query( $wpdb->prepare("
			DELETE 
			FROM $wpdb->ak_popularity
			WHERE post_id = %d ",
			$post_id
		));
	}
	
	function mine_data() {
		global $wpdb;
		$post_ids = $wpdb->get_results("
			SELECT ID
			FROM $wpdb->posts
			WHERE post_status = 'publish'
		");
		if ($post_ids && count($post_ids) > 0) {
			foreach ($post_ids as $post_id) {
				$this->create_post_record($post_id->ID);
				$this->populate_post_data($post_id->ID);
			}
		}
		return true;
	}
	
	function mine_gap_data() {
		global $wpdb;
		$post_ids = $wpdb->get_results("
			SELECT p.ID
			FROM $wpdb->posts p
			LEFT JOIN $wpdb->ak_popularity pop
			ON p.ID = pop.post_id
			WHERE pop.post_id IS NULL
			AND p.post_status = 'publish'
		");
		if ($post_ids && count($post_ids) > 0) {
			foreach ($post_ids as $post_id) {
				$this->create_post_record($post_id->ID);
				$this->populate_post_data($post_id->ID);
			}
		}
	}
	
	function populate_post_data($post_id) {
		global $wpdb;
		
		// grab existing comments
		$count = intval($wpdb->get_var( $wpdb->prepare("
			SELECT COUNT(*)
			FROM $wpdb->comments
			WHERE comment_post_ID = %d
			AND comment_type = ''
			AND comment_approved = '1'",
			$post_id
		)));
		if ($count > 0) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET comments = comments + %d
				, total = total + %d 
				WHERE post_id = %d",
				$count, $this->comment_value * $count, $post_id
			));
			if ($result === false) {
				return false;
			}
		}

		// grab existing trackbacks
		$count = intval($wpdb->get_var( $wpdb->prepare("
			SELECT COUNT(*)
			FROM $wpdb->comments
			WHERE comment_post_ID = %d
			AND comment_type = 'trackback'
			AND comment_approved = '1'",
			$post_id
		)));
		if ($count > 0) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET trackbacks = trackbacks + %d
				, total = total + %d 
				WHERE post_id = %d",
				$count, $this->trackback_value * $count, $post_id
			));
			if ($result === false) {
				return false;
			}
		}

		// grab existing pingbacks
		$count = intval($wpdb->get_var( $wpdb->prepare("
			SELECT COUNT(*)
			FROM $wpdb->comments
			WHERE comment_post_ID = %d
			AND comment_type = 'pingback'
			AND comment_approved = '1'",
			$post_id
		)));
		if ($count > 0) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET pingbacks = pingbacks + %d
				, total = total + %d 
				WHERE post_id = %d",
				$count, $this->pingback_value * $count, $post_id
			));
			if ($result === false) {
				return false;
			}
		}
	}
	
	function record_view($api = false, $ids = false, $type = false) {
		if ($this->logged > 0 || ($this->ignore_authors && current_user_can('publish_posts'))) {
			return true;
		}
		global $wpdb;
		if ($api == false) {
			global $posts;
			if (!isset($posts) || !is_array($posts) || count($posts) == 0 || is_admin()) {
				return;
			}
			$ids = array();
			$ak_posts = $posts;
			foreach ($ak_posts as $post) {
				$ids[] = $post->ID;
			}
		}		
		if (!$ids || !count($ids)) {
			return;
		}
		$num_ids = count($ids);
		switch ($num_ids) {
			case 0:
				return false;
				break;
			default:
				$where_clause = "WHERE post_id IN (".$wpdb->escape(implode(',', $ids)).")";
				break;
		}	
				
		if (($api && $type == 'feed') || is_feed()) {
			
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET feed_views = feed_views + 1
				, total = total + %d 
				$where_clause",
				$this->feed_value
			));
			if ($result === false) {
				return false;
			}
		}		
		else if (($api && $type == 'archive') || (is_archive() && !is_category())) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET archive_views = archive_views + 1
				, total = total + %d 
				$where_clause",
				$this->archive_value
			));
			if ($result === false) {
				return false;
			}
		}
		else if (($api && $type == 'category') || is_category()) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET category_views = category_views + 1
				, total = total + %d 
				$where_clause",
				$this->category_value
			));
			if ($result === false) {
				return false;
			}
		}
		else if (($api && $type == 'tag') || is_tag()) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET tag_views = tag_views + 1
				, total = total + %d 
				$where_clause",
				$this->tag_value
			));
			if ($result === false) {
				return false;
			}
		}
		else if ($api && (in_array($type, array('single', 'page', 'searcher'))) || is_single() || is_singular() || is_page()) {
			if (($api && $type == 'searcher') || akpc_is_searcher()) {
				$result = $wpdb->query( $wpdb->prepare("
					UPDATE $wpdb->ak_popularity
					SET searcher_views = searcher_views + 1
					, total = total + %d 
					$where_clause",
					$this->searcher_value
				));
				if ($result === false) {
					return false;
				}
			}
			else {
				
				$result = $wpdb->query( $wpdb->prepare("
					UPDATE $wpdb->ak_popularity
					SET single_views = single_views + 1
					, total = total + %d 
					$where_clause",
					$this->single_value
				));				
				if ($result === false) {
					return false;
				}
			}
		}
		else if ($type = 'home' && is_home()) {
			$result = $wpdb->query( $wpdb->prepare("
				UPDATE $wpdb->ak_popularity
				SET home_views = home_views + 1
				, total = total + %d 
				$where_clause",
				$this->home_value
			));
			if ($result === false) {
				return false;
			}
		}
		$this->logged++;
		return true;
	}
	
	function record_feedback($type, $action = '+', $comment_id = null) {
		global $wpdb, $comment_post_ID;
		$action = $wpdb->escape($action);
				
		if ($comment_id) {
			$comment_post_ID = $comment_id;
		}
		switch ($type) {
			case 'trackback':
				$result = $wpdb->query( $wpdb->prepare("
					UPDATE $wpdb->ak_popularity
					SET trackbacks = trackbacks $action 1
					, total = total $action %d 
					WHERE post_id = %d",
					$this->trackback_value, $comment_post_ID
				));
				if ($result === false) {
					return false;
				}
				break;
			case 'pingback':
				$result = $wpdb->query( $wpdb->prepare("
					UPDATE $wpdb->ak_popularity
					SET pingbacks = pingbacks $action 1
					, total = total $action %d 
					WHERE post_id = %d",
					$this->pingback_value, $comment_post_ID
				));
				if ($result === false) {
					return false;
				}
				break;
			default:
				$result = $wpdb->query( $wpdb->prepare("
					UPDATE $wpdb->ak_popularity
					SET comments = comments $action 1
					, total = total $action %d
					WHERE post_id = %d",
					$this->comment_value, $comment_post_ID
				));
				if ($result === false) {
					return false;
				}
				break;
		}
		return true;
	}
	
	function edit_feedback($comment_id, $action, $status = null) {
		$comment = get_comment($comment_id);
		switch ($action) {
			case 'delete':
				$this->record_feedback($comment->comment_type, '-', $comment_id);
				break;
			case 'status':
				if ($status == 'spam') {
					$this->record_feedback($comment->comment_type, '-', $comment_id);
					return;
				}
				break;
		}
	}
	
	function recount_feedback() {
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized.');
		}
		global $wpdb;
		$post_ids = $wpdb->get_col("
			SELECT ID
			FROM $wpdb->posts
			WHERE post_status = 'publish'
			OR post_status = 'static'
		");

		if (count($post_ids)) {
			$result = $wpdb->query("
				UPDATE $wpdb->ak_popularity 
				SET comments = 0,
				trackbacks = 0,
				pingbacks = 0
				WHERE post_id IN (".implode(',', $wpdb->escape($post_ids)).")"
			);
			foreach ($post_ids as $post_id) {
				$this->populate_post_data($post_id);
			}
		}
		$this->recalculate_popularity();
		
		$cf_tab = '';
		if (isset($_GET['cf_tab'])) {
			$cf_tab = '&cf_tab=' . esc_js($_GET['cf_tab']);
		}
		header('Location: '.admin_url('/options-general.php?page='.basename(AKPC_FILE, '.php').'&updated=true'.$cf_tab.'&message=Feedback%20Recounted'));
		die();
	}
	
	function options_form() {
			$temp = new ak_popularity_contest;
			$yes_no = array(
				'show_pop',
				'show_help',
				'ignore_authors',
			);
			foreach ($yes_no as $key) {
				$var = $key.'_options';
				$$var = '
					<option value="1" '.selected($this->$key, '1', false).'>'.__('Yes', 'popularity-contest').'</option>
					<option value="0" '.selected($this->$key, '0', false).'>'.__('No', 'popularity-contest').'</option>
				';
			}
	echo('
<div id="cf" class="wrap">
	<div id="cf-header">
			');
			CF_Admin::admin_header(__('Popularity Contest Options', 'popularity-contest'), 'Popularity Contest', AKPC_VERSION, 'popularity-contest');
			CF_Admin::admin_tabs(array(__('Settings', 'popularity-contest'), __('Popularity Values', 'popularity-contest'), __('Reset Counters', 'popularity-contest'), __('Usage', 'popularity-contest')) );
			echo('
	</div>
	<div class="cf-tab-content-1 cf-content cf-hidden">
		<form name="ak_popularity" action="'.admin_url('options-general.php').'" method="post" class="cf-elm-width-300">
			<fieldset class="cf-lbl-pos-left">
				<legend>'.__('General Settings', 'popularity-contest').'</legend>
				<div class="cf-elm-block">
					<label for="akpc_ignore_authors" class="cf-lbl-select">'.__('Ignore views by site authors:', 'popularity-contest').'</label>
					<select name="ignore_authors" id="akpc_ignore_authors" class="cf-elm-select">
					'.$ignore_authors_options.'
					</select>
				</div>
				<div class="cf-elm-block">
					<label for="akpc_show_pop" class="cf-lbl-select">'.__('Show popularity rank for posts:', 'popularity-contest').'</label>
					<select name="show_pop" id="akpc_show_pop" class="cf-elm-select">
					'.$show_pop_options.'
					</select>
				</div>
				<div class="cf-elm-block">
					<label for="akpc_show_help" class="cf-lbl-select">'.__('Show the [?] help link:', 'popularity-contest').'</label>
					<select name="show_help" id="akpc_show_help" class="cf-elm-select">
					'.$show_help_options.'
					</select>
				</div>
				<div class="cf-elm-block">
					<label for="searcher_names" class="cf-lbl-textarea">'.__('Search Engine Domains (space separated):', 'popularity-contest').'</label>
					<textarea name="searcher_names" id="searcher_names" rows="2" cols="50" class="cf-elm-textarea">'.htmlspecialchars($this->searcher_names).'</textarea>
				</div>
			</fieldset>						
			<fieldset>
				<legend>'.__('Post Types', 'popularity-contest').'</legend>
				<p>'.__('Which post types would you like to include in calculating and displaying popularity contest? Note that Popularity Contest will continue logging these post types even with them disabled in case you want to enable them in the future.', 'popularity-contest').'</p>
				<div class="cf-elm-block has-checkbox">
			');
			$args = array(
			  'public' => true,
			);
			$all_post_types = get_post_types($args, 'names');
			$ackb_post_types_array = explode(',', $this->post_types);
			foreach ($all_post_types as $post_type) {
				if (in_array($post_type, $ackb_post_types_array)) {
					$checked = 'checked="checked"';
				}
				else {
					$checked = '';
				}
				echo('
					<input type="checkbox" name="post_types['.$post_type.']" id="post_types['.$post_type.']" value="'.$post_type.'" class="cf-elm-checkbox" '.$checked.'/>
					<label for="post_types['.$post_type.']" class="cf-lbl-checkbox">'.$post_type.'</label>
									
								');
				}
			echo(' 
				</div>
			</fieldset>
			<input type="hidden" name="ak_action" value="update_popularity_values" />
			'.wp_nonce_field('akpc' , 'akpc_settings_nonce', true, false).' 
			'.wp_referer_field(false).'
			<p class="submit">
				<input type="submit" name="submit" value="'.__('Save Changes', 'popularity-contest').'" class="button-primary" /> 
			</p>
		</form>
	</div> <!--.cf-content-tab-1-->
						
	<div class="cf-tab-content-2 cf-content cf-hidden">
		<form name="ak_popularity" action="'.admin_url('options-general.php?cf_tab=2').'" method="post" class="cf-elm-width-300">
			<fieldset class="cf-lbl-pos-left">
				<p>'.__('Adjust the values below as you see fit. When you save the new options the <a href="index.php?page=popularity-contest.php"><strong>popularity rankings</strong></a> for your posts will be automatically updated to reflect the new values you have chosen.', 'popularity-contest').'</p>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="single_value" class="cf-lbl-text">'.__('Permalink Views:', 'popularity-contest').'</label>
					<input type="text" class="cf-elm-text" name="single_value" id="single_value" value="'.esc_attr($this->single_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->single_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="searcher_value" class="cf-lbl-text">'.__('Permalink Views from Search Engines:', 'popularity-contest').'</label>
					<input type="text" class="cf-elm-text" name="searcher_value" id="searcher_value" value="'.esc_attr($this->searcher_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->searcher_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="home_value" class="cf-lbl-text">'.__('Home Views:', 'popularity-contest').'</label>
					<input type="text" class="cf-elm-text" name="home_value" id="home_value" value="'.esc_attr($this->home_value).'" /> <span class="cf-elm-help">'.__("default:", 'popularity-contest').esc_html($temp->home_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="archive_value" class="cf-lbl-text">'.__('Archive Views:', 'popularity-contest').'</label>
					<input type="text" class="cf-elm-text" name="archive_value" id="archive_value" value="'.esc_attr($this->archive_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->archive_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="category_value" class="cf-lbl-text">'.__('Category Views:', 'popularity-contest').'</label>
					<input type="text" class="cf-elm-text" name="category_value" id="category_value" value="'.esc_attr($this->category_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->category_value).'</span>
				</div>	
				<div class="cf-elm-block cf-elm-width-50">
					<label for="tag_value" class="cf-lbl-text">'.__('Tag Views:', 'popularity-contest').'</label>	
					<input type="text" class="cf-elm-text" name="tag_value" id="tag_value" value="'.esc_attr($this->tag_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->tag_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="feed_value" class="cf-lbl-text">'.__('Feed Views (full content only):', 'popularity-contest').'</label></th> 
					<input type="text" class="cf-elm-text" name="feed_value" id="feed_value" value="'.esc_attr($this->feed_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->feed_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="comment_value" class="cf-lbl-text">'.__('Comments:', 'popularity-contest').'</label></th> 
					<input type="text" class="cf-elm-text" name="comment_value" id="comment_value" value="'.esc_attr($this->comment_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->comment_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="pingback_value" class="cf-lbl-text">'.__('Pingbacks:', 'popularity-contest').'</label>
					<input type="text" class="cf-elm-text" name="pingback_value" id="pingback_value" value="'.esc_attr($this->pingback_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->pingback_value).'</span>
				</div>
				<div class="cf-elm-block cf-elm-width-50">
					<label for="trackback_value" class="cf-lbl-text">'.__('Trackbacks:', 'popularity-contest').'</label></th> 
					<input type="text" class="cf-elm-text" name="trackback_value" id="trackback_value" value="'.esc_attr($this->trackback_value).'" /> <span class="cf-elm-help">'.__("default: ", 'popularity-contest').esc_html($temp->trackback_value).'</span>
				</div>

				<h3>'.__('Example', 'popularity-contest').'</h3>
					<ul>
						<li>'.__('Post #1 receives 11 Home Page Views (11 * 2 = 22), 6 Permalink Views (6 * 10 = 60) and 3 Comments (3 * 20 = 60) for a total value of: <strong>142</strong>', 'popularity-contest').'</li>
						<li>'.__('Post #2 receives 7 Home Page Views (7 * 2 = 14), 10 Permalink Views (10 * 10 = 100), 7 Comments (7 * 20 = 140) and 3 Trackbacks (3 * 80 = 240) for a total value of: <strong>494</strong>', 'popularity-contest').'</li>
					</ul>
			</fieldset>
			<input type="hidden" name="ak_action" value="update_popularity_values" />
			'.wp_nonce_field('akpc' , 'akpc_settings_nonce', true, false).' 
			'.wp_referer_field(false).'
			<p class="submit">
				<input type="submit" name="submit" value="'.__('Save Changes', 'popularity-contest').'" class="button-primary" /> 
			</p>
		</form>
	</div> <!--.cf-tab-content-2-->
						
	<div class="cf-tab-content-3 cf-content cf-hidden">
		<p>Pressing the button below will set the Comment, Pinkback and Trackback counts for your posts\' popularity then recounts them.</p>
		<form name="ak_popularity" action="'.admin_url('options-general.php?cf_tab=3').'" method="post">
			<input type="hidden" name="ak_action" value="recount_feedback" />
			'.wp_nonce_field('akpc' , 'akpc_recount_nonce', true, false).' 
			'.wp_referer_field(false).'
			<p class="submit">
				<input type="submit" name="recount" value="'.__('Reset Comments/Trackback/Pingback Counts', 'popularity-contest').'" />
			</p>
		</form>
	</div>
		');
		echo('
	<div class="cf-tab-content-4 cf-content cf-hidden">
		<div id="akpc_template_tags">
			<h3>'.__('Shortcodes', 'popularity-contest').'</h3>
			<p>
				<code>[akpc_the_popularity]</code> Displays the popularity a current post.
			</p>
			
			<p>
				<code>[akpc_most_popular limit="10"]</code> displays a list (of size 10) of your most popular posts.
			</p>
			<div class="cf-elm-block">
			</div>
			<h3>'.__('Template Tags', 'popularity-contest').'</h3>
			<dl>
				<dt><code>akpc_the_popularity()</code></dt>
				<dd>
					<p>'.__('Put this tag within <a href="http://codex.wordpress.org/The_Loop">The Loop</a> to show the popularity of the post being shown. The popularity is shown as a percentage of your most popular post. For example, if the popularity total for Post #1 is 500 and your popular post has a total of 1000, this tag will show a value of <strong>50%</strong>.', 'popularity-contest').'</p>
					<p>Example:</p> 
					<ul>
						<li><code>&lt;?php if (function_exists(\'akpc_the_popularity\')) { akpc_the_popularity(); } ?></code></li>
					</ul>
				</dd>
				<dt><code>akpc_most_popular($limit = 10, $before = &lt;li>, $after = &lt;/li>)</code></dt>
				<dd>
					<p>'.__('Put this tag outside of <a href="http://codex.wordpress.org/The_Loop">The Loop</a> (perhaps in your sidebar?) to show a list (like the archives/categories/links list) of your most popular posts. All arguments are optional, the defaults are included in the example above.', 'popularity-contest').'</p>
					<p>Examples:</p> 
					<ul>
						<li><code>&lt;?php if (function_exists(\'akpc_most_popular\')) { akpc_most_popular(); } ?></code></li>
						<li><code>
							&lt;?php if (function_exists(\'akpc_most_popular\')) { ?><br />
							&lt;li>&lt;h2>Most Popular Posts&lt;/h2><br />
							&nbsp;&nbsp;	&lt;ul><br />
							&nbsp;&nbsp;	&lt;?php akpc_most_popular(); ?><br />
							&nbsp;&nbsp;	&lt;/ul><br />
							&lt;/li><br />
							&lt;?php } ?>
						</code></li>
					</ul>
				</dd>
				<dt><code>akpc_most_popular_in_cat($limit = 10, $before = &lt;li>, $after = &lt;/li>, $cat_ID = current category)</code></dt>
				<dd>
					<p>'.__('Put this tag outside of <a href="http://codex.wordpress.org/The_Loop">The Loop</a> (perhaps in your sidebar?) to show a list of the most popular posts in a specific category. You may want to use this on category archive pages. All arguments are', 'popularity-contest').'</p>
					<p>Examples:</p> 
					<ul>
						<li><code>&lt;?php if (function_exists(\'akpc_most_popular_in_cat\')) { akpc_most_popular_in_cat(); } ?></code></li>
						<li><code>&lt;php if (is_category() && function_exists(\'akpc_most_popular_in_cat\')) { akpc_most_popular_in_cat(); } ?></code></li>
						<li><code>
							&lt;?php if (is_category() && function_exists(\'akpc_most_popular_in_cat\')) { ?><br />
							&lt;li>&lt;h2>Most Popular in \'&lt;?php single_cat_title(); ?>\'&lt;/h2><br />
							&nbsp;&nbsp;	&lt;ul><br />
							&nbsp;&nbsp;	&lt;?php akpc_most_popular_in_cat(); ?><br />
							&nbsp;&nbsp;	&lt;/ul><br />
							&lt;/li><br />
							&lt;?php } ?>
						</code></li>
					</ul>
				</dd>
				<dt><code>akpc_most_popular_in_month($limit, $before, $after, $m = YYYYMM)</code></dt>
				<dd>
					<p>'.__('Put this tag outside of <a href="http://codex.wordpress.org/The_Loop">The Loop</a> (perhaps in your sidebar?) to show a list of the most popular posts in a specific month. You may want to use this on monthly archive pages.', 'popularity-contest').'</p>
					<p>Examples:</p> 
					<ul>
						<li><code>&lt;?php if (function_exists(\'akpc_most_popular_in_month\')) { akpc_most_popular_in_month(); } ?></code></li>
						<li><code>&lt;php if (is_archive() && is_month() && function_exists(\'akpc_most_popular_in_month\')) { akpc_most_popular_in_month(); } ?></code></li>
						<li><code>
							&lt;?php if (is_archive() && is_month() && function_exists(\'akpc_most_popular_in_month\')) { ?><br />
							&lt;li>&lt;h2>Most Popular in &lt;?php the_time(\'F, Y\'); ?>&lt;/h2><br />
							&nbsp;&nbsp;	&lt;ul><br />
							&nbsp;&nbsp;	&lt;?php akpc_most_popular_in_month(); ?><br />
							&nbsp;&nbsp;	&lt;/ul><br />
							&lt;/li><br />
							&lt;?php } ?>
						</code></li>
					</ul>
				</dd>
				<dt><code>akpc_most_popular_in_last_days($limit, $before, $after, $days = 45)</code></dt>
				<dd>
					<p>'.__('Put this tag outside of <a href="http://codex.wordpress.org/The_Loop">The Loop</a> (perhaps in your sidebar?) to show a list of the most popular posts in the last (your chosen number, default = 45) days.', 'popularity-contest').'</p>
					<p>Examples:</p> 
					<ul>

						<li><code>&lt;?php if (function_exists(\'akpc_most_popular_in_last_days\')) { akpc_most_popular_in_last_days(); } ?></code></li>
						<li><code>
							&lt;?php if (function_exists(\'akpc_most_popular_in_last_days\')) { ?><br />
							&lt;li>&lt;h2>Recent Popular Posts&lt;/h2><br />
							&nbsp;&nbsp;	&lt;ul><br />
							&nbsp;&nbsp;	&lt;?php akpc_most_popular_in_last_days(); ?><br />
							&nbsp;&nbsp;	&lt;/ul><br />
							&lt;/li><br />
							&lt;?php } ?>
						</code></li>
					</ul>
				</dd>
			</dl>
		</div>
	</div><!--.cf-tab-content-4 -->
</div><!--#cf -->
		');
		CF_Admin::callouts('popularity-contest');	
	}
	
	function get_additional_sql($clause_type = "WHERE") {
		$sql = $this->get_post_types_sql($clause_type);
		$sql .= $this->get_excluded_posts_sql("AND");
		return $sql;
	}
	
	function get_excluded_posts_sql($clause_type = "WHERE") {
		global $wpdb;
		$posts = akpc_posts_to_exclude();
		if (!empty($posts)) {
			return ($clause_type . " pop.post_id NOT IN (".implode(',', $wpdb->escape($posts)).") ");	
		}
		return '';
	}

	function get_post_types_sql($clause_type = "WHERE") {	
		global $wpdb;
		if (!empty($this->post_types)) {
			$post_types = ('\''.str_replace(',', '\',\'', $wpdb->escape($this->post_types)).'\''); 
			return $clause_type . " p.post_type IN ($post_types) ";
		}
		return '';
	}
	
	function get_popular_posts($type = 'popular', $limit = 25, $custom = array()) {
		global $wpdb;
		$items = array();
		if (substr($type, 0, 9) == 'post_type') {
			$additional_sql = $wpdb->prepare("AND post_type = %s", substr($type, 10, strlen($type)));
			$additional_sql .= $this->get_excluded_posts_sql('AND');
		} 
		else {
			$post_types_and_exclusion = $this->get_additional_sql("AND");

			// Limit the number of days to search for popular items
			$date_limit = '';

			if (isset($custom['days']) && $custom['days'] > 0 && isset($custom['offset'])) {
				if (!isset($custom['compare'])) {
					$custom['compare'] = '>';
				}
				$date_limit = $wpdb->escape("
					AND DATE_ADD(p.post_date, INTERVAL ".intval($custom['days'])." DAY) {$custom['compare']} DATE_ADD(NOW(), INTERVAL ".intval($custom['offset'])." DAY)					
				");
			}
				
			// Gather all of the addition limits to the query, and filter in any additional needed
			$additional_sql = $post_types_and_exclusion.$date_limit;
		}

		// Build arguments so filter knows exactly what should be filtered
		$args = array(
			'type' => $type,
			'limit' => $limit,
			'custom' => $custom
		);
			
		$additional_sql = apply_filters('get_popular_posts_additional', $additional_sql, $args);
		$limit = intval($limit);
		switch($type) {
			case 'category':
				$temp = "
					SELECT p.ID AS ID, p.post_title AS post_title, pop.total AS total
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					LEFT JOIN $wpdb->term_relationships tr
					ON p.ID = tr.object_id
					LEFT JOIN $wpdb->term_taxonomy tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id
					WHERE tt.term_id = ".$custom['cat_ID']."
					AND p.post_status = 'publish'
					$additional_sql
					ORDER BY pop.total DESC
					LIMIT $limit
				";
				break;
			case 'tag':
				$temp = "
					SELECT p.ID AS ID, p.post_title AS post_title, pop.total AS total
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					LEFT JOIN $wpdb->term_relationships tr
					ON p.ID = tr.object_id
					LEFT JOIN $wpdb->term_taxonomy tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id
					WHERE tt.term_id = ".$custom['term_id']."
					AND p.post_status = 'publish'
					$additional_sql
					ORDER BY pop.total DESC
					LIMIT $limit
				";
				break;
			case 'category_popularity':
				$temp = "
					SELECT t.term_id, t.name, AVG(pop.total) AS avg
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					LEFT JOIN $wpdb->term_relationships tr
					ON p.ID = tr.object_id
					LEFT JOIN $wpdb->term_taxonomy tt
					ON tr.term_taxonomy_id = tt.term_taxonomy_id
					LEFT JOIN $wpdb->terms t
					ON tt.term_id = t.term_id
					WHERE tt.taxonomy = 'category'
					$additional_sql
					GROUP BY t.term_id
					ORDER BY avg DESC
					LIMIT $limit
				";
				break;
			case 'tag_popularity':
				$temp = "
					SELECT t.term_id, t.name, AVG(pop.total) AS avg
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					LEFT JOIN $wpdb->term_relationships tr
					ON p.ID = tr.object_id
					LEFT JOIN $wpdb->term_taxonomy tt
					ON tr.term_taxonomy_id = tt.term_taxonomy_id
					LEFT JOIN $wpdb->terms t
					ON tt.term_id = t.term_id
					WHERE tt.taxonomy = 'post_tag'
					$additional_sql
					GROUP BY t.term_id
					ORDER BY avg DESC
					LIMIT $limit
				";
				break;
			case 'category_tag_popularity':
				// This report type will gather all categories and tags and return the most popular 
				$temp = "
					SELECT t.term_id, t.name, AVG(pop.total) AS avg, tt.taxonomy as taxonomy
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					LEFT JOIN $wpdb->term_relationships tr
					ON p.ID = tr.object_id
					LEFT JOIN $wpdb->term_taxonomy tt
					ON tr.term_taxonomy_id = tt.term_taxonomy_id
					LEFT JOIN $wpdb->terms t
					ON tt.term_id = t.term_id
					WHERE 1
					AND (tt.taxonomy = 'category' OR tt.taxonomy = 'post_tag')
					$additional_sql
					GROUP BY t.term_id
					ORDER BY avg DESC
					LIMIT $limit
				";
				break;
			case 'year':
				$temp = "
					SELECT MONTH(p.post_date) AS month, SUM(pop.total) AS sum
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					WHERE YEAR(p.post_date) = '".$custom['y']."'
					AND p.post_status = 'publish'
					$additional_sql
					GROUP BY month
					ORDER BY sum DESC
				";
				break;
			case 'year_average':
				$temp = "
					SELECT MONTH(p.post_date) AS month, AVG(pop.total) AS avg
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					WHERE YEAR(p.post_date) = '".$custom['y']."'
					AND p.post_status = 'publish'
					$additional_sql
					GROUP BY month
					ORDER BY avg DESC
				";
				break;
			case 'views_wo_feedback':
				$temp = "
					SELECT p.ID AS ID, p.post_title AS post_title, pop.total AS total
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					WHERE pop.comments = 0
					AND pop.pingbacks = 0
					AND pop.trackbacks = 0
					AND p.post_status = 'publish'
					$additional_sql
					ORDER BY pop.total DESC
					LIMIT $limit
				";
				break;
			case 'most_feedback':
				// in progress, should probably be combination of comment, pingback & trackback scores
				$temp = "
					SELECT p.ID, p.post_title, p.comment_count 
					FROM $wpdb->posts p 
					LEFT JOIN $wpdb->ak_popularity pop ON p.ID = pop.post_id 
					WHERE p.post_status = 'publish'
					AND p.comment_count > 0
					$additional_sql
					ORDER BY p.comment_count DESC 
					LIMIT $limit;
				";
				break;
			case 'date':
				$temp = "
					SELECT p.ID AS ID, p.post_title AS post_title, pop.total AS total
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					WHERE p.post_status = 'publish'
					$additional_sql
					ORDER BY pop.total DESC
					LIMIT $limit
				";
				break;
			case 'most':
				$temp = "
					SELECT p.ID AS ID, p.post_title AS post_title, pop.{$custom['column']} AS {$custom['column']}
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					WHERE p.post_status = 'publish'
					$additional_sql
					ORDER BY pop.{$custom['column']} DESC
					LIMIT $limit
				";
				break;
			case 'popular_pages':
				$temp = "
					SELECT p.ID AS ID, p.post_title AS post_title, pop.single_views AS single_views
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					WHERE p.post_status = 'publish'
					AND p.post_type = 'page'
					$additional_sql 
					ORDER BY pop.single_views DESC
					LIMIT $limit
				";
			case 'popular':
			default :
				$temp = "
					SELECT p.ID AS ID, p.post_title AS post_title, pop.{$custom['column']} AS {$custom['column']}
					FROM $wpdb->posts p
					LEFT JOIN $wpdb->ak_popularity pop
					ON p.ID = pop.post_id
					WHERE p.post_status = 'publish'
					$additional_sql
					ORDER BY pop.{$custom['column']} DESC
					LIMIT $limit
				";
				break;
		}
		$items = $wpdb->get_results($temp);

		do_action('akpc_get_popular_posts',$items);
		
		if (count($items)) {
			return $items;
		}
		return false;
	}

	/**
	 * Show a popularity report
	 * @var string $type - type of report to show
	 * @var int $limit - num posts to show
	 * @var array $custom - pre-defined list of posts to show
	 * @var bool $hide_title - wether to echo the list title
	 */

	function show_report($type = 'popular', $limit = 10, $exclude_pages = 'DEPRECATED', $custom = array(), $before_title = '<h3>', $after_title = '</h3>', $hide_title = false) {
		global $wpdb;
		$query = '';
		$column = '';
		$list = '';
		$items = array();
		$rel = '';
		switch ($type) {
			case 'category':
				$title = $custom['cat_name'];
				$items = $this->get_popular_posts($type, $limit, $custom);
				$list = $this->report_list_items($items, $before = '<li>', $after = '</li>');
				break;
			case 'tag':
				$title = $custom['term_name'];
				$rel = sanitize_title($title);
				$items = $this->get_popular_posts($type, $limit, $custom);
				$list = $this->report_list_items($items, $before = '<li>', $after = '</li>');
				break;
			case 'pop_by_category':
				$cats = get_categories();
				if (count($cats)) {
					foreach ($cats as $cat) {
						$this->show_report('category', 10, 'DEPRECATED', array('cat_ID' => $cat->term_id, 'cat_name' => $cat->name));
					}
				}
				break;
			case 'pop_by_tag':
				$tags = maybe_unserialize(get_option('akpc_tag_reports'));
				if (is_array($tags) && count($tags)) {
					foreach ($tags as $tag) {
						$term = get_term_by('slug', $tag, 'post_tag');
						$this->show_report('tag', 10, 'DEPRECATED', array('term_id' => $term->term_id, 'term_name' => $term->name));
					}
				}
				break;
			case 'category_popularity':
				$title = __('Most Popular Categories', 'popularity-contest');
				$items = $this->get_popular_posts($type, $limit);
				if (is_array($items) && count($items)) {
					foreach ($items as $item) {
						$list .= '	
							<li>
								<span>'.$this->get_rank(ceil($item->avg)).'</span>
								<a href="'.get_category_link($item->term_id).'">'.esc_html($item->name).'</a>
							</li>'."\n";
					}
				}
				break;
			case 'tag_popularity':
				$title = __('Most Popular Tags', 'popularity-contest');
				$items = $this->get_popular_posts($type, $limit);
				if (is_array($items) && count($items)) {
					foreach ($items as $item) {
						$list .= '	
							<li>
								<span>'.$this->get_rank(ceil($item->avg)).'</span>
								<a href="'.get_tag_link($item->term_id).'">'.esc_html($item->name).'</a>
							</li>'."\n";
					}
				}
				break;
			case 'category_tag_popularity':
				$title = __('Most Popular Categories and Tags', 'popularity-contest');
				$items = $this->get_popular_posts($type, $limit);
				if (is_array($items) && count($items)) {
					foreach ($items as $item) {
						if ($item->taxonomy == 'category') {
							$url = get_category_link($item->term_id);
						}
						else {
							$url = get_tag_link($item->term_id);
						}
						$list .= '
							<li>
								<span>'.$this->get_rank(ceil($item->avg)).'</span>
								<a href="'.$url.'">'.esc_html($item->name).'</a>
							</li>'."\n";
					}
				}
				break;
			case 'year':	
				global $month;
				$title = $custom['y'].' '.__('Most Popular Months', 'popularity-contest');
				$items = $this->get_popular_posts($type, $limit, $custom);
				if (is_array($items) && count($items)) {
					$sum = 0;
					foreach ($items as $item) {
						$sum += $item->sum;
					}
					foreach ($items as $item) {
						$list .= '
							<li>
								<span>'.$this->get_rank($item->sum, $sum).'</span>
								'.esc_html($month[str_pad($item->month, 2, '0', STR_PAD_LEFT)]).'
							</li>'."\n";
					}
				}
				break;
			case 'month_popularity':
				$years = array();
				$years = $wpdb->get_results("
					SELECT DISTINCT YEAR(post_date) AS year
					FROM $wpdb->posts
					ORDER BY year DESC
				");
				$i = 2;
				if (count($years) > 0) {
					foreach ($years as $year) {
						$this->show_report('year', 10, 'DEPRECATED', array('y' => $year->year));
						if ($i == 3) {
							echo '<div class="clear"></div>';
							$i = 0;
						}
						$i++;
					}
				}
				break;
			case 'year_average':
				global $month;
				$title = $custom['y'].' '.__('Average by Month', 'popularity-contest');
				$items = $this->get_popular_posts($type, $limit, $custom);
				if (is_array($items) && count($items)) {
					$sum = 0;
					foreach ($items as $item) {
						$sum += $item->avg;
					}
					foreach ($items as $item) {
						$list .= '
							<li>
								<span>'.$this->get_rank($item->avg, $sum).'</span>
								'.esc_html($month[str_pad($item->month, 2, '0', STR_PAD_LEFT)]).'
							</li>'."\n";
					}
				}
				break;
			case 'month_average':
				$years = array();
				$years = $wpdb->get_results("
					SELECT DISTINCT YEAR(post_date) AS year
					FROM $wpdb->posts
					ORDER BY year DESC
				");
				$i = 2;
				if (count($years) > 0) {
					foreach ($years as $year) {
						$this->show_report('year_average', 10, 'DEPRECATED', array('y' => $year->year));
						if ($i == 3) {
							echo '<div class="clear"></div>';
							$i = 0;
						}
						$i++;
					}
				}
				break;
			case 'views_wo_feedback':
				$title = __('Views w/o Feedback', 'popularity-contest');
				$items = $this->get_popular_posts($type, $limit);
				$list = $this->report_list_items($items, $before = '<li>', $after = '</li>');
				break;
			case 'most_feedback':
				$query = 'sum';
				$column = 'pop.comments + pop.pingbacks + pop.trackbacks AS feedback';
				$title = __('Feedback', 'popularity-contest');
				break;
			case '365_plus':
				$offset = -365;
				$compare = '<';
				$title = __('Older Than 1 Year', 'popularity-contest');
				$items = $this->get_popular_posts('date', $limit, array('days' => $custom['days'], 'offset' => $offset, 'compare' => $compare));
				$list = $this->report_list_items($items, $before = '<li>', $after = '</li>');
				break;
			case 'last_30':
			case 'last_60':
			case 'last_90':
			case 'last_365':
			case 'last_n':
				$compare = '>';
				$offset = $days = '0';
				switch(str_replace('last_','',$type)) {
					case '30':
						$days = 30;
						$title = __('Last 30 Days', 'popularity-contest');
						break;
					case '60':
						$days = 60;
						$title = __('Last 60 Days', 'popularity-contest');
						break;
					case '90':
						$days = 90;
						$title = __('Last 90 Days', 'popularity-contest');
						break;
					case '365':
						$days = 365;
						$title = __('Last Year', 'popularity-contest');
						break;
					case 'n':
						$days = $custom['days'];
						if ($days == 1) {
							$title = __('Last Day', 'popularity-contest');
						}
						else {
							$title = sprintf(__('Last %s Days', 'popularity-contest'), $days);
						}
						break;
				}
				$items = $this->get_popular_posts('date', $limit, array('days' => $days, 'offset' => $offset, 'compare' => $compare));
				$list = $this->report_list_items($items, $before = '<li>', $after = '</li>');
				break;
			case 'most_feed_views':
			case 'most_home_views':
			case 'most_archive_views':
			case 'most_category_views':
			case 'most_tag_views':
			case 'most_single_views':
			case 'most_searcher_views':
			case 'most_comments':
			case 'most_pingbacks':
			case 'most_trackbacks':
				switch($type) {
					case 'most_feed_views':
						$query = 'most';
						$column = 'feed_views';
						$title = __('Feed Views', 'popularity-contest');
						break;
					case 'most_home_views':
						$query = 'most';
						$column = 'home_views';
						$title = __('Home Page Views', 'popularity-contest');
						break;
					case 'most_archive_views':
						$query = 'most';
						$column = 'archive_views';
						$title = __('Archive Views', 'popularity-contest');
						break;
					case 'most_category_views':
						$query = 'most';
						$column = 'category_views';
						$title = __('Category Views', 'popularity-contest');
						break;
					case 'most_tag_views':
						$query = 'most';
						$column = 'tag_views';
						$title = __('Tag Views', 'popularity-contest');
						break;
					case 'most_single_views':
						$query = 'most';
						$column = 'single_views';
						$title = __('Single Views', 'popularity-contest');
						break;
					case 'most_searcher_views':
						$query = 'most';
						$column = 'searcher_views';
						$title = __('Search Engine Traffic', 'popularity-contest');
						break;
					case 'most_comments':
						$query = 'most';
						$column = 'comments';
						$title = __('Comments', 'popularity-contest');
						break;
					case 'most_pingbacks':
						$query = 'most';
						$column = 'pingbacks';
						$title = __('Pingbacks', 'popularity-contest');
						break;
					case 'most_trackbacks':
						$query = 'most';
						$column = 'trackbacks';
						$title = __('Trackbacks', 'popularity-contest');
						break;
				}
				$items = $this->get_popular_posts('most', $limit, array('column' => $column));
				if (is_array($items) && count($items)) {
					foreach ($items as $item) {
						$list .= '
							<li>
								<span>'.$item->$column.'</span>
								<a href="'.get_permalink($item->ID).'">'.esc_html($item->post_title).'</a>
							</li>'."\n";
					}
				}
				else {
					$list = '<li>'.__('(none)', 'popularity-contest').'</li>';
				}
				break;
			case 'popular':
				$query = 'popular';
				$column = 'total';
				$title = __('Most Popular', 'popularity-contest');
				$items = $this->get_popular_posts($type, $limit, array('column' => $column));
				if (is_array($items) && count($items)) {
					foreach ($items as $item) {
						$list .= '
							<li>
								<span>'.$this->get_post_rank(null, $item->total).'</span>
								<a href="'.get_permalink($item->ID).'">'.esc_html($item->post_title).'</a>
							</li>'."\n";
					}
				}
				else {
					$list = '<li>'.__('(none)', 'popularity-contest').'</li>';
				}
				break;
			case 'post_types':
			default:
				$column = 'single_views';
				$title = ucwords($type).' '.__('Views', 'popularity-contest');
				$items = $this->get_popular_posts('post_type_'.$type, $limit, array('column' => $column));
				if (is_array($items) && count($items)) {
					foreach ($items as $item) {
						$list .= '
							<li>
								<span>'.$item->$column.'</span>
								<a href="'.get_permalink($item->ID).'">'.esc_html($item->post_title).'</a>
							</li>'."\n";
					}
				}
				else {
					$list = '<li>'.__('(none)', 'popularity-contest').'</li>';
				}
				break;
			
		}

		if (!empty($list)) {
			$reltpl='';
			if(!empty($rel)) $reltpl='rel="'.$rel.'"';
			$html = '
				<div class="akpc_report" '.$reltpl.'>
					'.($hide_title ? '' : $before_title.$title.$after_title).'
					<ol>
						'.$list.'
					</ol>
				</div>
				';
			echo apply_filters('akpc_show_report', $html, $items);
		}
	}

	function report_list_items($items, $before = '<li>', $after = '<li>') {
		if (!$items || !count($items)) {
			return false;
		}
		
		$html = '';
		foreach ($items as $item) {
			$html .= $before.
					 '<span>'.$this->get_post_rank(null, $item->total).' </span><a href="'.get_permalink($item->ID).'">'.esc_html($item->post_title).'</a>'.
					 $after;
		}
		return $html;
	}
	
	function show_report_extended($type = 'popular', $limit = 50) {
		global $wpdb, $post;
		$columns = array(
			'popularity' 	=> __('', 'popularity-contest'),
			'title'      	=> __('Title', 'popularity-contest'),
			'categories'	=> __('Categories', 'popularity-contest'),
			'type'			=> __('Post Type', 'popularity-contest'),
			'single_views'	=> __('Single', 'popularity-contest'),
			'searcher_views'=> __('Search', 'popularity-contest'),
			'category_views'=> __('Cat', 'popularity-contest'),
			'tag_views'		=> __('Tag', 'popularity-contest'),
			'archive_views'	=> __('Arch', 'popularity-contest'),
			'home_views'	=> __('Home', 'popularity-contest'),
			'feed_views'	=> __('Feed', 'popularity-contest'),
			'comments'		=> __('Com', 'popularity-contest'),
			'pingbacks'		=> __('Ping', 'popularity-contest'),
			'trackbacks'	=> __('Track', 'popularity-contest')
			
		);
?>
<div id="akpc_most_popular">
	<table width="100%" cellpadding="3" cellspacing="2"> 
		<tr>
<?php
		foreach ($columns as $column_display_name) {
?>
			<th scope="col"><?php echo $column_display_name; ?></th>
<?php
		}
?>
			</tr>
<?php
		$post_types_and_exclusion = $this->get_additional_sql("AND");
		$posts = $wpdb->get_results("
			SELECT p.*, pop.*
			FROM $wpdb->posts p
			LEFT JOIN $wpdb->ak_popularity pop
			ON p.ID = pop.post_id
			WHERE p.post_status = 'publish'
			$post_types_and_exclusion
			ORDER BY pop.total DESC
			LIMIT ".intval($limit)
		);
		if ($posts) {
			$bgcolor = '';
			$class = '';
			foreach ($posts as $post) {
				$class = ('alternate' == $class) ? '' : 'alternate';
?>
		<tr class='<?php echo $class; ?>'>
<?php
				foreach ($columns as $column_name => $column_display_name) {
					switch($column_name) {
						case 'popularity':
?>
				<td class="right"><?php $this->show_post_rank(null, $post->total); ?></td>
<?php
						break;
						case 'title':
?>
				<td><a href="<?php the_permalink(); ?>"><?php esc_html(the_title()) ?></a></td>
<?php
							break;
						case 'categories':
?>
				<td><?php the_category(','); ?></td>
<?php
							break;
						case 'type':
?>
				<td><?php echo(get_post_type($post->ID)); ?></td>
<?php
							break;
						case 'single_views':
?>
				<td class="right"><?php echo($post->single_views); ?></td>
<?php
							break;
						case 'searcher_views':
?>
				<td class="right"><?php echo($post->searcher_views); ?></td>
<?php
							break;
						case 'category_views':
?>
				<td class="right"><?php echo($post->category_views); ?></td>
<?php
							break;
						case 'tag_views':
?>
				<td class="right"><?php echo($post->tag_views); ?></td>
<?php
							break;
						case 'archive_views':
?>
				<td class="right"><?php echo($post->archive_views); ?></td>
<?php
							break;
						case 'home_views':
?>
				<td class="right"><?php echo($post->home_views); ?></td>
<?php
							break;
						case 'feed_views':
?>
				<td class="right"><?php echo($post->feed_views); ?></td>
<?php
							break;
						case 'comments':
?>
				<td class="right"><?php echo($post->comments); ?></td>
<?php
							break;
						case 'pingbacks':
?>
				<td class="right"><?php echo($post->pingbacks); ?></td>
<?php
							break;
						case 'trackbacks':
?>
				<td class="right"><?php echo($post->trackbacks); ?></td>
<?php
							break;
					}
				}
?>
		</tr> 
<?php
			}
		}
		else {
?>
	  <tr style='background-color: <?php echo $bgcolor; ?>'> 
		<td colspan="8"><?php _e('No posts found.', 'popularity-contest') ?></td> 
	  </tr> 
<?php
		} // end if ($posts)
?>
	</table> 
</div>
<?php
	}
	
	function view_stats($limit = 100) {
		global $wpdb, $post;
		echo('
<div id="cf" class="wrap ak_wrap">
	<div id="cf-header">
		');
		CF_Admin::admin_header(__('Most Popular', 'popularity-contest'), 'Popularity Contest', AKPC_VERSION, 'popularity-contest');
		echo('
	</div>
		');
		$this->show_report_extended('popular', 50);

		echo('
	<p id="akpc_options_link"><a href="'.admin_url('options-general.php?page='.basename(AKPC_FILE, '.php').'&cf_tab=2').'">'.__('Change Popularity Values', 'popularity-contest').'</a></p>

	<div class="pop_group">
		<h2>'.__('Date Range', 'popularity-contest').'</h2>
		');

		$this->show_report('last_30');
		$this->show_report('last_60');
		$this->show_report('last_90');
		$this->show_report('last_365');
		$this->show_report('365_plus');

		echo('
	</div>
	<div class="clear"></div>
	<div class="pop_group">
		<h2>'.__('Views', 'popularity-contest').'</h2>
		');

		$this->show_report('most_single_views');

		$post_types = explode(',',$this->post_types);
		foreach ($post_types as $post_type) {
			$type = $post_type;
			$this->show_report($type);
		}
		$this->show_report('most_searcher_views');
		$this->show_report('most_category_views');
		$this->show_report('most_tag_views');
		$this->show_report('most_archive_views');
		$this->show_report('most_home_views');
		$this->show_report('most_feed_views');

		echo('
	</div>
	<div class="clear"></div>
	<div class="pop_group">
		<h2>'.__('Feedback', 'popularity-contest').'</h2>
		');

		$this->show_report('most_comments');
		$this->show_report('most_pingbacks');
		$this->show_report('most_trackbacks');
		$this->show_report('views_wo_feedback');

		echo('
	</div>
	<div class="clear"></div>
	<h2>'.__('Averages', 'popularity-contest').'</h2>
		');
		$this->show_report('category_tag_popularity');
		$this->show_report('category_popularity');
		$this->show_report('tag_popularity');
		$this->show_report('month_popularity');
		$this->show_report('month_average');

		echo('
	<div class="clear"></div>
	<div class="pop_group" id="akpc_tag_reports">
		<h2>'.__('Tags', 'popularity-contest').'
			<form action="'.site_url('index.php').'" method="post" id="akpc_report_tag_form">
				<label for="akpc_tag_add">'.__('Add report for tag:', 'popularity-contest').'</label>
				<input type="text" name="akpc_tag_add" id="akpc_tag_add" value="" />
				<input type="submit" name="submit_button" value="'.__('Add', 'popularity-contest').'" />
				<input type="hidden" name="ak_action" value="akpc_add_tag" />
				<span class="akpc_status">'.__('Adding tag...'. 'popularity-contest').'</span>
			</form>
		</h2>
		');

		$this->show_report('pop_by_tag');

		echo('
	<div class="akpc_padded none">'.__('No tag reports chosen.', 'popularity-contest').'</div>
	</div>
	<div class="clear"></div>
	<div class="pop_group">
		<h2>'.__('Categories', 'popularity-contest').'</h2>
		');

		$this->show_report('pop_by_category');

		print('
	</div>
	<div class="clear"></div>
</div><!--#cf-->
		');
		CF_Admin::callouts('popularity-contest');
?>
<script type="text/javascript">
akpc_flow_reports = function() {
	var reports = jQuery('div.akpc_report').css('visibility', 'hidden');
	jQuery('div.akpc-auto-insert').remove();
	var akpc_reports_per_row = Math.floor(jQuery('div.pop_group').width()/250);
	jQuery('div.pop_group').each(function() {
		var i = 1;
		jQuery(this).find('div.akpc_report').each(function() {
			if (i % akpc_reports_per_row == 0) {
				jQuery(this).after('<div class="clear akpc-auto-insert"></div>');
			}
			i++;
		});
	});
	akpc_tag_reports_none();
	reports.css('visibility', 'visible');
}
akpc_tag_report_remove_links = function() {
	jQuery('#akpc_tag_reports a.remove').remove();
	jQuery('#akpc_tag_reports .akpc_report').each(function() {
		jQuery(this).prepend('<a href="<?php echo site_url('index.php?ak_action=akpc_remove_tag&tag='); ?>' + jQuery(this).attr('rel') + '" class="remove"><?php _e('[X]', 'popuarity-contest'); ?></a>');
	});
	jQuery('#akpc_tag_reports a.remove').click(function() {
		report = jQuery(this).parents('#akpc_tag_reports .akpc_report');
		report.html('<div class="akpc_padded"><?php _e('Removing...', 'popularity-contest'); ?></div>');
		jQuery.post(
			'<?php echo site_url('index.php'); ?>',
			{
				'ak_action': 'akpc_remove_tag',
				'tag': report.attr('rel')
			},
			function(response) {
				report.remove();
				akpc_flow_reports();
			},
			'html'
		);
		return false;
	});
}
akpc_tag_reports_none = function() {
	none_msg = jQuery('#akpc_tag_reports .none');
	if (jQuery('#akpc_tag_reports .akpc_report').size()) {
		none_msg.hide();
	}
	else {
		none_msg.show();
	}
}
jQuery(function($) {
	akpc_flow_reports();
	akpc_tag_report_remove_links();
	$('#akpc_tag_add').suggest( 'admin-ajax.php?action=ajax-tag-search&tax=post_tag', { delay: 500, minchars: 2, multiple: true, multipleSep: ", " } );
	$('#akpc_report_tag_form').submit(function() {
		var tag = $('#akpc_tag_add').val();
		if (tag.length > 0) {
			var add_button = $(this).find('input[type="submit"]');
			var saving_msg = $(this).find('span.akpc_status');
			add_button.hide();
			saving_msg.show();
			$.post(
				'<?php echo site_url('index.php'); ?>',
				{
					'ak_action': 'akpc_add_tag',
					'tag': tag
				},
				function(response) {
					$('#akpc_tag_add').val('');
					$('#akpc_tag_reports').append(response);
					akpc_flow_reports();
					akpc_tag_report_remove_links()
					saving_msg.hide();
					add_button.show();
				}, 
				'html'
			);
		}
		return false;
	});
});
jQuery(window).bind('resize', akpc_flow_reports);
</script>
<?php
	}
	
	function get_post_total($post_id) {
		if (!isset($this->current_posts['id_'.$post_id])) {
			$this->get_current_posts(array($post_id));
		}
		return $this->current_posts['id_'.$post_id];
	}
	
	function get_rank($item, $total = null) {
		if (is_null($total)) {
			$total = $this->top_rank();
		}
		return ceil(($item/$total) * 100).'%';
	}

	function get_post_rank($post_id = null, $total = -1) {
		if (count($this->top_ranked) == 0) {
			$this->get_top_ranked();
		}
		if ($total > -1 && !$post_id) {
			return ceil(($total/$this->top_rank()) * 100).'%';
		}
		if (isset($this->top_ranked['id_'.$post_id])) {
			$rank = $this->top_ranked['id_'.$post_id];
		}
		else {
			$rank = $this->get_post_total($post_id);
		}
		$show_help = apply_filters('akpc_show_help', $this->show_help, $post_id);
		if ($show_help) {
			$suffix = ' <span class="akpc_help">[<a href="http://alexking.org/projects/wordpress/popularity-contest" title="'.__('What does this mean?', 'popularity-contest').'">?</a>]</span>';
		}
		else {
			$suffix = '';
		}
		if (isset($rank) && $rank != false) {
			return __('Popularity:', 'popularity-contest').' '.$this->get_rank($rank).$suffix;
		}
		else {
			return __('Popularity:', 'popularity-contest').' '.__('unranked', 'popularity-contest').$suffix;
		}
	}
	
	function show_post_rank($post_id = -1, $total = -1) {
		print($this->get_post_rank($post_id, $total));
	}
	
	function top_rank() {
		if (count($this->top_ranked) == 0) {
			$this->get_top_ranked();
		}
		foreach ($this->top_ranked as $id => $rank) {
			$top = $rank;
			break;
		}
		// handle edge case - div by zero
		if (intval($top) == 0) {
			$top = 1;
		}
		return $top;
	}

	function get_current_posts($post_ids = array()) {
		global $wpdb, $wp_query;
		$posts = $wp_query->get_posts();
		$ids = array();
		foreach ($posts as $post) {
			$ids[] = $post->ID;
		}

		// Merge, remove duplicates, reassign index values.
		$ids = array_values( array_unique( array_merge($ids, $post_ids)));

		if (count($ids)) {
			$result = $wpdb->get_results("
				SELECT post_id, total
				FROM $wpdb->ak_popularity
				WHERE post_id IN (".implode(',', $ids).")
			");
			
			if (count($result)) {
				foreach ($result as $data) {
					$this->current_posts['id_'.$data->post_id] = $data->total;
				}
			}
		}
		return true;
	}
	
	function get_top_ranked() {
		global $wpdb;

		$post_types_and_exclusion = $this->get_additional_sql("WHERE");
		$result = $wpdb->get_results("
			SELECT pop.post_id AS post_id, pop.total AS total
			FROM $wpdb->ak_popularity pop
			LEFT JOIN $wpdb->posts p
			ON pop.post_id = p.ID
			$post_types_and_exclusion
			ORDER BY total DESC
			LIMIT 10
		");

		if (!count($result)) {
			return false;
		}
		
		foreach ($result as $data) {
			$this->top_ranked['id_'.$data->post_id] = $data->total;
		}
		
		return true;
	}
	
	function show_top_ranked($limit, $before, $after) {
		if ($posts=$this->get_top_ranked_posts($limit)) {
			foreach ($posts as $post) {
				if ($this->show_pop) {
					$rank = $this->get_rank($this->get_post_total($post->ID));
				}
    			echo(
    				$before.$rank.' <a href="'.get_permalink($post->ID).'">'
    				.$post->post_title.'</a>'.$after
    			);
			}
		}
		else {
			echo $before.__('(none)', 'popularity-contest').$after;
		}
	}

	function get_top_ranked_posts($limit) {
		global $wpdb;
		$temp = $wpdb;
		$additional_sql = $this->get_additional_sql("AND");
		$join = apply_filters('posts_join', '');
		$where = apply_filters('posts_where', '');
		$groupby = apply_filters('posts_groupby', '');
		if (!empty($groupby)) {
			$groupby = ' GROUP BY '.$groupby;
		}
		else {
			$groupby = ' GROUP BY p.ID ';
		}
				
		$posts = $wpdb->get_results("
			SELECT p.ID, p.post_title
			FROM $wpdb->posts p 
			LEFT JOIN $wpdb->ak_popularity pop 
			ON p.ID = pop.post_id 
			$join
			WHERE p.post_status = 'publish'
			AND p.post_date < NOW()
			$additional_sql
			$where
			$groupby
			ORDER BY pop.total DESC
			LIMIT ".intval($limit)
		);

		$wpdb = $temp;

		return $posts;
	}
	
	function show_top_ranked_in_cat($limit, $before, $after, $cat_ID = '') {
		if (empty($cat_ID) && is_category()) {
			global $cat;
			$cat_ID = $cat;
		}
		if (empty($cat_ID)) {
			return;
		}
		global $wpdb;
		$temp = $wpdb;
		$additional_sql = $this->get_additional_sql("AND");
		$join = apply_filters('posts_join', '');
		$where = apply_filters('posts_where', '');
		$groupby = apply_filters('posts_groupby', '');
		if (!empty($groupby)) {
			$groupby = ' GROUP BY '.$groupby;
		}
		else {
			$groupby = ' GROUP BY p.ID ';
		}
		$posts = $wpdb->get_results("
			SELECT p.ID, p.post_title
			FROM $wpdb->posts p
			LEFT JOIN $wpdb->term_relationships tr
			ON p.ID = tr.object_id
			LEFT JOIN $wpdb->term_taxonomy tt
			ON tr.term_taxonomy_id = tt.term_taxonomy_id
			LEFT JOIN $wpdb->ak_popularity pop
			ON p.ID = pop.post_id
			$join
			WHERE tt.term_id = '".intval($cat_ID)."'
			AND tt.taxonomy = 'category'
			AND p.post_status = 'publish'
			AND p.post_type = 'post' 
			AND p.post_date < NOW()
			$additional_sql
			$where
			$groupby
			ORDER BY pop.total DESC
			LIMIT ".intval($limit)
		);
		if ($posts) {
			foreach ($posts as $post) {
    			echo(
    				$before.'<a href="'.get_permalink($post->ID).'">'
    				.$post->post_title.'</a>'.$after
    			);
			}
		}
		else {
			print($before.__('(none)', 'popularity-contest').$after);
		}
		$wpdb = $temp;
	}
	
	function show_top_ranked_in_month($limit, $before, $after, $m = '') {
		if (empty($m) && is_archive()) {
			global $m;
		}
		if (empty($m)) {
			global $post;
			$m = get_the_time('Ym');
		}
		if (empty($m)) {
			return;
		}
		$year = substr($m, 0, 4);
		$month = substr($m, 4, 2);
		global $wpdb;
		$temp = $wpdb;
		$additional_sql = $this->get_additional_sql("AND");
		$join = apply_filters('posts_join', '');
		$where = apply_filters('posts_where', '');
		$groupby = apply_filters('posts_groupby', '');
		if (!empty($groupby)) {
			$groupby = ' GROUP BY '.$groupby;
		}
		else {
			$groupby = ' GROUP BY p.ID ';
		}

		$posts = $wpdb->get_results("
			SELECT p.ID, p.post_title
			FROM $wpdb->posts
			LEFT JOIN $wpdb->ak_popularity pop
			ON p.ID = pop.post_id
			$join
			WHERE YEAR(p.post_date) = '$year'
			AND MONTH(p.post_date) = '$month'
			AND p.post_status = 'publish'
			AND p.post_date < NOW()
			$additional_sql
			$where
			$groupby
			ORDER BY pop.total DESC
			LIMIT ".intval($limit)
		);
		if ($posts) {
			foreach ($posts as $post) {
    			print(
    				$before.'<a href="'.get_permalink($post->ID).'">'
    				.$post->post_title.'</a>'.$after
    			);
			}
		}
		else {
			print($before.__('(none)', 'popularity-contest').$after);
		}
		$wpdb = $temp;
	}

	function show_top_ranked_in_last_days($limit, $before, $after, $days = 45) {
		global $wpdb;
		$temp = $wpdb;
		$additional_sql = $this->get_additional_sql("AND");
		$join = apply_filters('posts_join', '');
		$where = apply_filters('posts_where', '');
		$groupby = apply_filters('posts_groupby', '');
		if (!empty($groupby)) {
			$groupby = ' GROUP BY '.$groupby;
		}
		else {
			$groupby = ' GROUP BY p.ID ';
		}

		$offset = 0;
		$compare = '>';

		$posts = $wpdb->get_results("
			SELECT p.ID, p.post_title
			FROM $wpdb->posts
			LEFT JOIN $wpdb->ak_popularity pop
			ON p.ID = pop.post_id
			$join
			WHERE DATE_ADD(p.post_date, INTERVAL $days DAY) $compare DATE_ADD(NOW(), INTERVAL $offset DAY)
			AND p.post_status = 'publish'
			AND p.post_date < NOW()
			$additional_sql
			$where
			$groupby
			ORDER BY pop.total DESC
			LIMIT ".intval($limit)
		);
		if ($posts) {
			foreach ($posts as $post) {
    			print(
    				$before.'<a href="'.get_permalink($post->ID).'">'
    				.$post->post_title.'</a>'.$after
    			);
			}
		}
		else {
			print($before.__('(none)', 'popularity-contest').$after);
		}
		$wpdb = $temp;
	}

}

// -- "HOOKABLE" FUNCTIONS
function akpc_admin_init() {
	global $pagenow;
	if (!empty($_GET['page']) && $_GET['page'] == basename(__FILE__, '.php')) {
		CF_Admin::load_js();
		CF_Admin::load_css();
		wp_enqueue_script('suggest');
	}
	if ($pagenow == 'widgets.php') {
		wp_enqueue_script('akpc_widget_js', admin_url('?ak_action=akpc_js'), array('jquery'));
	}
}
add_action('admin_init', 'akpc_admin_init');

function akpc_upgrade() {
	global $akpc;
	if ($akpc->needs_upgrade()) {
		$akpc->upgrade();
	}
}
add_action('admin_init', 'akpc_upgrade');

function akpc_init() {
	global $wpdb, $akpc;

	$wpdb->ak_popularity = $wpdb->prefix.'ak_popularity';
	$wpdb->ak_popularity_options = $wpdb->prefix.'ak_popularity_options';

	$akpc = new ak_popularity_contest;
	$akpc->get_settings();
}
add_action('init', 'akpc_init', 1);

function akpc_view($content) {
	global $akpc;
	$akpc->record_view();
	return $content;
}

function akpc_feedback_comment() {
	global $akpc;
	$akpc->record_feedback('comment');
}
add_action('comment_post', 'akpc_feedback_comment');

function akpc_comment_status($comment_id, $status = 'approved') {
	global $akpc;
	$akpc->edit_feedback($comment_id, 'status', $status);
}
add_action('wp_set_comment_status', 'akpc_comment_status', 10, 2);

function akpc_comment_delete($comment_id) {
	global $akpc;
	$akpc->edit_feedback($comment_id, 'delete');
}
add_action('delete_comment', 'akpc_comment_delete');

function akpc_feedback_pingback() {
	global $akpc;
	$akpc->record_feedback('pingback');
}
add_action('pingback_post', 'akpc_feedback_pingback');

function akpc_feedback_trackback() {
	global $akpc;
	$akpc->record_feedback('trackback');
}
add_action('trackback_post', 'akpc_feedback_trackback');

function akpc_publish($post_id) {
	global $akpc;
	$akpc->create_post_record($post_id);
}
add_action("publish_post", 'akpc_publish');

function akpc_post_delete($post_id) {
	global $akpc;
	$akpc->delete_post_record($post_id);
}
add_action('delete_post', 'akpc_post_delete');

function akpc_update_post_meta($meta_id, $post_id, $meta_key, $meta_value) {
	global $akpc;
	$akpc->update_post_meta($meta_id, $post_id, $meta_key, $meta_value);
}
add_action('update_postmeta', 'akpc_update_post_meta', 10, 4);


function akpc_options_form() {
	global $akpc;
	$akpc->options_form();
}

function akpc_view_stats() {
	global $akpc;
	$akpc->view_stats();
}

function akpc_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__, '.php');
	if (basename($file, '.php') == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">'.__('Settings', 'popularity-contest').'</a>';
		array_unshift($links, $settings_link);
		$reports_link = '<a href="index.php?page='.$plugin_file.'">'.__('Reports', 'popularity-contest').'</a>';
		array_unshift($links, $reports_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'akpc_plugin_action_links', 10, 2);

function akpc_options() {
	add_options_page(
		__('Popularity Contest Options', 'popularity-contest'),
		__('Popularity', 'popularity-contest'),
		'manage_options',
		basename(AKPC_FILE, '.php'),
		'akpc_options_form'
	);
	add_submenu_page(
		'index.php',
		__('Most Popular Posts', 'popularity-contest'),
		__('Most Popular Posts', 'popularity-contest'),
		'manage_options',
		basename(AKPC_FILE, '.php'),
		'akpc_view_stats'
	);
}
add_action('admin_menu', 'akpc_options');

function akpc_options_css() {
	if (is_admin() && !empty($_GET['page']) && $_GET['page'] == basename(__FILE__, '.php')) {
		print('<link rel="stylesheet" type="text/css" href="'.site_url('?ak_action=akpc_css').'" />');
	}
}
add_action('admin_print_styles', 'akpc_options_css');

// -- TEMPLATE FUNCTIONS
function akpc_the_popularity($post_id = null) {
	global $akpc;
	if (!$post_id) {
		global $post;
		$post_id = $post->ID;
	}

// TODO check if post is excluded from pop
// We do this in akpc_content_pop. Not done here due to use with shortcodes. 

	$akpc->show_post_rank($post_id);
}

function akpc_most_popular($limit = 10, $before = '<li>', $after = '</li>', $report = false, $echo = true) {
	global $akpc;
	if(!$report) {
		$akpc->show_top_ranked($limit, $before, $after);
	}
	else {
		return $akpc->show_report($report, $limit);
	}
}

function akpc_show_report($type = 'popular', $limit = 10, $exclude_pages = 'DEPRECATED', $custom = array(), $before_title = '<h3>', $after_title = '</h3>', $hide_title = false) {
	global $akpc;
	return $akpc->show_report($type, $limit, $exclude_pages, $custom, $before_title, $after_title, $hide_title);
}

function akpc_get_popular_posts_array($type, $limit, $custom = array()) {
	global $akpc;
	return $akpc->get_popular_posts($type, $limit, $custom);
}

function akpc_most_popular_in_cat($limit = 10, $before = '<li>', $after = '</li>', $cat_ID = '') {
	global $akpc;
	$akpc->show_top_ranked_in_cat($limit, $before, $after, $cat_ID);
}

function akpc_most_popular_in_month($limit = 10, $before = '<li>', $after = '</li>', $m = '') {
	global $akpc;
	$akpc->show_top_ranked_in_month($limit, $before, $after, $m);
}

function akpc_most_popular_in_last_days($limit = 10, $before = '<li>', $after = '</li>', $days = 45) {
	global $akpc;
	$akpc->show_top_ranked_in_last_days($limit, $before, $after, $days);
}

function akpc_content_pop($str) {
	global $akpc, $post;
	if (is_admin()) {
		return $str;
	}
	else if (is_feed()) {
		$str .= '<img src="'.esc_url(site_url('?ak_action=api_record_view&id='.$post->ID.'&type=feed')).'" alt="" />';
	}
	else {
		if (AKPC_USE_API && in_the_loop()) {
			$str .= '<script type="text/javascript">AKPC_IDS += "'.$post->ID.',";</script>';
		}
		$show = apply_filters('akpc_display_popularity', $akpc->show_pop, $post);
		$post_types = explode(',', ($akpc->post_types));
		$post_type = get_post_type($post);

		if (get_post_meta($post->ID, 'exclude_from_popularity', true) != '1' && 
			get_post_meta($post->ID, 'hide_popularity', true) != '1' && 
			$show && 
			in_array($post_type, $post_types)) {
			$str .= apply_filters('akpc_popularity_display_markup','<p class="akpc_pop">'.$akpc->get_post_rank($post->ID).'</p>');
		}
	}
	return $str;
}

function akpc_excerpt_compat_pre($output) {
	remove_filter('the_content', 'akpc_content_pop', 9999);
	return $output;
}

function akpc_excerpt_compat_post($output) {
	add_filter('get_the_excerpt', 'akpc_content_pop');
	return $output;
}

function akpc_filters() {
	add_filter('the_content', 'akpc_content_pop', 9999);
	add_filter('get_the_excerpt', 'akpc_excerpt_compat_pre', 1);
	add_filter('get_the_excerpt', 'akpc_excerpt_compat_post', 2);
}
add_action('init' , 'akpc_filters');

// -- WIDGET
class AKPC_Widget extends WP_Widget {
	function AKPC_Widget() {
		$widget_ops = array('classname' => 'akpc-widget', 'description' => __('Show popular posts as ranked by Popularity-Contest', 'popularity-contest'));
		$this->WP_Widget('akpc-widget', __('Popularity-Contest', 'popularity-contest'), $widget_ops);
	}

	function widget($args, $instance) {
		extract( $args, EXTR_SKIP );		
		// Get the variables
		$title = esc_html($instance['title']);
		$type = (!isset($instance['type']) || empty($instance['type'])) ? 'popular' : esc_html($instance['type']);
		$days = (!isset($instance['days']) || empty($instance['days'])) ? '' : intval($instance['days']);
		$limit = (!isset($instance['limit']) || empty($instance['limit'])) ? 10 : intval($instance['limit']);
		$show_rank = !isset($instance['show_rank']) ? 0 : intval($instance['show_rank']);
		
		if ($limit > 9) {
			echo '<style type="text/css">.akpc_report ol { padding-left: 1em; }</style>';
		}

		$custom = array();
			if ($type == 'last_n') {
				$custom['days'] = $days;
			}

		echo $before_widget;
		// Check to see if we have a title, and only echo the before and after if we do
		if (!empty($title)) {
			echo $before_title.$title.$after_title;
		}

		akpc_show_report($type, $limit, 'DEPRECATED', $custom, '<h4>', '<h4>', true);
		echo $after_widget;
		global $akpc;
		if (!$show_rank) {
			echo '<style type="text/css">.akpc_report li span { display: none; }</style>';
		}
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
			// Check and make sure the days passed in is an actual integer
			$days = 0;
			if (is_numeric($new_instance['days']) && !preg_match('/[[:^digit:]]/', $new_instance['days'])) {
				$days = $new_instance['days'];
		}

		// Check and make sure the limit passed in is an actual integer
		$limit = 0;
		if (is_numeric($new_instance['limit']) && !preg_match('/[[:^digit:]]/', $new_instance['limit'])) {
			$limit = $new_instance['limit'];
		}
		
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['type'] = strip_tags($new_instance['type']);
		$instance['days'] = strip_tags(intval($new_instance['days']));
		$instance['limit'] = strip_tags(intval($new_instance['limit']));
		$instance['show_rank'] = strip_tags(intval($new_instance['show_rank']));

		return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args((array) $instance, array('title' => '', 'type' => '', 'days' => '', 'limit' => ''));
		$title = esc_html($instance['title']);
		$days = esc_html($instance['days']);
		$limit = esc_html($instance['limit']);
		$show_rank = intval($instance['show_rank']);
		
		$report_types = akpc_get_report_types();
			
		$hide_days = ($instance['type'] == 'last_n') ? '' : ' style="display:none;"';
			
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'popularity-contest'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Report Type:', 'popularity-contest'); ?></label>
			<select id="<?php echo $this->get_field_id('type'); ?>" name="<?php echo $this->get_field_name('type'); ?>" class="widefat akpc_pop_widget_type">
				<?php
				if (is_array($report_types) && !empty($report_types)) {
					foreach ($report_types as $key => $type) {
						?>
						<option value="<?php echo attribute_escape($key); ?>"<?php selected($instance['type'], attribute_escape($key)); ?>><?php echo attribute_escape($type); ?></option>
						<?php
					}
				}
				?>
		</select>
		</p>
		<p class="akpc_pop_widget_days"<?php echo $hide_days; ?>>
			<label for="<?php echo $this->get_field_id('days'); ?>"><?php _e('Number of Days:', 'popularity-contest'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('days'); ?>" name="<?php echo $this->get_field_name('days'); ?>" type="text" value="<?php echo $days; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of Posts:', 'popularity-contest'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('show_rank'); ?>"><?php _e('Show rank:', 'popularity-contest'); ?></label>
			<select name="<?php echo $this->get_field_name('show_rank'); ?>" id="<?php echo $this->get_field_id('show_rank'); ?>">
				<option value="1" <?php selected($show_rank, 1) ?>>Yes</option>
				<option value="0" <?php selected($show_rank, 0) ?>>No</option>
			</select>
		</p>
		<?php
		}
}
add_action('widgets_init', create_function('', "register_widget('AKPC_Widget');"));

function akpc_get_report_types() {
	$public_post_types = explode(',', akpc_public_post_types());
	
	$reports = array(
		'popular' => __('Most Popular', 'popularity-contest'),
		'category_popularity' => __('Most Popular Categories', 'popularity-contest'),
		'tag_popularity' => __('Most Popular Tags', 'popularity-contest'),
		'category_tag_popularity' => __('Most Popular Categories and Tags', 'popularity-contest'),
		'last_30' => __('Last 30 Days', 'popularity-contest'),
		'last_60' => __('Last 60 Days', 'popularity-contest'),
		'last_90' => __('Last 90 Days', 'popularity-contest'),
		'last_n' => __('Last (n) Days', 'popularity-contest'),
		'365_plus' => __('Older than 1 Year', 'popularity-contest'),
		'year' => __('Average by Year', 'popularity-contest'),
		'views_wo_feedback' => __('Views w/o Feedback', 'popularity-contest'),
		'most_feedback' => __('Most Feedback', 'popularity-contest'),
		'most_comments' => __('Most Commented', 'popularity-contest'),
		'most_feed_views' => __('Feed Views', 'popularity-contest'),
		'most_home_views' => __('Home Page Views', 'popularity-contest'),
		'most_archive_views' => __('Archive Views', 'popularity-contest'),
		'most_single_views' => __('Permalink Views', 'popularity-contest'),
		'most_pingbacks' => __('Pingbacks', 'popularity-contest'),
		'most_trackbacks' => __('Trackbacks', 'popularity-contest')
	);
	foreach ($public_post_types as $post_type) {
		$most_popular_str = __('Most Popular ' , 'popularity-contest');
		$views_str = __('Views' , 'popularity-contest');
		$reports[$post_type] = $most_popular_str . ucwords($post_type) . ' ' . $views_str;
	}
	
	return apply_filters('akpc_get_report_types', $reports);
}

// -- API FUNCTIONS
function akpc_api_head_javascript() {
	echo '<script type="text/javascript">var AKPC_IDS = "";</script>';
}

function akpc_api_footer_javascript() {
	if (function_exists('akpc_is_searcher') && akpc_is_searcher()) {
		$type = 'searcher';
	}
	else if (is_category()) {
		$type = 'category';
	}
	else if (is_single()) {
		$type = 'single';
	}
	else if (is_tag()) {
		$type = 'tag';
	}
	else if (is_page()) {
		$type = 'page';
	}
	else if (is_archive()) {
		$type = 'archive';
	}
	else if (is_home()) {
		$type = 'home';
	}
	echo '
<script type="text/javascript">
jQuery(function() {
	
	jQuery.post("index.php",{ak_action:"api_record_view", ids: AKPC_IDS, type:"'.esc_js($type).'"}, false, "json");
});
</script>
	';
}

function akpc_is_searcher() {
	global $akpc;

	$temp = parse_url($_SERVER['HTTP_REFERER']);
	$referring_domain = $temp['host'];
	$searchers = preg_replace("/\n|\r|\r\n|\n\r/", ' ', $akpc->searcher_names);
	$searchers = explode(' ', $searchers);
	foreach ($searchers as $searcher) {
		if ($referring_domain == $searcher) { 
			return true;
		}
	}
	return false;
}

function akpc_api_record_view($id = null) {
	global $wpdb;
	$akpc = new ak_popularity_contest;
	$akpc->get_settings();	
	$ids = array();
	if ($id) {
		$ids[] = $id;
	}
	else {
		foreach (explode(',', $_POST['ids']) as $id) {
			if ($id = intval($id)) {
				$ids[] = $id;
			}
		}
	}
	array_unique($ids);	
	if (!empty($_GET['type'])) {
		$type = $_GET['type'];
		$response = 'img';
	}
	else {
		$type = $_POST['type'];
		$response = 'json';
	}
	if (count($ids) && $akpc->record_view(true, $ids, $type)) {
		$json = '{"result":true,"ids":"'.implode(',',$ids).'","type":"'.sanitize_title($type).'"}';
	}
	else {
		$json = '{"result":false,"ids":"'.implode(',',$ids).'","type":"'.sanitize_title($type).'"}';
	}
	switch ($response) {
		case 'img':
			header('Location: ' . trailingslashit(AKPC_DIR_URL) . 'transparent.gif');
			break;
		case 'json':
			header('Content-type: application/json');
			echo $json;
			break;
	}
	exit();
}

// -- HANDLE ACTIONS
function akpc_request_handler() {
	if (!empty($_POST['ak_action'])) {
		switch($_POST['ak_action']) {
			case 'update_popularity_values':
					if (!check_admin_referer('akpc', 'akpc_settings_nonce')) {
						die();
					}
					$akpc = new ak_popularity_contest;
					$akpc->get_settings();
					$akpc->update_settings();
				break;
			case 'recount_feedback':
					if (!check_admin_referer('akpc', 'akpc_recount_nonce')) {
						die();
					}
					$akpc = new ak_popularity_contest;
					$akpc->get_settings();
					$akpc->recount_feedback();
				break;
			case 'api_record_view':
				akpc_api_record_view();
				break;
			case 'akpc_add_tag':
				if (!empty($_POST['tag']) && current_user_can('manage_options')) {
					$akpc = new ak_popularity_contest;
					if (strpos($_POST['tag'], ',')) {
						$added_tags = explode(',', $_POST['tag']);
					}
					else {
						$added_tags = array($_POST['tag']);
					}
					$tag_reports = get_option('akpc_tag_reports');
					if ($tag_reports == '') {
						add_option('akpc_tag_reports');
					}
					$tags = maybe_unserialize($tag_reports);
					if (!is_array($tags)) {
						$tags = array();
					}
					foreach ($added_tags as $tag) {
						$tag = sanitize_title_with_dashes(trim($tag));
						if (!empty($tag) && !in_array($tag, $tags)) {
							if ($term = get_term_by('slug', $tag, 'post_tag')) {
								$tags[] = $tag;
								$akpc->show_report('tag', 10, 'DEPRECATED', array('term_id' => $term->term_id, 'term_name' => $term->name));
							}
						}
					}
					$tags = array_unique($tags);
					update_option('akpc_tag_reports', $tags);
				}
				die();
				break;
			case 'akpc_remove_tag':
				if (!empty($_POST['tag']) && current_user_can('manage_options')) {
					$tag = sanitize_title(trim($_POST['tag']));
					if (!empty($tag)) {
						$tags = maybe_unserialize(get_option('akpc_tag_reports'));
						if (is_array($tags) && count($tags)) {
							$new_tags = array();
							foreach ($tags as $existing_tag) {
								if ($existing_tag != $tag) {
									$new_tags[] = $existing_tag;
								}
							}
							$tags = array_unique($new_tags);
							update_option('akpc_tag_reports', $tags);
						}
					}
				}
				die();
				break;
		}
	}
	if (!empty($_GET['ak_action'])) {
		switch($_GET['ak_action']) {
			case 'api_record_view':
				if (isset($_GET['id']) && $id = intval($_GET['id'])) {
					akpc_api_record_view($id);
				}
				break;
			case 'akpc_css':
				header("Content-type: text/css");
?>
.ak_wrap {
	padding-bottom: 40px;
}
#akpc_most_popular {
	height: 250px;
	overflow: auto;
	margin-bottom: 10px;
}
#akpc_most_popular .alternate {
	background: #efefef;
}
#akpc_most_popular td.right, #akpc_options_link {
	text-align: right;
}
#akpc_most_popular td {
	padding: 3px;
}
#akpc_most_popular td a {
	border: 0;
}
.akpc_report {
	float: left;
	margin: 5px 30px 20px 0px;
	width: 220px;
}
.akpc_report h3 {
	border-bottom: 1px solid #999;
	color #333;
	margin: 0 0 4px 0;
	padding: 0 0 2px 0;
}
.akpc_report ol {
	margin: 0;
	padding: 0 0 0 30px;
}
.akpc_report ol li span {
	float: right;
}
.akpc_report ol li a {
	border: 0;
	display: block;
	margin: 0 30px 0 0;
}
.clear {
	clear: both;
	float: none;
}
#akpc_template_tags dl {
	margin-left: 10px;
}
#akpc_template_tags dl dt {
	font-weight: bold;
	margin: 0 0 5px 0;
}
#akpc_template_tags dl dd {
	margin: 0 0 15px 0;
	padding: 0 0 0 15px;
}
#akpc_report_tag_form {
	display: inline;
	padding-left: 20px;
}
#akpc_report_tag_form label, .akpc_status {
	font: normal normal 12px "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;
}
.akpc_status {
	color: #999;
	display: none;
	padding: 5px;
}
#akpc_tag_reports h3 {
	padding-right: 20px;
}
#akpc_tag_reports a.remove {
	float: right;
}
#akpc_tag_reports .akpc_padded {
	color: #999;
	padding: 20px;
	text-align: center;
}
#akpc_tag_reports .none {
	background: #eee;
	text-align: left;
}
<?php
				die();
				break;
			case 'akpc_js':
				header('Content-type: text/javascript');
				?>
var cf_widget_count = 0;
jQuery(function($) {
	akpc_widget_js();
	setInterval('akpc_widget_check()', 500);
});
akpc_widget_js = function() {
	jQuery('select.akpc_pop_widget_type').unbind().change(function() {
		if (jQuery(this).val() == 'last_n') {
			jQuery(this).parents('div.widget-content, div.widget-control').find('p.akpc_pop_widget_days:hidden').slideDown();
		}
		else {
			jQuery(this).parents('div.widget-content, div.widget-control').find('p.akpc_pop_widget_days:visible').slideUp();
		}
	});
}
akpc_widget_check = function() {
	var current_count = jQuery('#widgets-right .widget-inside:visible, .widget-control-list .widget-list-control-item').size();
	if (current_count != cf_widget_count) {
		akpc_widget_js();
		cf_widget_count = current_count;
	}
}
<?php
				die();
				break;
		}
	}
}
add_action('init', 'akpc_request_handler', 2);

function akpc_posts_to_exclude() {
	global $wpdb;
	
	$args = array(
			'status' => 'publish',
			'meta_key' => 'exclude_from_popularity',
			'meta_value' => '1'
		);
	$posts = get_posts($args);
	$post_ids = array();
	if (!empty($posts)) {
		foreach ($posts as $post) {
			$post_ids[] .= $post->ID;
		}
	}

	return $post_ids;	
}

function akpc_public_post_types() {
	$args = array('public' => true);
	return implode(',', get_post_types($args, 'names'));
}

// -- SHORTCODES
function akpc_the_popularity_shortcode($atts) {
	extract(shortcode_atts(array(
	), $atts));
	
	akpc_the_popularity();
}
add_shortcode('akpc_the_popularity', 'akpc_the_popularity_shortcode');

function akpc_most_popular_shortcode($atts) {
	extract(shortcode_atts(array(
		'limit' => '10'
	), $atts));
	
	akpc_most_popular($limit);
}
add_shortcode('akpc_most_popular', 'akpc_most_popular_shortcode');

if (AKPC_USE_API == 0) {
	// work cache unfriendly
	add_action('the_content', 'akpc_view');
}
else {
	// do view updates via API
	add_action('wp_head','akpc_api_head_javascript');
	add_action('wp_footer','akpc_api_footer_javascript');
	wp_enqueue_script('jquery');
}

// Multisite support
function akpc_is_multisite() {
	return CF_Admin::is_multisite();	
}

function akpc_is_network_activation() {
	return CF_Admin::is_network_activation();
}

function akpc_activate_for_network() {
	CF_Admin::activate_for_network('akpc_activate_single');
}

function akpc_activate_plugin_for_new_blog($blog_id) {
	CF_Admin::activate_plugin_for_new_blog(AKPC_FILE, $blog_id, 'akpc_activate_single');
}
add_action( 'wpmu_new_blog', 'akpc_activate_plugin_for_new_blog');

function akpc_switch_blog() {
	global $wpdb;
	$wpdb->ak_popularity = $wpdb->prefix . 'ak_popularity';
	$wpdb->ak_popularity_options = $wpdb->prefix . 'ak_popularity_options';
}
add_action('switch_blog' , 'akpc_switch_blog');

endif; // LOADED CHECK

?>
