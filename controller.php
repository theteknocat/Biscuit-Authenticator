<?php
define('ACCOUNT_SUSPENDED',1);
define('ACCOUNT_ACTIVE',2);
define('ACCOUNT_PENDING',3);
if (!defined('BISCUIT_HASH_SALT')) {
	// For backwards compatibility with sites that don't have their own unique hash salt defined. Very important to ensure sites get their config updated
	// with a unique one
	define('BISCUIT_HASH_SALT', 'ty1X#BZZ-zP99KTtt9Llpgft9EYAY!wbvg1rJKcI#SD7mhkI');
}
/**
 * Provides user authentication functions
 *
 * @package Modules
 * @subpackage Authenticator
 * @author Peter Epp
 * @copyright Copyright (c) 2009 Peter Epp (http://teknocat.org)
 * @license GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 * @version 2.0 $Id: controller.php 14804 2013-05-08 16:01:47Z teknocat $
 **/
class Authenticator extends AbstractModuleController {
	/**
	 * Constant to use to indicate auto login when calling perform_login method
	 */
	const AUTO_LOGIN = true;
	/**
	 * Constant to use to indicate manual login when calling perform_login method
	 */
	const MANUAL_LOGIN = false;
	/**
	 * Constant to use to indicate skip redirect after login
	 */
	const SKIP_LOGIN_REDIRECT = true;
	/**
	 * Whether or not the user opted to stay logged in and therefore their session should never expire
	 *
	 * @var string
	 */
	protected $_keep_logged_in = false;
	/**
	 * Array of info about the current page's access level (ie. login url, access level name, access level number)
	 *
	 * @var array
	 */
	protected $access_info = array();
	/**
	* Information about the logged in user
	*
	* @var array
	**/
	protected $user;
	/**
	 * Models used by this module
	 *
	 * @var string
	 */
	protected $_models = array(
		"User"                  => "User",
		"AccessLevel"           => "AccessLevel",
		"AccountStatus"         => "AccountStatus",
		"UserEmailVerification" => "UserEmailVerification",
		"PasswordResetToken"    => "PasswordResetToken"
	);
	protected $_uncacheable_actions = array('reset-password','login','logout');
	/**
	 * Define the sorting options for users and access levels
	 * 
	 * @var array
	 */
	protected $_index_sort_options = array(
		'User' => array('first_name' => 'ASC', 'last_name' => 'ASC'),
		'AccessLevel' => array('id' => 'ASC')
	);
	/**
	 * The action that a URL was requested for. Used by the custom "primary_page" method
	 *
	 * @var string
	 */
	protected $_url_action = null;
	/**
	 * List of session variables to keep after logout
	 *
	 * @var string
	 */
	protected $_logout_keepers = array();
	/**
	 * Place to cache the list of access levels
	 *
	 * @var array
	 */
	protected $_access_levels = array();
	/**
	 * Ensure the primary page for this module is "users" for actions other than login and logout, which are special and controlled via rewrite rules
	 *
	 * @var string
	 */
	protected $_primary_page = 'users';
	/**
	 * Place to store the current password reset token found when doing a password reset request
	 *
	 * @var string
	 */
	protected $_current_reset_token;
	/**
	 * Whether or not to automatically log the user in on verification of their account. Safe defaults to false. Extend the controller and set to true to override
	 *
	 * @var bool
	 */
	protected $_auto_login_on_verification = false;
	/**
	 * Whether or not access levels have already been defined
	 */
	protected $_already_defined_access_levels = false;
	/**
	 * Session expiry timeout in seconds. Is set from access_levels table if not overriden
	 *
	 * @var string
	 */
	protected $_session_expiry_timeout = null;
	/**
	 * Place to store extra code to be rendered on the login page
	 *
	 * @var string
	 */
	protected $_extra_login_form_code;

