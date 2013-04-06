<?php
define('ACCOUNT_SUSPENDED',1);
define('ACCOUNT_ACTIVE',2);
/**
 * Provides user authentication functions
 *
 * @package Modules
 * @author Peter Epp
 * @copyright Copyright (c) 2009 Peter Epp (http://teknocat.org)
 * @license GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 * @version 2.0
 **/
class Authenticator extends AbstractModuleController {
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
		"User" => "User",
		"AccessLevels" => "AccessLevels"
	);
	protected $_uncacheable_actions = array('reset-password','login','logout');
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
	protected $_dependencies = array('PrototypeJs');
	protected $_access_levels = array();

	public function __construct() {
		parent::__construct();
		if (!$this->user_is_logged_in()) {
			// Make Captcha a dependency for creating a new user account if no user is currently logged in
			$this->_dependencies['new'] = 'Captcha';
		}
	}

	public function run() {
		$this->set_login_level();
		$this->_set_access_info();
		$this->check_page_access();
		if ($this->action() == 'new' || $this->action() == 'edit') {
			$this->register_js('footer','pwd_strength.js');
			$this->register_css(array('filename' => 'pwd_strength.css', 'media' => 'screen'));
		}
		parent::run();		// Dispatch to action
	}
	/**
	 * Check if the user can access the current page, whether or not their session has expired (if logged in), and act accordingly
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function check_page_access() {
		// If no input processing occurred above, then we just do the normal user authentication:
		if ($this->user_is_logged_in()) {
			if (Session::is_expired() && !Request::is_ping_keepalive()) {
				$this->action_logout("Your login session has expired.");
				return;
			}
			Session::set_expiry();
			if (Request::is_ping_keepalive()) {
				Console::log("Nothing more than a keep-alive ping. No need to continue.");
				Bootstrap::end_program();
			}
			else if ($this->action() != "logout") {
				// Fire an event to indicate normal logged in state in case any modules need to do something when a user is logged in before anything else happens.
				// This is useful, for example, if you need to do some sort of check on the user's account before they can access the page.
				Event::fire("user_is_logged_in");
			}
		}
		if ($this->Biscuit->Page->access_level() > PUBLIC_USER) {
			// If the current page is not public, authenticate the currently logged-in user (if any):
			if (!$this->user_is_logged_in()) {
				Session::flash_unset('login_redirect');
				Session::flash("login_redirect","/".Request::uri());
				Session::flash("user_message","Please login to access the requested page.");
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
					Response::redirect($this->access_info->login_url()); // Redirect to the login page for this access level
				}
			}
			elseif (!Permissions::can_access($this->Biscuit->Page->access_level())) {
			    // The current page has higher than public access restriction, and the currently logged 
			    // in user does not have a sufficient access level for this page..
				if (Request::referer() && Request::referer() != Request::uri()) {
				   $redirect_to = Request::referer();
				} else {
				   $redirect_to = '/';
				}
				Session::flash('user_error', "You do not have sufficient member privileges to access the requested page.");
				if (Request::is_ajax()) {
					$this->Biscuit->render_js('document.location.href="'.$redirect_to.'";');
					Bootstrap::end_program();
				} else {
					Response::redirect($redirect_to);
				}
				
			}
		}
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
			$auth_data = Session::get("auth_data");
			$user_factory = new ModelFactory("User");
			$this->user = $user_factory->find($auth_data['id']);
		}
		return $this->user;
	}
	protected function action_index() {
		$access_levels = $this->AccessLevels->find_all(array('id' => 'ASC'));
		$levels_by_id = array();
		foreach ($access_levels as $access_level) {
			$levels_by_id[$access_level->id()] = $access_level;
		}
		$this->set_view_var('access_levels',$levels_by_id);
		parent::action_index();
	}
	protected function action_edit($mode = 'edit') {
		parent::action_edit($mode);
		if ($this->user_is_super()) {
			$access_levels = $this->access_levels();
			foreach ($access_levels as $access_level) {
				if ($access_level->id() != PUBLIC_USER) {
					$access_level_list[] = array(
						'label' => $access_level->name(),
						'value' => $access_level->id()
					);
				}
			}
			$this->set_view_var('access_level_list',$access_level_list);
		}
	}
	public function user_can_edit($user = null) {
		if (empty($user)) {
			return (parent::user_can('edit'));
		}
		$active_user = $this->active_user();
		return (parent::user_can('edit') && ($active_user->user_level() > $user->user_level() || $active_user->id() == $user->id()));
	}
	public function user_can_delete($user = null) {
		if (empty($user)) {
			return (parent::user_can('delete'));
		}
		$active_user = $this->active_user();
		return (parent::user_can('delete') && $active_user->user_level() > $user->user_level());
	}
	/**
	 * Log a user in if their credentials are valid, otherwise spit out an error
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_login() {
		if (!Request::is_post()) {
			if (!empty($this->params['ref_page'])) {
				Session::flash_unset('login_redirect');
				Session::flash('login_redirect',$this->params['ref_page']);
			}
			$this->render();
			return;
		}
		$is_ajaxy_login = (Request::is_ajax() && Request::type() == "login");
		$valid_credentials = $this->valid_credentials();
		if ($this->valid_credentials()) {
			if (!$is_ajaxy_login) {
				$login_info = $this->params['login_info'];
				Session::flash('user_success', 'Logged in');
				Console::log("                        Successful login for ".$login_info['username']);

				$this->_set_logged_in_session_data();
				Session::set_expiry();
				$access_level_home_url = $this->_get_access_level_home($this->active_user()->user_level());

				$redirect_page = (!empty($this->params['login_redirect'])) ? 
					$this->params['login_redirect'] : 
					$access_level_home_url;

				if ($this->access_info->login_url() && $redirect_page == $this->access_info->login_url()) {
					$redirect_page = $access_level_home_url;
				}
				if (empty($redirect_page)) {
					$redirect_page = '/';		// Failsafe
				}
				Response::redirect($redirect_page);
			}
		}
		else {
			if ($is_ajaxy_login) {
				Response::http_status(406);
				
			} else {
				Console::log("Login is not ajaxy, setting response message and rendering");
				if ($this->credentials_submitted()) {
					Session::flash("user_error", "Invalid username or password");
				}
				if (isset($this->params['login_redirect'])) {
					Session::flash_unset('login_redirect');
	    			Session::flash('login_redirect',$this->params['login_redirect']);
				}
				$this->render();
			}
		}
		if ($is_ajaxy_login) {
			$this->Biscuit->render_json(array('message' => "Invalid username or password"));
		}
	}
	/**
	 * Log the user out
	 *
	 * @param string $user_msg Optional message to display after logout is complete
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_logout($user_msg = "You have been logged out") {
		if (!Session::already_flashed('user_message',$user_msg)) {
			Session::flash("user_message",$user_msg);
		}
		// Reset the session, keeping the flash variables in tact
		$this->keep_session_var("flash");
		
		// In case any modules need to do something before the user is logged out
		Event::fire("logout");

		Console::log("                        Clearing session...");
		Session::reset(true,$this->_logout_keepers);
		Console::log("                        Redirecting user...");

		if (!empty($this->params['ref_page'])) {
			$gopage = $this->params['ref_page'];
		}
		else {
			$gopage = str_replace('logout','',Request::uri());
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
				$this->Biscuit->set_user_input();
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
	 * Respond to a password reset request. This method is for use when storing hashed passwords.
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function action_reset_password() {
		if (!isset($this->params['step']) || $this->params['step'] == "") {
			// Default to the first step of the password reset form, which is just to display the page:
			$this->params['step'] = 1;
		}
		if (Request::is_post()) {
			if (!$this->process_reset()) {
				Session::flash('user_error',"<strong>Please make the following corrections:</strong><br><br>".implode("<br>",$this->_validation_errors));
			}
		}
		switch ($this->params['step']) {
			case 1:
				$this->title('Reset Password - Step 1');
				if (empty($this->params['username'])) {
					$post_username = '';
				} else {
					$post_username = $this->params['username'];
				}
				$this->set_view_var('username',$post_username);
				$this->set_view_var('step',1);
				break;
			case 2:
				$this->title('Reset Password - Step 2');
				$this->set_view_var('username',$this->tmp_user->username());
				$access_code = (!empty($this->params['access_code'])) ? $this->params['access_code'] : '';
				$this->set_view_var('access_code',$access_code);
				$this->set_view_var('security_question',$this->tmp_user->security_question());
				$security_answer = (!empty($this->params['security_answer'])) ? $this->params['security_answer'] : '';
				$this->set_view_var('security_answer',$security_answer);
				$new_password1 = (!empty($this->params['new_password1'])) ? $this->params['new_password1'] : '';
				$new_password2 = (!empty($this->params['new_password2'])) ? $this->params['new_password2'] : '';
				$this->set_view_var('new_password1',$new_password1);
				$this->set_view_var('new_password2',$new_password2);
				$this->set_view_var('step',2);
				break;
		}
		$this->render();
	}
	/**
	 * Validate and process user input from a password reset request
	 *
	 * @return void
	 * @author Peter Epp
	 */
	private function process_reset() {
		$this->_validation_errors = array();
		if ($this->validate_reset_password()) {
			switch ($this->params['step']) {
				case 1:
					$access_code = Crumbs::random_password(13); // Generate a random temporary password
					$mail = new Mailer();
					$options['Subject'] = "Password Reset Access Code";
					$options['To'] = $this->tmp_user->email_address();
					$viewvars = array(
						'contact_name' => $this->tmp_user->full_name(),
						'access_code' => $access_code
					);
					$result = $mail->send_mail('authenticator/views/pwd_reset_code',$options,$viewvars);
					if ($result == "+OK") {
						Session::set('pwd_reset_access_code',$access_code);
						$this->params['step'] = 2;
					}
					else {
						$this->_validation_errors[] = $result;
					}
					break;
				case 2:
					$this->tmp_user->set_password($this->hash_password($this->params['new_password1']));
					$this->tmp_user->save();
					Session::unset_var('pwd_reset_access_code');
					Session::flash('user_message','Your password has been successfully reset.');
					Response::redirect('/login');
					break;
			}
			return (empty($this->_validation_errors));
		}
		return false;
	}
	protected function validate_edit() {
		$is_valid = parent::validate_edit();
		if ($this->action() == 'new' && !$this->user_is_logged_in() && !Captcha::matches($this->params['security_code'])) {
			$this->_validation_errors[] = "Enter the security code shown in the image";
			$this->_invalid_fields[] = 'security_code';
			$is_valid = false;
		}
		return $is_valid;
	}
	/**
	 * Validate user input for a password reset request
	 *
	 * @return void
	 * @author Peter Epp
	 */
	protected function validate_reset_password() {
		switch ($this->params['step']) {
			case 1:
				if (!empty($this->params['username'])) {
					$this->tmp_user = $this->User->find_by('username',$this->params['username']);
					if (!$this->tmp_user || $this->tmp_user->status() == ACCOUNT_SUSPENDED) {
						$this->_validation_errors[] = "No active user account found";
					} else {
						Console::log_var_dump("User attributes",$this->tmp_user->get_attributes());
						if (!Crumbs::valid_email($this->tmp_user->email_address())) {
							$this->_validation_errors[] = 'There is no email address associated with your account. Please contact a system administrator to update your account information.';
						}
					}
					if (empty($this->_validation_errors)) {
						if (!$this->tmp_user->security_question() || !$this->tmp_user->security_answer()) {
							$this->_validation_errors[] = "There is no security question and/or answer associated with your account. Please contact a system administrator to update your account information.";
						}
					}
				} else {
					$this->_validation_errors[] = "Please provide your username";
				}
				if (!empty($this->_validation_errors)) {
					$this->_invalid_fields[] = 'username';
				}
				break;
			case 2:
				$this->tmp_user = $this->User->find_by('username',$this->params['username']);
				if (empty($this->params['security_answer']) || $this->params['security_answer'] != $this->tmp_user->security_answer()) {
					$this->_validation_errors[] = "Please answer your security question";
					$this->_invalid_fields[] = 'security_answer';
				}
				$secret_code = Session::get('captcha');
				if (!Captcha::matches($this->params['security_code'])) {
					$this->_validation_errors[] = "Please enter the security code shown in the image";
					$this->_invalid_fields[] = 'captcha';
				}
				$access_code = Session::get('pwd_reset_access_code');
				if ($this->params['access_code'] != $access_code) {
					$this->_validation_errors[] = "Please enter the access code that was sent to you by email.";
					$this->_invalid_fields[] = 'tmp_code';
				}
				if (strlen($this->params['new_password1']) < 7) {
					$this->_validation_errors[] = "Please enter a password at least 7 characters long";
					$this->_invalid_fields[] = 'new_password1';
				}
				if ($this->params['new_password2'] != $this->params['new_password1']) {
					$this->_validation_errors[] = "Your confirmation password does not match";
					$this->_invalid_fields[] = 'new_password1';
					$this->_invalid_fields[] = 'new_password2';
				}
				if (empty($this->_validation_errors)) {
					$this->params['security_question'] = $this->tmp_user->security_question();
					$this->params['step'] = 2;
					$this->params['contact_name'] = $this->tmp_user->full_name();
				}
				break;
		}
		return (empty($this->_validation_errors));
	}
	/**
	 * Validate the credentials submitted by the user
	 *
	 * @return void bool Whether or not credentials are valid
	 * @author Peter Epp
	 */
	private function valid_credentials() {
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
		if (!$user || !$this->_passwords_match(H::purify_text($login_info['password']), $user->password())) {
			Console::log("                        User not found or password mismatch");
			return false;
		}
		$this->user = $user;
		return true;
	}
	/**
	 * Check if login information was submitted
	 *
	 * @return bool Whether or not login info was submitted
	 * @author Peter Epp
	 */
	private function credentials_submitted() {
		return !empty($this->params['login_info']);
	}
	/**
	 * Check if one of the submitted login info fields is empty
	 *
	 * @return bool Whether or not a login field was empty
	 * @author Peter Epp
	 */
	private function credentials_submitted_empty() {
		return (empty($this->params['login_info']['username']) || empty($this->params['login_info']['password']));
	}
	/**
	 * Set $this->login_level
	 *
	 * @return void
	 **/
	private function set_login_level() {
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
		if (Session::var_exists('auth_data')) {
			$auth_data = Session::get('auth_data');
		}
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
	 * Fetch the URL to redirect the user to after they logout
	 *
	 * @return string URL relative to the site root
	 * @author Peter Epp
	 */
	public function login_url() {
		if ($this->user_is_logged_in()) {
			$auth_data = Session::get('auth_data');
			$access_level = $auth_data['user_level'];
		} else {
			$access_level = PUBLIC_USER;
		}
		// Grab the login page for this user's level:
		return DB::fetch_one("SELECT `login_url` FROM `access_levels` WHERE `id` = ?", $access_level);
	}
	/**
	 * Return the home url for the current user based on their user level.  This method does not check if a user is logged in, so you must check that before calling this method
	 *
	 * @return string
	 * @author Peter Epp
	 */
	public function user_home_url() {
		return DB::fetch_one("SELECT `home_url` FROM `access_levels` WHERE `id` = ?", $this->active_user()->user_level());
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
		$access_levels = $this->access_levels();
		Console::log("        Authenticator: Defining system access levels:");
		foreach ($access_levels as $access_level) {
			define($access_level->var_name(),$access_level->id());
			Console::log("            ".$access_level->var_name()." = ".$access_level->id());
		}
	}
	public function access_levels() {
		if (empty($this->_access_levels)) {
			$access_level_factory = new ModelFactory('AccessLevels');
			$this->_access_levels = $access_level_factory->find_all(array('id' => 'ASC'));
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
	private function _get_user_data($login_info) {
		return $this->User->find_by('username',H::purify_text($login_info['username']));
	}
	/**
	* Set $this->access info
	* 
	* The access_name and login_url for a specific login level
	*
	* @return void
	**/
	private function _set_access_info() {
		// Collect the access level data for the current login level:
		$this->access_info = $this->AccessLevels->find($this->login_level);
	}
	/**
	* Do the given passwords match?
	*
	* @return boolean
	* @author Lee O'Mara
	**/
	private function _passwords_match($user_input, $stored_pass) {
		return ($this->hash_password($user_input) == $stored_pass);
	}
	/**
	 * Whether or not to use a hash function when matching password.
	 *
	 * @return mixed False if hash should not be used, otherwise name of hash function to use if defined
	 * @author Peter Epp
	 */
	private function use_hash() {
		if (defined("USE_PWD_HASH")) {
			$use_hash = USE_PWD_HASH;
		}
		else {
			$use_hash = "no";
		}
		if ($use_hash != "no" && function_exists($use_hash)) {
			return $use_hash;
		}
		return false;
	}
	/**
	 * Hash the provided password for storing in the database (if required) using the hash method defined in the system settings
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function hash_password($password) {
		$use_hash = $this->use_hash();
		if (!$use_hash) {
			return $password;
		}
		return $use_hash($password);
	}
	/**
	 * Return the URL for the homepage of this level user
	 *
	 * @return string
	 * @author Lee O'Mara
	 **/
	private function _get_access_level_home($user_level) {
		return DB::fetch_one("SELECT `home_url` FROM `access_levels` WHERE `id` = ?", $user_level);
	}
	/**
	* Set values in the session after a successful login
	*
	* @return void
	* @author Lee O'Mara
	**/
	private function _set_logged_in_session_data() {
		$auth_data['id']         = (int)($this->active_user()->id());
		$auth_data['username']   = $this->active_user()->username();
		$auth_data['user_level'] = $this->active_user()->user_level();
		Session::set('auth_data',$auth_data);
	}
	/**
	 * Return the session timeout in seconds for the currently logged in user's level
	 *
	 * @return int Number of seconds
	 * @author Peter Epp
	 */
	private function session_timeout() {
		$auth_data = Session::get('auth_data');
		$session_timeout = DB::fetch_one("SELECT `session_timeout` FROM `access_levels` WHERE `id` = ?", $auth_data['user_level']);
		return intval($session_timeout,10) * 60;
	}
	/**
	 * Return the unix timestamp of the session expiry time
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function session_expires_at() {
		return time() + $this->session_timeout();
	}
	/**
	 * Prevent caching if a user is logged in
	 *
	 * @return bool
	 * @author Peter Epp
	 */
	protected function can_cache_action() {
		return (!$this->user_is_logged_in() && parent::can_cache_action());
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


	public function render_password_strength_meter($attribute_id) {
		return Crumbs::capture_include('modules/authenticator/views/pwd-strength-meter.php',array('attribute_id' => $attribute_id));
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
	public function url($action=null,$id=null) {
		$this->_url_action = $action;
		return parent::url($action,$id);
	}
	/**
	 * Provide rewrite rules for any login pages other than "login"
	 *
	 * @return array
	 * @author Peter Epp
	 */
	public static function rewrite_rules() {
		$access_level_factory = new ModelFactory('AccessLevels');
		$access_levels = $access_level_factory->find_all();
		$rewrite_rules[] = array(
			'pattern'     => '/^([^\.]+)\/logout\/?$/',
			'replacement' => 'page_slug=logout&action=logout&ref_page=/$1'
		);
		$rewrite_rules[] = array(
			'pattern'     => '/^logout\/?$/',
			'replacement' => 'page_slug=logout&action=logout'
		);
		foreach ($access_levels as $access_level) {
			if ($access_level->login_url() !== null) {
				$slug = substr($access_level->login_url(),1);
				$slug = preg_replace('/\//','\/',$slug);
				$rewrite_rules[] = array(
					'pattern'     => '/^'.$slug.'$/',
					'replacement' => 'page_slug='.$slug.'&action=login'
				);
			}
		}
		return $rewrite_rules;
	}
	/**
	 * Custom method for adding extra breadcrumbs that only adds crumb for the current action if it's not "login"
	 *
	 * @param Navigation $Navigation 
	 * @return void
	 * @author Peter Epp
	 */
	protected function act_on_build_breadcrumbs($Navigation) {
		if ($this->action() != 'login') {
			parent::act_on_build_breadcrumbs($Navigation);
		}
	}
}
?>