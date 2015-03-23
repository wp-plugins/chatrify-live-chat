<?php
/*
Plugin Name: Chatrify 
Plugin URI: https://www.chatrify.com/
Version: 1.0.0
Description: Wordpress Plugin for chatrify
*/

class Chatrify {

	// singleton pattern
	protected static $instance;

	/**
	 * license parameters
	 */
	protected $login = null;
	protected $uid = null;
	protected $group = null;
	protected $changes_saved = 0;

	/**
	 * Starts the plugin
	 */
	protected function __construct()
	{
		add_action ('wp_head', array($this, 'tracking_code'));

		if (is_admin()) {
			add_action('init', array($this, 'load_scripts'));
			add_action('admin_menu', array($this, 'admin_menu'));

			if (isset($_GET['reset']) && $_GET['reset'] == '1')
			{
				$this->reset_options();
			}
			elseif ($_SERVER['REQUEST_METHOD'] == 'POST')
			{
				$this->update_options($_POST);
			}
		}

		// tricky error reporting
		if (defined('WP_DEBUG') && WP_DEBUG == true)
		{
			add_action('init', array($this, 'error_reporting'));
		}

	}

	public function get_instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	/**
	 * Set error reporting for debugging purposes
	 */
	public function error_reporting()
	{
		error_reporting(E_ALL & ~E_USER_NOTICE);
	}

	public function load_scripts()
	{
		wp_enqueue_script('chatrify', $this->get_plugin_url().'/js/chatrify.js', 'jquery', $this->get_plugin_version(), true);
		wp_enqueue_style('chatrify', $this->get_plugin_url().'/css/chatrify.css', false, $this->get_plugin_version());
	}

	public function admin_menu()
	{
		add_menu_page(
			'Chatrify',
			'Chatrify',
			'administrator',
			'chatrify',
			array($this, 'chatrify_settings_page'),
			$this->get_plugin_url().'/images/favicon.png'
		);

		add_submenu_page(
			'chatrify',
			'Settings',
			'Settings',
			'administrator',
			'chatrify_settings',
			array($this, 'chatrify_settings_page')
		);

		// remove the submenu that is automatically added
		if (function_exists('remove_submenu_page'))
		{
			remove_submenu_page('chatrify', 'chatrify');
		}

		// Settings link
		add_filter('plugin_action_links', array($this, 'chatrify_settings_link'), 10, 2);
	}

	public function chatrify_settings_link($links, $file)
	{
		if (basename($file) !== 'wp-chatrify.php')
		{
			return $links;
		}

		$settings_link = sprintf('<a href="admin.php?page=chatrify_settings">%s</a>', __('Settings'));
		array_unshift ($links, $settings_link); 
		return $links;
	}

