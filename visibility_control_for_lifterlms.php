<?php
/*
Plugin Name: Visibility Control for Lifter LMS
Plugin URI: https://www.nextsoftwaresolutions.com/lifterlms-visibility-control
Description: Control visibility of HTML elements, menus, and other details on your website based on User's access to specific Lifter LMS Course, Role or Login status. Add CSS class: visible_to_course_123 to show the element/menu item to user with access to Course with ID 123. Add CSS Class: hidden_to_course_123 to hide the element from user with access to Course with ID 123. Add CSS class: visible_to_logged_in to show the element/menu item to a logged in user. Add CSS class: hidden_to_logged_in or visible_to_logged_out to show the element/menu item to a logged out users. More Classes: visible_to_role_administrator, hidden_to_role_administrator, visible_to_course_complete_123, hidden_to_course_complete_123, visible_to_course_incomplete_123, hidden_to_course_incomplete_123. Currently, this will only hide the content using CSS.
Author: Next Software Solutions
Version: 1.1
Author URI: https://www.nextsoftwaresolutions.com
*/

class visibility_control_for_lifterlms {
	function __construct() {
		add_action("wp_head", array($this, "add_css"));
		add_action( 'admin_menu', array($this,'menu'), 10);
	}

	function menu() {
		global $submenu, $admin_page_hooks;
		$icon = plugin_dir_url(__FILE__)."img/icon-gb.png";

		if(empty( $admin_page_hooks[ "grassblade-lrs-settings" ] )) {
			add_menu_page("GrassBlade", "GrassBlade", "manage_options", "grassblade-lrs-settings", array($this, 'menu_page'), $icon, null);
		}
		add_submenu_page("grassblade-lrs-settings", "Visibility Control for LifterLMS", "Visibility Control for LifterLMS",'manage_options','grassblade-visibility-control-lifterlms', array($this, 'menu_page'));
		add_submenu_page("lifterlms", "Visibility Control for LifterLMS", "Visibility Control",'manage_options','grassblade-visibility-control-lifterlms', array($this, 'menu_page'));
	}