	public function __construct() {
		parent::__construct();
		if (!$this->user_is_logged_in()) {
			// Make Captcha a dependency for creating a new user account if no user is currently logged in
			$this->_dependencies['new'] = 'Captcha';
		}
	}
	/**
	 * Perform setup operations required prior to dispatching to action, including setting up the current user, checking page access permissions etc.
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function run() {
	    $this->register_js('footer', 'session.js');
		$this->set_login_level();
		$this->_set_access_info();
		$this->_session_check();
		$this->_check_page_access();
		$this->PasswordResetToken->trash_expired();
		if ($this->action() == 'new' || $this->action() == 'edit') {
			$this->register_js('footer','pwd_strength.js');
			$this->register_js('footer','user-edit.js');
			$this->register_css(array('filename' => 'pwd_strength.css', 'media' => 'screen'));
		}
		if (!$this->Biscuit->page_cache_is_valid() && !$this->Biscuit->request_is_bad()) {
			parent::run();		// Dispatch to action
		}
	}
	/**
	 * If a user is logged in, check the status of their session, log them out if expired, otherwise extend and also check for special
	 * session extension requests
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function _session_check() {
		Event::fire('login_session_check',$this);
		$this->_keep_logged_in_check();
		if ($this->user_is_logged_in()) {
			if (Session::is_expired() && !Request::is_ping_keepalive()) {
				$this->action_logout(__("Your login session has expired."));
				return;
			}
			if (!$this->active_user()) {
				$this->action_logout(__("User account is no longer available."));
				return;
			}
			Session::set_expiry();
			$this->_check_for_session_extension_requests();
			if ($this->action() != "logout") {
				$this->_set_session_timeout_header();
				// Fire an event to indicate normal logged in state in case any modules need to do something when a user is logged in before anything else happens.
				// This is useful, for example, if you need to do some sort of check on the user's account before they can access the page.
				Event::fire("user_is_logged_in");
			}
		}
	}
	/**
	 * Check if a user id is set for staying logged in
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function _keep_logged_in_check() {
		if (!empty($_COOKIE['stay_logged_in_as'])) {
			$user_hash = $_COOKIE['stay_logged_in_as'];
			Event::fire('keep_logged_in_check', $user_hash);
			if (!$this->user_is_logged_in()) {
				$user = $this->User->find_by("SHA1(CONCAT(`id`, '".BISCUIT_HASH_SALT."'))",$user_hash);
				if ($user) {
					Console::log("Logging user in from cookie...");
					$this->_keep_logged_in = true;
					$this->stuff_active_user($user);
					// Force redirecting to the same page again after auto-login
					$this->params['login_redirect'] = Request::uri();
					$this->perform_login();
					if ($this->_keep_logged_in) {
						// Extend the cookie
						Response::set_cookie('stay_logged_in_as', sha1((int)$user->id().BISCUIT_HASH_SALT), time()+(60*60*24*30), '/');
					}
				}
			} else {
				$user = $this->active_user();
				if (sha1($user->id().BISCUIT_HASH_SALT) == $user_hash) {
					$this->_keep_logged_in = true;
				}
			}
		}
	}
	/**
	 * Check if the current request is a special session extension request and act accordingly
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function _check_for_session_extension_requests() {
		if (Request::is_ping_keepalive()) {
			$this->_set_session_timeout_header();
			$this->Biscuit->render('Session extended');
			Bootstrap::end_program(true);
		}
	}
	/**
	 * Set the session timeout header used on ajax requests to reset the session timeout counter
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function _set_session_timeout_header() {
		$remaining_time = $this->session_remaining_time();
		if ($remaining_time > 0) {
			// Set a special response header for Ajax requests to ensure that the Javascript session timeout tracker starts counting down from the top again:
			Response::add_header('X-Biscuit-Session-Time', $remaining_time);
		}
	}
	/**
	 * Check if the user can access the current page, whether or not their session has expired (if logged in), and act accordingly
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function _check_page_access() {
		if ($this->Biscuit->Page->access_level() > PUBLIC_USER) {
			// If the current page is not public, authenticate the currently logged-in user (if any):
			if (!$this->user_is_logged_in()) {
				Session::flash_unset('login_redirect');
				Session::flash("login_redirect","/".Request::uri());
				Session::flash("user_message",__("Please login to access the requested page."));
				if (Request::is_ajax()) {
					$this->Biscuit->render_js('document.location.href="'.$this->access_info->login_url().'";');
					Bootstrap::end_program();
				}
				else {
					if ($this->access_info->login_url() == Request::uri()) {
						// This should never happen unless someone was silly enough to set the access level of the login page to somehing other than public
						trigger_error('Trying to redirect to login page from login page', E_USER_NOTICE);
					}
					if (Request::uri() != null) {
						Session::flash_unset('login_redirect');
    					Session::flash('login_redirect',Request::uri());
					}
					if (Request::is_ajax() && Request::type() != 'update') {
						Response::http_status(403);
					}
					Response::redirect($this->access_info->login_url()); // Redirect to the login page for this access level
				}
			}
			elseif (!Permissions::can_access($this->Biscuit->Page->access_level())) {
				// The current page has higher than public access restriction, and the currently logged 
				// in user does not have a sufficient access level for this page..
				Session::flash('user_error', __("You do not have sufficient privileges to access the requested page."));
				if (Request::is_ajax()) {
					$this->Biscuit->render_js('document.location.href="'.$redirect_to.'";');
					Bootstrap::end_program();
				} else {
					Response::redirect('/');
				}
				
			}
		}
	}
	/**
	 * Return the amount of time remaining for the current user's session
	 *
	 * @return int
	 * @author Peter Epp
	 */
	protected function session_remaining_time() {
		$expiry_time = Session::get('expires_at');
		return $expiry_time-time();
	}
	/**
	 * Instantiate and return model for the currently logged in user. Note that it is the responsibility of the caller to check on whether
	 * a user is logged in before calling this method.
	 *
	 * @return User
	 * @author Peter Epp
	 */
	public function active_user() {
		if (empty($this->user) && $this->user_is_logged_in()) {
			$auth_data = $this->_get_logged_in_session_data();
			$this->user = $this->User->find($auth_data['id']);
		}
		return $this->user;
	}
	/**
	 * Fetch access levels and set for view prior to running the default action
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_index() {
		$access_levels = $this->AccessLevel->find_all(array('id' => 'ASC'));
		$levels_by_id = array();
		foreach ($access_levels as $access_level) {
			$levels_by_id[$access_level->id()] = $access_level;
		}
		$this->set_view_var('access_levels',$levels_by_id);
		$account_statuses = $this->AccountStatus->find_all(array('name' => 'ASC'));
		$statuses_by_id = array();
		foreach ($account_statuses as $status) {
			$statuses_by_id[$status->id()] = $status;
		}
		$this->set_view_var('account_statuses',$statuses_by_id);
		parent::action_index();
	}
	/**
	 * Setup pagination for the user list and return the limits
	 * 
	 * @return string
	 * @author Peter Epp
	 */
	protected function set_index_limits() {
		$limits = '';
		if ($this->action() == 'index') { // Only when indexing users
			$result_count = $this->User->record_count();
			$paginator = new PaginateIt();
			$paginator->SetItemsPerPage(20);
			$paginator->SetLinksFormat('&laquo; Prev','','Next &raquo;');
			$paginator->SetLinksHref($this->url());
			$paginator->SetItemCount($result_count);
			$limits = $paginator->GetSqlLimit();
			$this->set_view_var('result_count', $result_count);
			$this->set_view_var('paginator', $paginator);
		}
		return $limits;
	}
	/**
	 * Set access levels in a view var for the edit form for super users creating or editing other users so they can set that user's access level
	 *
	 * @param string $mode 
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_edit($mode = 'edit') {
		parent::action_edit($mode);
		if (($this->action() == 'edit' || $this->action() == 'new') && $this->user_is_logged_in() && $this->params['id'] != $this->active_user()->id()) {
			$access_levels = $this->access_levels();
			foreach ($access_levels as $access_level) {
				if ($access_level->id() != PUBLIC_USER && $access_level->id() != SYSTEM_LORD && $access_level->id() <= $this->active_user()->user_level()) {
					$access_level_list[] = array(
						'label' => $access_level->name(),
						'value' => $access_level->id()
					);
				}
			}
			$access_level_list = array_reverse($access_level_list);
			$this->set_view_var('access_level_list',$access_level_list);
			if ($this->user_is_account_administrator()) {
				$account_statuses = $this->AccountStatus->find_all(array('name' => 'ASC'));
				foreach ($account_statuses as $acct_status) {
					$account_status_list[] = array(
						'label' => $acct_status->name(),
						'value' => $acct_status->id()
					);
				}
				$this->set_view_var('account_status_list',$account_status_list);
			}
		}
	}
	/**
	 * Set the title for the edit page based on mode ('new' or 'edit') and the model name
	 *
	 * @param string $mode 
	 * @param string $model_name 
	 * @return void
	 * @author Peter Epp
	 */
	protected function set_edit_title($mode,$model_name) {
		if ($this->Biscuit->Page->slug() == 'users') {
			parent::set_edit_title($mode,$model_name);
		}
	}
	/**
	 * Set the title for the show view based on the user name
	 *
	 * @param string $model_name 
	 * @return void
	 * @author Peter Epp
	 */
	protected function set_show_title($model) {
		$this->title(sprintf(__("Profile of %s"), $model->full_name()));
	}
	/**
	 * Special permission check to see if the current user can edit
	 *
	 * @param string $user 
	 * @return void
	 * @author Peter Epp
	 */
	public function user_can_edit($user = null) {
		if (empty($user) && !empty($this->params['id'])) {
			$user = $this->User->find($this->params['id']);
		}
		if (empty($user)) {
			return (parent::user_can('edit'));
		}
		$active_user = $this->active_user();
		return (parent::user_can('edit') && ($active_user->user_level() >= $user->user_level() || $active_user->id() == $user->id()));
	}
	/**
	 * Special permission check to see if the current user can delete
	 *
	 * @param string $user 
	 * @return void
	 * @author Peter Epp
	 */
	public function user_can_delete($user = null) {
		if (empty($user) && !empty($this->params['id'])) {
			$user = $this->User->find($this->params['id']);
		}
		if (empty($user)) {
			return (parent::user_can('delete'));
		}
		$active_user = $this->active_user();
		return (parent::user_can('delete') && $user->user_level() < SYSTEM_LORD && $active_user->user_level() >= $user->user_level());
	}
	/**
	 * Custom edit permission check for access levels to prevent editing of public level
	 *
	 * @param string $access_level 
	 * @return void
	 * @author Peter Epp
	 */
	public function user_can_edit_access_level($access_level = null) {
		if (empty($access_level) && (!empty($this->params['id']) || $this->params['id'] === 0 || $this->params['id'] === '0')) {
			$access_level = $this->AccessLevel->find($this->params['id']);
		}
		if (empty($access_level)) {
			return (parent::user_can('edit_access_level'));
		}
		return (parent::user_can('edit_access_level') && $access_level->id() != 0);
	}
	/**
	 * Custom permission check for deleting an access level. May not delete the public or system lord levels
	 *
	 * @param string $access_level 
	 * @return void
	 * @author Peter Epp
	 */
	public function user_can_delete_access_level($access_level = null) {
		if (empty($access_level) && (!empty($this->params['id']) || $this->params['id'] === 0 || $this->params['id'] === '0')) {
			$access_level = $this->AccessLevel->find($this->params['id']);
		}
		if (empty($access_level)) {
			return (parent::user_can('delete_access_level'));
		}
		return (parent::user_can('delete_access_level') && $access_level->id() != PUBLIC_USER && $access_level->id() != SYSTEM_LORD);
	}
	/**
	 * Log a user in if their credentials are valid, otherwise spit out an error
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_login() {
		if (!Request::is_post()) {
			if ($this->user_is_logged_in() && !$this->is_login_dialog_request()) {
				Session::flash('user_message',__('You are already logged in!'));
				Response::redirect('/');
			}
			if (!empty($this->params['ref_page'])) {
				Session::flash_unset('login_redirect');
				Session::flash('login_redirect',$this->params['ref_page']);
			}
			$this->set_view_var('is_login_dialog_request',$this->is_login_dialog_request());
			Event::fire("render_login_form",$this);
			$this->set_view_var('extra_login_form_code',$this->_extra_login_form_code);
			$this->render();
			return;
		}
		$valid_credentials = $this->valid_credentials();
		if ($valid_credentials && $this->account_is_active()) {
			if ($this->is_ajaxy_login() && !$this->is_login_dialog_request()) {
				$this->Biscuit->render_json(array(true));
			} else {
				if (!empty($this->params['keep_logged_in'])) {
					$this->_keep_logged_in = true;
					Response::set_cookie('stay_logged_in_as', sha1((int)$this->active_user()->id().BISCUIT_HASH_SALT), time()+(60*60*24*30), '/');
				}
				$this->perform_login();
			}
		} else {
			if ($valid_credentials && !$this->account_is_active()) {
				$status = $this->AccountStatus->find($this->user->status());
				$message = sprintf(__("Sorry, your account is currently %s."),__($status->name()));
			} else {
				$message = __("Invalid username or password.");
			}
			if ($this->is_ajaxy_login()) {
				Response::http_status(406);
				$this->Biscuit->render_json(array('message' => $message));
			} else {
				Console::log("Login is not ajaxy, setting response message and rendering");
				Session::flash("user_error", $message);
				if (isset($this->params['login_redirect'])) {
					Session::flash_unset('login_redirect');
	    			Session::flash('login_redirect',$this->params['login_redirect']);
				}
				$this->render();
			}
		}
	}
	/**
	 * Hook for adding extra code to login form, to be placed under the login fields
	 *
	 * @param string $code 
	 * @return void
	 * @author Peter Epp
	 */
	public function add_login_form_code($code) {
		$this->_extra_login_form_code = $code;
	}
	/**
	 * Perform actual user login - set their info in session and handle the response
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function perform_login($is_auto_login = false, $skip_redirect = false) {
		$this->_set_logged_in_session_data();
		Session::set_expiry();

		$session_timeout = $this->session_timeout();

		if (!$this->is_login_dialog_request() && !$this->_keep_logged_in) {
			if ($session_timeout > 0) {
				$remaining_minutes = $this->session_remaining_time()/60;
				$message = 'Your login session will expire after %d minutes of inactivity.';
				if ($is_auto_login) {
					$full_message = sprintf(__($message),$remaining_minutes);
				} else {
					$message = 'Welcome %s. '.$message;
					$full_message = sprintf(__($message),$this->active_user()->full_name(),$remaining_minutes);
				}
			} else {
				$full_message = sprintf(__('Welcome %s.'),$this->active_user()->full_name());
			}
			if (!empty($full_message)) {
				Session::flash('user_success', '<strong>'.$full_message.'</strong>');
			}
		}

		Console::log("                        Successful login for ".$this->active_user()->username());

		// Default to home page after login
		$redirect_page = '/';

		$access_level_home_url = $this->_get_access_level_home($this->active_user()->user_level());

		if (!empty($this->params['login_redirect'])) {
			$redirect_page = $this->params['login_redirect'];
		} else if (!empty($access_level_home_url)) {
			$redirect_page = $access_level_home_url;
		}

		if ($redirect_page == $this->access_info->login_url()) {
			if (!empty($access_level_home_url)) {
				$redirect_page = $access_level_home_url;
			} else {
				$redirect_page = "/";
			}
		}
		if ($this->is_ajaxy_login() && $this->is_login_dialog_request()) {
			// Send JSON response with remaining session time
			$this->Biscuit->render_json(array('remaining_session_time' => $this->session_remaining_time()));
		} else if (!$skip_redirect) {
			// Normal redirect
			Response::redirect($redirect_page);
		}
	}
	/**
	 * Stuff the active user with any one we choose. This provides a means for authentication extension modules to decide who the current is, for
	 * example a Facebook login module can use this after it's authenticated the Facebook user and created a local user account using their Facebook
	 * data. It can call this method, followed by perform_login() to log in the desired user without passing them through the normal login process.
	 *
	 * @param string $user 
	 * @return void
	 * @author Peter Epp
	 */
	public function stuff_active_user($user) {
		if (is_object($user)) {
			$this->user = $user;
		}
	}
	/**
	 * Whether or not this is a login request from the popup dialog
	 *
	 * @author Peter Epp
	 */
	protected function is_login_dialog_request() {
		return (!empty($this->params['login_dialog_request']) && $this->params['login_dialog_request'] == 1);
	}
	/**
	 * Whether or not this is an ajaxy login request
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function is_ajaxy_login() {
		return (Request::is_ajax() && Request::type() == "login");
	}
	/**
	 * Log the user out
	 *
	 * @param string $user_msg Optional message to display after logout is complete
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_logout($user_msg = null) {
		if (!empty($this->params['js_auto_logout']) && $this->params['js_auto_logout'] == 1) {
			$user_msg = "<strong>".__("You were automatically logged out due to inactivity.")."</strong>";
		} else if (empty($user_msg)) {
			$user_msg = __("You have been logged out.");
		}
		if (!Session::already_flashed('user_message',$user_msg) && !$this->is_login_dialog_request()) {
			Session::flash("user_message",$user_msg);
		}
		// Reset the session, keeping the flash variables in tact
		$this->keep_session_var("flash");
		
		// In case any modules need to do something before the user is logged out
		Event::fire("logout");

		Console::log("                        Clearing session...");
		Session::reset(true,$this->_logout_keepers);
		if (!empty($_COOKIE['stay_logged_in_as'])) {
			Console::log("Resetting cookie that keeps user logged in...");
			Response::set_cookie('stay_logged_in_as', "", time()-3600, '/');
		}
		Console::log("                        Redirecting user...");

		if ($this->is_login_dialog_request()) {
			return;
		}

		if (!empty($this->params['ref_page'])) {
			$gopage = $this->params['ref_page'];
			if (substr($gopage,0,1) != '/') {
				$gopage = '/'.$gopage;
			}
		}
		else {
			$request_uri = Request::uri();
			if (!empty($this->params['js_auto_logout'])) {
				$request_uri = preg_replace('/\?js_auto_logout\=(0|1)/','',$request_uri);
			}
			$gopage = str_replace('logout','',$request_uri);
		}
		if (!Request::is_ajax()) {
			Response::redirect($gopage);
		}
		else {
			if ($this->Biscuit->Page->access_level() > PUBLIC_USER) {
				// Only redirect if the page doesn't have public access
				$this->Biscuit->render_js('document.location.href="'.$gopage.'"');
				Bootstrap::end_program();
			}
			else {
				// Otherwise let the page continue to run and render
				// However, if "logout" is still the action other modules may fail to load their content
				// As such we'll clear the action from the query so other modules will default to "index"
				Request::clear_query('action');
				Request::clear_form('action');
				Request::set_user_input();
			}
		}
	}
	/**
	 * Add a session variable to the list of ones to keep after logout.  This method is intended to be used by other modules responding to the "logout" event
	 * the logout event.
	 *
	 * @param string $varname 
	 * @return void
	 * @author Peter Epp
	 */
	public function keep_session_var($varname) {
		Console::log("                        Keeping session var: ".$varname);
		$this->_logout_keepers[] = $varname;
	}
	/**
	 * Handle a password reset request. Defers to other methods for the current step of the reset process
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_reset_password() {
		if (!$this->has_valid_reset_token()) {
			$this->set_view_var('reset_email_sent',false);
			$this->set_view_var('current_step',1);
			$this->reset_password_step_1();
		} else {
			$this->set_view_var('current_step',2);
			$this->reset_password_step_2();
		}
		$this->render();
	}
	/**
	 * Whether or not the current password reset request contains a valid reset token
	 *
	 * @return bool
	 * @author Peter Epp
	 */
	protected function has_valid_reset_token() {
		if (empty($this->params['reset_token'])) {
			return false;
		} else {
			$token = $this->PasswordResetToken->find_valid_by_token($this->params['reset_token']);
			if (!$token) {
				Session::flash('user_error',__("The token you supplied is invalid or expired. If you just clicked on the reset link from your email, please start again to get a new reset token."));
				return false;
			}
			$this->_current_reset_token = $token;
			return true;
		}
	}
	/**
	 * Process step 1 of password reset
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function reset_password_step_1() {
		if (Request::is_post()) {
			if (!empty($this->params['username'])) {
				$user = $this->User->find_by('username',$this->params['username']);
			}
			if (empty($user)) {
				Session::flash('user_error',__("User account could not be found"));
			} else {
				if (!$user->email_address()) {
					Session::flash('user_error', 'There is no email address associated with your account. Please contact the site administrator for assistance.');
					Response::redirect('/reset-password');
				} else {
					$token_code = sha1(Crumbs::random_password(13).microtime());
					$token_data = array(
						'user_id' => $user->id(),
						'token'   => $token_code
					);
					$token = $this->PasswordResetToken->create($token_data);
					$token->save();
					$this->send_password_reset_email($user,$token);
					$this->set_view_var('reset_email_sent',true);
					$this->set_view_var('user',$user);
				}
			}
		}
	}
	/**
	 * Email the user a password reset token
	 *
	 * @param string $user 
	 * @return void
	 * @author Peter Epp
	 */
	protected function send_password_reset_email($user,$token) {
		$options = array(
			"To"          => $user->email_address(),
			"From"        => Crumbs::site_from_address(),
			"FromName"    => SITE_TITLE,
			"Subject"     => __("Password Reset Token")
		);
		$message_vars = array(
			'user_name' => $user->full_name(),
			'reset_url' => STANDARD_URL.'/reset-password/'.$token->token()
		);
		$mail = new Mailer();
		return $mail->send_mail("authenticator/views/reset_password/reset_link",$options,$message_vars);
	}
	/**
	 * Process step 2 of password reset
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function reset_password_step_2() {
		if (Request::is_post()) {
			$user = $this->User->find($this->params['id']);
			$user->set_attributes($this->params['user']);
			if ($this->validate_reset_password($user) && $user->save()) {
				// Toss the token we were using now that reset is complete:
				$this->_current_reset_token->delete();
				// Notify the user off success and return to the login page:
				Session::flash('user_success',sprintf(__("Thank you, %s, your password has been successfully reset. You can now login with your new password."),$user->full_name()));
				Response::redirect($this->login_url());
			}
		} else {
			$this->register_js('footer','pwd_strength.js');
			$this->register_css(array('filename' => 'pwd_strength.css', 'media' => 'screen'));
			$user = $this->User->find($this->_current_reset_token->user_id());
		}
		$this->set_view_var('user',$user);
	}
	/**
	 * Respond to an email verification request
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_verify_email() {
		$user_verified = false;
		if (!empty($this->params['user_hash'])) {
			$user_verification = $this->UserEmailVerification->find_by('hash',$this->params['user_hash']);
			if ($user_verification) {
				$user_verified = true;
				if ($user_verification->verified() == 0) {
					$user_verification->set_verified(1);
					$user_verification->save();
					$user = $this->User->find($user_verification->user_id());
					$user->set_status(ACCOUNT_ACTIVE);
					$user->save();
					if ($this->_auto_login_on_verification) {
						$message = 'Thank you, %s, your account has been verified.';
					} else {
						$message = 'Thank you, %s, your account has been verified and is ready to use. Please login to access your account.';
					}
					Session::flash('user_success',sprintf(__($message),$user->full_name()));
					Event::fire('user_account_verified',$user_verification->user_id());
				} else {
					Session::flash('user_message',__('Your user account has already been verified. Please login to access it.'));
					Response::redirect($this->login_url());
				}
			}
		}
		if ($user_verified) {
			if ($this->_auto_login_on_verification) {
				$this->user = $user;
				$this->perform_login(true);
			} else {
				Response::redirect($this->login_url());
			}
		} else {
			Session::flash('user_error',__("Could not verify user account. If you clicked a link from your email, check to ensure the link did not get broken into more than one line. If it did, you may need to copy and paste it into your browser."));
			Response::redirect('/');
		}
	}
	/**
	 * Check if a user ID exists. This always expects to be called by ajax and therefore always renders a JSON response
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_check_username() {
		$response = array();
		if (!empty($this->params['username'])) {
			$user = $this->User->find_by('username',$this->params['username']);
			$response = array(
				'exists' => is_object($user)
			);
		}
		$this->Biscuit->render_json($response);
	}
	/**
	 * Validate user input for step 2 of a password reset request
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function validate_reset_password($user = null) {
		if (empty($user)) {
			$user = $this->User->find($this->params['id']);
		}
		$user->set_attributes($this->params['user']);
		$is_valid = $user->validate();
		if (!$is_valid) {
			$this->_validation_errors = $user->errors();
			$this->_invalid_fields = $user->invalid_attributes();
		}
		// If the security question and answer are required fields for the current site, validate that the user has provided the answer to their
		// security question
		if ($user->has_security_question() && $user->has_security_answer() && $user->security_question_is_required() && $user->security_answer_is_required()) {
			if (!isset($this->params['security_answer']) || empty($this->params['security_answer']) || $this->params['security_answer'] != $user->security_answer()) {
				$this->_validation_errors['security_answer'] = __('Please provide the answer to your security question');
				$this->_invalid_fields[] = 'security_answer';
				$is_valid = false;
			}
		}
		return $is_valid;
	}
	/**
	 * Validate the credentials submitted by the user
	 *
	 * @return void bool Whether or not credentials are valid
	 * @author Peter Epp
	 */
	protected function valid_credentials() {
		if (!$this->credentials_submitted()) {
			Console::log("                        Login_info is empty");
			return false;
		}
		if ($this->credentials_submitted_empty()) {
			Console::log("                        Empty username or password");
			return false;
		}

		$login_info = $this->params['login_info'];
		$user = $this->_get_user_data($login_info);
		if (!$user || !$this->_passwords_match(Crumbs::html_entity_decode(H::purify_text($login_info['password'])), $user->password())) {
			Console::log("                        User not found or password mismatch");
			return false;
		}
		$this->user = $user;
		return true;
	}
	/**
	 * Whether or not the current user account has active status
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function account_is_active() {
		$user = $this->active_user();
		if (!empty($user)) {
			return ($user->status() == ACCOUNT_ACTIVE);
		}
		return false;
	}
	/**
	 * Check if login information was submitted
	 *
	 * @return bool Whether or not login info was submitted
	 * @author Peter Epp
	 */
	protected function credentials_submitted() {
		return !empty($this->params['login_info']);
	}
	/**
	 * Check if one of the submitted login info fields is empty
	 *
	 * @return bool Whether or not a login field was empty
	 * @author Peter Epp
	 */
	protected function credentials_submitted_empty() {
		return (empty($this->params['login_info']['username']) || empty($this->params['login_info']['password']));
	}
	/**
	 * Set $this->login_level
	 *
	 * @return void
	 **/
	protected function set_login_level() {
		// Determine the access level for the page
		if ($this->Biscuit->Page->access_level() > 0) {
			// If it's not a public page, set the access level to that of the current page as defined in the page_index table
			$this->login_level = $this->Biscuit->Page->access_level();
		}
		elseif (!empty($this->params['login_level'])) {
			// Otherwise if the current page is public (access level 0), and there's a "login_level" form field submitted, which would be the case if the user
			// submitted a login form, set the login level to that of the submitted form
			$this->login_level = $this->params['login_level'];
		}
		else {
			// Otherwise default to the public level
			$this->login_level = PUBLIC_USER;
		}	
		Console::log("                        Login level: ". $this->login_level);
	}