	/**
	 * Displays settings page
	 */
	public function chatrify_settings_page()
	{
		?>

	<div id="chatrify">
		<div class="wrap">

			<div id="mc_logo">
				<img src="<?php echo $this->get_plugin_url(); ?>/images/logo.png" />
			</div>
			<div class="clear"></div> 

			<?php if ($this->changes_saved) { ?>
			<div id="changes_saved_info" class="updated installed_ok"><p>Advanced settings saved successfully.</p></div>
			<?php } ?>

			<?php if ($this->is_installed()) { ?>
			<div class="updated installed_ok"><p>Chatrify is installed properly. Woohoo!</p></div>
			<?php } ?>

			<?php if ($this->is_installed() == false) { ?>
			<div class="metabox-holder">
				<div class="postbox">
					<h3>Do you already have a Chatrify account?</h3>
					<div class="postbox_content">
					<ul id="mc_choice_account">
					<li><input type="radio" name="choice_account" id="choice_account_1" checked="checked"> <label for="choice_account_1">Yes, I already have a Chatrify account</label></li>
					<li><input type="radio" name="choice_account" id="choice_account_0"> <label for="choice_account_0">No, I want to create one</label></li>
					</ul>
					</div>
				</div>
			</div>
			<?php } ?>

			<!-- Already have an account -->
			<div class="metabox-holder" id="chatrify_already_have" style="display:none">

				<?php if ($this->is_installed()): ?>
				<div class="postbox">
				<h3><?php echo _e('Sign in to Chatrify'); ?></h3>
				<div class="postbox_content">
				<p><?php echo _e('Sign in to Chatrify and start chatting with your customers!'); ?></p>
				<p><span class="btn"><a href="https://app.chatrify.com/" target="_blank"><?php _e('Sign in to web application'); ?></a></span> &nbsp; <!-- or <a href="http://www.chatrify.com/product/" target="_blank"><?php _e('download desktop app'); ?></a> --></p>
				</div>
				</div>
				<?php endif; ?>

				<?php if ($this->is_installed() == false) { ?>
				<div class="postbox">
				<form method="post" action="?page=chatrify_settings">
					<h3>Chatrify account</h3>
					<div class="postbox_content">
					<table class="form-table">
					<tr>
					<th scope="row"><label for="chatrify_login">My Chatrify login is:</label></th>
					<td><input type="text" name="login" id="chatrify_login" value="<?php echo $this->get_login(); ?>" size="40" /></td>
					</tr>
					</table>

					<p class="ajax_message"></p>
					<p class="submit">
					<input type="hidden" name="license_number" value="<?php echo $this->get_uid(); ?>" id="license_number">
					<input type="hidden" name="settings_form" value="1">
					<input type="submit" class="button-primary" value="<?php _e('Save changes') ?>" />
					</p>
					</div>
				</form>
				</div>

					<?php } else { ?>

				<div id="advanced" class="postbox" style="display:none">
				<form method="post" action="?page=chatrify_settings">
					<h3>Advanced settings</h3>
					<div class="postbox_content">
					<table class="form-table">
					<tr>
					<th scope="row"><label for="skill">Group:</label></th>
					<td><input type="text" name="skill" id="skill" value="<?php echo $this->get_group(); ?>" /> <span class="explanation">Used for dividing chat agents into groups (<a href="https://www.chatrify.com/support/kb/adding-groups-chatrify/" target="_blank">read more</a>). </span></td>
					</tr>
					</table>
					<p class="submit">
					<input type="hidden" name="license_number" value="<?php echo $this->get_uid(); ?>" id="license_number">
					<input type="hidden" name="changes_saved" value="1">
					<input type="hidden" name="settings_form" value="1">
					<input type="submit" class="button-primary" value="<?php _e('Save changes') ?>" />
					</p>
					</div>
				</form>
				</div>
				<p id="mc_advanced-link"><a href="">Show advanced settings&hellip;</a></p>
					<?php } ?>

				<?php if ($this->is_installed()) { ?>
				<p id="mc_reset_settings">Something went wrong? <a href="?page=chatrify_settings&amp;reset=1">Reset your settings</a>.</p>
				<?php } ?>
			</div>

			<!-- New account form -->
			<div class="metabox-holder" id="chatrify_new_account" style="display:none">
				<div class="postbox">
				<form method="post" action="?page=chatrify_settings">
					<h3>Create new Chatrify account</h3>
					<div class="postbox_content">

					<?php
					global $current_user;
					get_currentuserinfo();

					$fullname = $current_user->user_firstname.' '.$current_user->user_lastname;
					$fullname = trim($fullname);
					?>
					<table class="form-table">
					<tr>
					<th scope="row"><label for="name">Full name:</label></th>
					<td><input type="text" name="name" id="name" maxlength="60" value="<?php echo $fullname; ?>" size="40" /></td> 
					</tr>
					<tr>
					<th scope="row"><label for="email">E-mail:</label></th>
					<td><input type="text" name="email" id="email" maxlength="100" value="<?php echo $current_user->user_email; ?>" size="40" /></td>
					</tr>
					<tr>
					<th scope="row"><label for="password">Password:</label></th>
					<td><input type="password" name="password" id="password" maxlength="100" value="" size="40" /></td>
					</tr>
					<tr>
					<th scope="row"><label for="password_retype">Retype password:</label></th>
					<td><input type="password" name="password_retype" id="password_retype" maxlength="100" value="" size="40" /></td>
					</tr>
					</table>

					<p class="ajax_message"></p>
					<p class="submit">
						<input type="hidden" name="website" value="<?php echo bloginfo('url'); ?>">
						<input type="submit" value="Create account" id="submit" class="button-primary">
					</p>
					</div>
				</form>

				<form method="post" action="?page=chatrify_settings" id="save_new_license">
					<p>
					<input type="hidden" name="new_license_form" value="1">
					<input type="hidden" name="skill" value="0">
					<input type="hidden" name="license_number" value="0" id="new_license_number">
					</p>
				</form>
				</div>
			</div>

		</div>

	</div>

		<?php

	}

