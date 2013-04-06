<?php
/**
 * Custom factory for the password reset tokens table
 *
 * @package default
 * @author Peter Epp
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
