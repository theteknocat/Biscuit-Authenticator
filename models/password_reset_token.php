<?php
/**
 * Model the password_reset_tokens table
 *
 * @package Modules
 * @author Peter Epp
 */
class PasswordResetToken extends AbstractModel {
	protected function created_field_default() {
		return time();
	}
}