	/** 
	 * Returns plugin files absolute path
	 *
	 * @return string
	 */
	public function get_plugin_url()
	{
		if (is_null($this->plugin_url))
		{
			$this->plugin_url = WP_PLUGIN_URL.'/wp-chatrify';
		}

		return $this->plugin_url;
	}

	/**
	 * Returns this plugin's version
	 *
	 * @return string
	 */
	public function get_plugin_version()
	{
		if (is_null($this->plugin_version))
		{
			if (!function_exists('get_plugins'))
			{
				require_once(ABSPATH.'wp-admin/includes/plugin.php');
			}

			$plugin_folder = get_plugins('/'.plugin_basename(dirname(__FILE__).'/..'));
			$this->plugin_version = $plugin_folder['wp-chatrify.php']['Version'];
		}

		return $this->plugin_version;
	}

	/**
	 * Returns true if Chatrify UID is set properly,
	 * false otherwise
	 *
	 * @return bool
	 */
	public function is_installed()
	{
		return ($this->get_uid() ? 1 : 0);
	}

	/**
	 * Returns Chatrify UID
	 *
	 * @return int
	 */
	public function get_uid()
	{
		if (is_null($this->uid))
		{
			$this->uid = get_option('chatrify_uid');
		}

		if (!$this->uid) {
			$this->uid = 0;	
		}

		return $this->uid;
	}	

	/**
	 * Returns Chatrify group number
	 *
	 * @return int
	 */
	public function get_group()
	{
		if (is_null($this->group))
		{
			$this->group = (int)get_option('chatrify_groups');
		}

		if ($this->group) {
			$this->group = 0;
		}

		return $this->group;
	}

	/**
	 * Returns Chatrify login
	 */
	public function get_login()
	{
		if (is_null($this->login))
		{
			$this->login = get_option('login');
		}

		return $this->login;
	}

	public function reset_options()
	{
		delete_option('chatrify_uid');
		delete_option('chatrify_groups');
	}


	protected function update_options($data)
	{
		// check if we are handling LiveChat settings form
		if (isset($data['settings_form']) == false && isset($data['new_license_form']) == false)
		{
			return false;
		}

		$license_number = isset($data['license_number']) ? $data['license_number'] : 0;
		$skill = isset($data['skill']) ? (int)$data['skill'] : 0;

		// skill must be >= 0
		$skill = max(0, $skill);


		update_option('chatrify_uid', $license_number);
		update_option('chatrify_groups', $skill);

		if (isset($data['changes_saved']) && $data['changes_saved'] == '1')
		{
			$this->changes_saved = true;
		}
	}

	public function tracking_code() {

		if ($this->is_installed())
		{
			$skill = $this->get_group();
			$license_number = $this->get_uid();

			$str = <<<HTML
<script type="text/javascript">
	var __ac = {};
    __ac.uid = "{$license_number}";
    __ac.server = "secure.chatrify.com";
    __ac.group_id = {$skill};

    (function() {
    var ac = document.createElement('script'); ac.type = 'text/javascript'; ac.async = true;
    ac.src = 'https://cdn.chatrify.com/go.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ac, s);
    })();
</script>
HTML;

			echo $str;
		}
	}

}

Chatrify::get_instance();