	function menu_page() {

		if(!current_user_can("manage_options"))
			return;

		$enabled = get_option("visibility_control_for_lifterlms");

		if( !empty($_POST["submit"]) && check_admin_referer('visibility_control_for_lifterlms') ) {
			$enabled = intVal(isset($_POST["visibility_control_for_lifterlms"]));
			update_option("visibility_control_for_lifterlms", $enabled);
		}

		if($enabled === false) {
			$enabled = 1;
			update_option("visibility_control_for_lifterlms", $enabled);
		}

		?>
		<style type="text/css">
			div#visibility_control_for_lifterlms {
				padding: 30px;
				background: white;
				margin: 50px;
				border-radius: 5px;
			}
			div#visibility_control_for_lifterlms input[type=checkbox] {
				margin-left: 50px;
			}
		</style>
		<div id="visibility_control_for_lifterlms" class="wrap">
			<h3>Visibility Control for Lifter LMS</h3>
			<form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
				<?php wp_nonce_field( 'visibility_control_for_lifterlms' ); ?>
				<p style="padding: 20px;"><b><?php _e("Enable"); ?></b> <input name="visibility_control_for_lifterlms" type="checkbox" value="1" <?php if($enabled) echo 'checked="checked"'; ?>> </p>

				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e("Save Changes"); ?>"></p>

			</form>
			<br>
			<?php _e("For instructions on how to control visibility of different elements on your site based on Lifter LMS course access. Or login/logout state and more.", "visibility_control_for_lifterlms"); ?> <a href="https://wordpress.org/plugins/visibility-control-for-lifterlms/" target="_blank"><?php _e("Check the plugin page here.", "visibility_control_for_lifterlms"); ?></a>
		</div>
		<?php
	}

	function add_css() {
		if(!function_exists('llms') || !function_exists('llms_is_user_enrolled'))
			return;

		global $pagenow, $post;

		if( is_admin() && $pagenow == "post.php" ||  is_admin() && $pagenow == "post-new.php" )
			return; //Disable for Edit Pages

		if( !empty($post->ID) ) {
			if( $post->post_type == "page" && current_user_can( 'edit_page', $post->ID ) || $post->post_type != "page" &&  current_user_can( 'edit_post', $post->ID ) ) {
				//User with Edit Access
				if( isset($_GET["elementor-preview"]) || isset($_GET['brizy-edit'])  || isset($_GET['brizy-edit-iframe'])  || isset($_GET['vcv-editable'])   || isset($_GET['vc_editable']) || isset($_GET['fl_builder'])  || isset($_GET['et_fb'])  )
					return; //Specific Front End Editor Pages. Elementor, Brizy Builder, Beaver Builder, Divi, WPBakery Builder, Visual Composer
			}
		}

		$enabled = get_option("visibility_control_for_lifterlms", true);

		if(empty($enabled))
			return;

		global $current_user, $wpdb;
		$hidden_classes = array();
		if(!empty($current_user->ID)) { //Logged In

			$hidden_classes[] = ".hidden_to_logged_in";
			$hidden_classes[] = ".visible_to_logged_out";
		}
		else //Logged Out
		{
			$hidden_classes[] = ".hidden_to_logged_out";
			$hidden_classes[] = ".visible_to_logged_in";
		}


		$roles = wp_roles();
		$role_ids = array_keys($roles->roles);

		foreach($role_ids as $role_id) {
			if( empty($current_user->roles) || !in_array($role_id, $current_user->roles) ) { //User not with Role
				$hidden_classes[] = ".visible_to_role_".$role_id;
			}
			else //Has Role
			{
				$hidden_classes[] = ".hidden_to_role_".$role_id;
			}
		}

		$courses = get_posts("post_type=course&post_status=publish&posts_per_page=-1");
		$user_id = empty($current_user->ID)? null:intval($current_user->ID);

		$completed_courses_ids = empty($user_id) ? array() : $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}lifterlms_user_postmeta where user_id = %d AND meta_key = '_is_complete' AND meta_value LIKE 'yes' ", $user_id));
		$course_ids = array();
		if(!empty($courses))
		foreach ($courses as $course) {
			$course_ids[] = $course->ID;
			if(!in_array($course->ID, $completed_courses_ids)){
				$hidden_classes[] = ".hidden_to_course_incomplete_".$course->ID;
				$hidden_classes[] = ".visible_to_course_complete_".$course->ID;
			}
		}
		if(!empty($completed_courses_ids))
		foreach ($completed_courses_ids as $course_id) {
			if(in_array($course_id, $course_ids) ){
				$hidden_classes[] = ".hidden_to_course_complete_".$course_id;
				$hidden_classes[] = ".visible_to_course_incomplete_".$course_id;

			}
		}

		if(!empty($courses))
		foreach ($courses as $course) {
			$has_access = !empty($user_id) && llms_is_user_enrolled($user_id, $course->ID);
			if($has_access) {
				$hidden_classes[] = ".hidden_to_course_".$course->ID;
			}
			else
			{
				$hidden_classes[] = ".visible_to_course_".$course->ID;
			}
		}
		?>
		<style type="text/css" id="visibility_control_for_lifterlms">
			<?php
			$hidden_classes_string = preg_replace('/[^a-zA-Z0-9_\s.,]/', '', implode(", ", $hidden_classes));
			echo $hidden_classes_string ?> {
				display: none !important;
			}
		</style>
		<script>
			if (typeof jQuery == "function")
			jQuery(document).ready(function() {
				jQuery(window).on("load", function(e) {
					//<![CDATA[
					var hidden_classes = <?php echo json_encode($hidden_classes); ?>;
					//]]>
					jQuery(hidden_classes.join(",")).remove();
				});
			});
		</script>
		<?php
	}
}

new visibility_control_for_lifterlms();
