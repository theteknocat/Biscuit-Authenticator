<?php
/**
 * Model the password_reset_tokens table
 *
 * @package Modules
 * @subpackage Authenticator
 * @author Peter Epp
 * @version $Id: password_reset_token.php 13843 2011-07-27 19:45:49Z teknocat $
 */
class PasswordResetToken extends AbstractModel {
	protected function created_field_default() {
		return time();
	}
}
