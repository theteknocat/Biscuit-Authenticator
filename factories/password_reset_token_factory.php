<?php
/**
 * Custom factory for the password reset tokens table
 *
 * @package Modules
 * @subpackage Authenticator
 * @author Peter Epp
 * @version $Id: password_reset_token_factory.php 13843 2011-07-27 19:45:49Z teknocat $
 */
class PasswordResetTokenFactory extends ModelFactory {
	/**
	 * Delete rows that are more than 12 hours old
	 *
	 * @return void
	 * @author Peter Epp
	 */
	public function trash_expired() {
		$time = time();
		return DB::query("DELETE FROM `password_reset_tokens` WHERE (({$time}-`created`)/60/60) > 2");
	}
	/**
	 * Find a valid token
	 *
	 * @param string $token 
	 * @return void
	 * @author Peter Epp
	 */
	public function find_valid_by_token($token) {
		$time = time();
		return $this->model_from_query("SELECT * FROM `password_reset_tokens` WHERE (({$time}-`created`)/60/60) <= 2 AND `token` = ?",$token);
	}
}
