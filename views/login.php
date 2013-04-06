<div id="login-form-container">
	<form action="" method="POST" accept-charset="UTF-8" id="login-form" class="login-form">
		<input type="hidden" name="action" value="login">
		<?php
			if (Session::flash_isset('login_redirect')) {
		?>
		<input type="hidden" name="login_redirect" value="<?php echo Session::flash_get('login_redirect')?>">
		<?php
			}
		?>
		<p>
			<?php echo Form::text("username","login_info[username]",__("Username"),'',true,true,array('maxlength' => '255')) ?>
		</p>
		<p>
			<?php echo Form::password("password","login_info[password]",__("Password"),'',true,true,array('maxlength' => '255')) ?>
		</p>
		<?php
		if (!$is_login_dialog_request) {
			?>
		<p>
			<?php echo Form::checkbox(1, 'keep_logged_in', 'keep_logged_in', __('Stay logged in on this computer'), 0, 0, false, true); ?>
			<span class="instructions"><?php echo __('Login session will last for up to 30 days with no activity.'); ?></span>
		</p>
		<p><label>&nbsp;</label><a href="/reset-password"><?php echo __('I forgot my password!') ?></a></p><?php
			if (!empty($extra_login_form_code)) {
				print $extra_login_form_code;
			}
		}
		?><div class="controls"<?php if ($is_login_dialog_request) { ?> style="position: absolute; left: -999999em; top: -999999em; width: 0; height: 0; margin: 0; padding: 0;"<?php } ?>><input type="submit" class="SubmitButton" value="<?php echo __('Login') ?> &rarr;"></div>
	</form>
</div>
<?php
if (!$is_login_dialog_request) {
?>
<script type="text/javascript">
	$(document).ready(function() {
		$('#attr_username').focus();
		Biscuit.Ajax.LoginHandler('login-form','login-form-container');
	});
</script>
<?php
}
?>