	/**
	 * Is a user currently logged in?
	 *
	 * @return boolean
	 * @author Lee O'Mara
	 **/
	public function user_is_logged_in() {
		$auth_data = $this->_get_logged_in_session_data();
		return (!empty($auth_data) && !empty($auth_data['id']));
	}
	/**
	 * If the current user has super (system lord) user access
	 *
	 * @return bool
	 * @author Peter Epp
	 */
	public function user_is_super() {
		if ($this->user_is_logged_in()) {
			$curr_user = $this->active_user();
			return ($curr_user->user_level() == SYSTEM_LORD);
		}
		return false;
	}
	/**
	 * Hook for allowing a customised version of the module to define whether or not the currently active user has the power to administer user accounts.
	 * By default it's synonymous to user_is_super()
	 *
	 * @return bool
	 * @author Peter Epp
	 */
	public function user_is_account_administrator() {
		return $this->user_is_super();
	}
	/**
	 * Fetch the URL to redirect the user to after they logout
	 *
	 * @return string URL relative to the site root
	 * @author Peter Epp
	 */
	public function login_url() {
		if ($this->user_is_logged_in()) {
			$auth_data = $this->_get_logged_in_session_data();
			$curr_access_level = $auth_data['user_level'];
		} else {
			$curr_access_level = PUBLIC_USER;
		}
		$access_levels = $this->access_levels();
		foreach ($access_levels as $access_level) {
			if ($access_level->id() == $curr_access_level) {
				return $access_level->login_url();
			}
		}
	}
	/**
	 * Return the home url for the current user based on their user level.  This method does not check if a user is logged in, so you must check that before calling this method
	 *
	 * @return string
	 * @author Peter Epp
	 */
	public function user_home_url() {
		return $this->_get_access_level_home($this->active_user()->user_level());
	}
	/**
	 * Map global variable names to access level numbers so that scripts and plugins do not need to rely on every site have specific level numbers.
	 * Default globals that are expect by the system are PUBLIC_USER, WEBMASTER, ADMINISTRATOR and SYSTEM_LORD, so errors may occur if these four
	 * levels do not exist in the database. SYSTEM_LORD is the programmer level, and is intended for use when programmer-level functions are incorporated
	 * such as install/updating scripts.
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function define_access_levels() {
		if (!$this->_already_defined_access_levels) {
			$access_levels = $this->access_levels();
			Console::log("        Authenticator: Defining system access levels:");
			foreach ($access_levels as $access_level) {
				define($access_level->var_name(),$access_level->id());
				Console::log("            ".$access_level->var_name()." = ".$access_level->id());
			}
			$this->_already_defined_access_levels = true;
		}
	}
	/**
	 * Return all the current access levels, finding them if they are not currently set
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function access_levels() {
		if (empty($this->_access_levels)) {
			$this->_access_levels = $this->AccessLevel->find_all(array('id' => 'ASC'));
			if (empty($this->_access_levels)) {
				throw new ModuleException('No user access levels defined!');
			}
		}
		return $this->_access_levels;
	}
	/**
	* Return a user object by username, if the user exists in the database
	* 
	* Override this method in descendants to accommodate varying table fields
	* 
	* @return abject False if no user found
	**/
	protected function _get_user_data($login_info) {
		return $this->User->find_by('username',H::purify_text($login_info['username']));
	}
	/**
	* Set $this->access info
	* 
	* The access_name and login_url for a specific login level
	*
	* @return void
	**/
	protected function _set_access_info() {
		// Collect the access level data for the current login level:
		$this->access_info = $this->AccessLevel->find($this->login_level);
	}
	/**
	* Do the given passwords match?
	*
	* @return boolean
	* @author Lee O'Mara
	**/
	protected function _passwords_match($user_input, $stored_pass) {
		if ($this->use_hash() == 'secure-hasher') {
			require_once('authenticator/vendor/PasswordHash.php');
			$hasher = new PasswordHash(8, false);
			return $hasher->CheckPassword($user_input, $stored_pass);
		}
		return ($this->hash_password($user_input) == $stored_pass);
	}
	/**
	 * Whether or not to use a hash function when matching password.
	 *
	 * @return mixed False if hash should not be used, otherwise name of hash function to use if defined
	 * @author Peter Epp
	 */
	protected function use_hash() {
		if (defined("USE_PWD_HASH")) {
			$use_hash = USE_PWD_HASH;
		} else {
			$use_hash = "";
		}
		if (!empty($use_hash) && function_exists($use_hash)) {
			return $use_hash;
		}
		// If not otherwise defined, fall back on the secure hasher (PasswordHash framework included in the vendor folder)
		return 'secure-hasher';
	}
	/**
	 * Hash the provided password for storing in the database (if required) using the hash method defined in the system settings
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function hash_password($password) {
		$use_hash = $this->use_hash();
		if ($use_hash == 'secure-hasher') {
			require_once('authenticator/vendor/PasswordHash.php');
			$hasher = new PasswordHash(8, false);
			return $hasher->HashPassword($password);
		}
		return call_user_func_array($use_hash, array($password));
	}
	/**
	 * Return the URL for the homepage of this level user
	 *
	 * @return string
	 * @author Lee O'Mara
	 **/
	protected function _get_access_level_home($user_level) {
		$access_levels = $this->access_levels();
		foreach ($access_levels as $access_level) {
			if ($access_level->id() == $user_level) {
				return $access_level->home_url();
			}
		}
	}
	/**
	* Set values in the session after a successful login
	*
	* @return void
	* @author Lee O'Mara
	**/
	protected function _set_logged_in_session_data() {
		$auth_data['id']         = (int)($this->active_user()->id());
		$auth_data['username']   = $this->active_user()->username();
		$auth_data['user_level'] = $this->active_user()->user_level();
		Session::set('auth_data',$auth_data);
	}
	/**
	 * Return logged in session data, if present
	 *
	 * @return array|null
	 * @author Peter Epp
	 */
	protected function _get_logged_in_session_data() {
		if (Session::var_exists('auth_data')) {
			return Session::get('auth_data');
		}
		return null;
	}
	/**
	 * Return the session timeout in seconds for the currently logged in user's level
	 *
	 * @return int Number of seconds
	 * @author Peter Epp
	 */
	public function session_timeout() {
		if ($this->user_is_logged_in() && !$this->_keep_logged_in) {
			if (empty($this->_session_expiry_timeout)) {
				$auth_data = $this->_get_logged_in_session_data();
				$this->_session_expiry_timeout = DB::fetch_one("SELECT `session_timeout` FROM `access_levels` WHERE `id` = ?", $auth_data['user_level']);
			}
			if ($this->_session_expiry_timeout > 0) {
				return intval($this->_session_expiry_timeout,10) * 60;
			}
		}
		return 0;
	}
	/**
	 * Return the unix timestamp of the session expiry time
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function session_expires_at() {
		Event::fire("set_session_expiry_time",$this);
		$session_timeout = $this->session_timeout();
		if ($session_timeout) {
			return time() + $session_timeout;
		}
		return 0;
	}
	/**
	 * Explicitly set session timeout in seconds - use to override access level default by hooking in to set_session_expiry_time event.
	 *
	 * @param string $seconds 
	 * @return void
	 * @author Peter Epp
	 */
	public function set_session_timeout($seconds) {
		$this->_session_expiry_timeout = $seconds;
	}
	/**
	 * Define access levels prior to dispatch
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_dispatch_request() {
		$this->define_access_levels();
		parent::act_on_dispatch_request();
	}
	/**
	 * Define access levels on cron run
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_cron_run() {
		$this->define_access_levels();
	}
	public function render_password_strength_meter($attribute_id) {
		return Crumbs::capture_include('authenticator/views/pwd-strength-meter.php',array('attribute_id' => $attribute_id));
	}
	protected function primary_page() {
		if (empty($this->_url_action)) {
			$this->_url_action = 'index';
		}
		switch ($this->_url_action) {
			case 'index':
			case 'edit':
				return 'users';
				break;
			case 'login':
				return 'login';
				break;
			case 'logout':
				return 'logout';
				break;
			case 'reset_password':
				return 'reset-password';
				break;
			default:
				return parent::primary_page();
		}
	}
	public function url($action='index',$id=null) {
		$this->_url_action = $action;
		$url = parent::url($action,$id);
		if ($action == 'index_access_level') {
			$url = str_replace('index_access_level', 'access_levels', $url);
		}
		return $url;
	}
	/**
	 * Provide mapping rules for any login pages other than "login" as well as logout
	 *
	 * @return array
	 * @author Peter Epp
	 */
	public static function uri_mapping_rules() {
		$mapping_rules = array(
			'action=index_access_level' => '/^(?P<page_slug>users)\/access_levels\/?$/',
			'action=logout'    => '/^(?P<ref_page>[^\.]+)\/(?P<page_slug>logout)\/?$/',
			'page_slug=logout' => '/^(?P<action>logout)\/?$/',
			'/^(?P<page_slug>users)\/(?P<action>verify_email)\/(?P<user_hash>[a-zA-Z0-9]+)\/?$/',
			'action=reset_password' => '/^(?P<page_slug>reset-password)(\/(?P<reset_token>[a-zA-Z0-9]+))?\/?$/',
			'page_slug=index' => '/^(?P<action>ping)(\/[0-9]+)?/',
			'/^(?P<page_slug>users)\/(?P<action>check_username)\/(?P<username>[a-zA-Z0-9_\-\.\@]+)\/?$/'
		);
		$access_levels = ModelFactory::instance('AccessLevel')->find_all();
		foreach ($access_levels as $access_level) {
			if ($access_level->login_url() !== null) {
				$slug = substr($access_level->login_url(),1);
				$slug = preg_replace('/\//','\/',$slug);
				$key = 'page_slug='.$slug.'&action=login';
				$value = '/^'.$slug.'$/';
				$mapping_rules[$key] = $value;
			}
		}
		return $mapping_rules;
	}
	/**
	 * Custom method for adding extra breadcrumbs that only adds crumb for the current action if it's not "login"
	 *
	 * @param Navigation $Navigation 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_build_breadcrumbs($Navigation) {
		if ($this->Biscuit->Page->slug() == 'users' && $this->action() != 'login' && $this->action() != 'reset_password') {
			parent::act_on_build_breadcrumbs($Navigation);
		}
	}
	/**
	 * Add user create and manage links to admin menu if current user has permission for those actions
	 *
	 * @param string $caller 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_build_admin_menu($caller) {
		$menu_items = array();
		if ($this->user_can_index()) {
			$menu_items['Manage Users'] = array(
				'url' => $this->url(),
				'ui-icon' => 'ui-icon-person'
			);
		}
		if ($this->user_can_create()) {
			$menu_items['Add User'] = array(
				'url' => $this->url('new'),
				'ui-icon' => 'ui-icon-plus'
			);
		}
		if ($this->user_can_index_access_level()) {
			$menu_items['Manage Access Levels'] = array(
				'url' => $this->url('index_access_level'),
				'ui-icon' => 'ui-icon-key'
			);
		}
		if (!empty($menu_items)) {
			$caller->add_admin_menu_items('User Management',$menu_items);
		}
	}
	/**
	 * Add JS code for session tracker to footer when user is logged in and not in debug mode on local machine
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_compile_footer() {
		if ($this->user_is_logged_in() && $this->session_timeout() > 0 && !(SERVER_TYPE == 'LOCAL_DEV' && DEBUG)) {
			$remaining_time = $this->session_remaining_time();
			$js_code = <<<JAVASCRIPT
<script type="text/javascript">
	$(document).ready(function() {
		Biscuit.Session.remaining_time = $remaining_time;
		Biscuit.Session.InitTracker();
	});
</script>
JAVASCRIPT;
			$this->Biscuit->append_view_var('footer',$js_code);
		}
	}
	/**
	 * Upon successful save of a new user model, if user registered publicly setup an email address verification
	 *
	 * @param string $model 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_successful_save($model) {
		if ($this->is_primary()) {
			$model_name = Crumbs::normalized_model_name($model);
			if ($model_name == 'User' && $this->action() == 'new' && !$this->user_is_logged_in()) {
				// Public user just registered, setup email verification:
				$model->set_status(ACCOUNT_PENDING);
				$model->save();
				$verification_hash = sha1($model->id().microtime());
				$verification_data = array(
					'user_id'  => $model->id(),
					'hash'     => $verification_hash,
					'verified' => 0
				);
				$user_verification = $this->UserEmailVerification->create($verification_data);
				$user_verification->save();
				$from_address = Crumbs::site_from_address();
				$verification_success = $this->send_new_user_verification_email($model,$verification_hash);
				if ($verification_success = "+OK") {
					Session::flash('user_message',__("Your new account has been successfully created. However, in order to use your new account you will need to confirm your email address. Please check your email now and click the verification link. Be sure to check your junk folder, and add ".$from_address." to your spam filter white list to ensure that you receive it."));
				} else {
					Session::flash("user_error",__("Your new account has been successfully created. However, I was unable to send you an account verification email. Please contact the system administrator to resolve the issue and activate your account."));
				}
			}
		}
	}
	/**
	 * Handle deletion of own user account
	 *
	 * @param string $model 
	 * @param string $url 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_successful_delete($model,$url) {
		if ($this->is_primary() && Crumbs::normalized_model_name($model) == 'User') {
			$curr_user = $this->active_user();
			if ($curr_user->id() == $model->id()) {
				Session::flash('user_success',__('Your user account has been removed and you have been logged out.'));
				// If the current user just deleted themself, they need to be logged out
				$this->keep_session_var("flash");
				// In case any modules need to do something before the user is logged out
				Event::fire("logout");
				Session::reset(true,$this->_logout_keepers);
			}
		}
	}
	/**
	 * Ignore request tokens for login request
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_request_token_check() {
		if ($this->action() == 'login') {
			RequestTokens::set_ignore($this->Biscuit->Page->hyphenized_slug());
		}
	}
	/**
	 * Send an email address verification message to the user with a link to confirm their account
	 *
	 * @param string $user 
	 * @return void
	 * @author Peter Epp
	 */
	protected function send_new_user_verification_email($user,$hash) {
		$options = array(
			"To"          => $user->email_address(),
			"From"        => Crumbs::site_from_address(),
			"FromName"    => SITE_TITLE,
			"ReplyTo"     => OWNER_EMAIL,
			"ReplyToName" => OWNER_FROM,
			"Subject"     => "New Account Verification"
		);
		$message_vars = array(
			'Authenticator' => $this,
			'user'          => $user,
			'user_hash'     => $hash
		);
		$mail = new Mailer();
		return $mail->send_mail("authenticator/views/email_verification",$options,$message_vars);
	}
}
