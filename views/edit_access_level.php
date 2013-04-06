<?php print Form::header($access_level); ?>

	<?php
	if ($access_level->is_new()) {
		print ModelForm::text($access_level, 'var_name', __('Used to reference this access level in PHP code. Must contain only letters and underscores and will be CASE SENSITIVE. Once set this value cannot be changed.'));
	}
	?>

	<?php print ModelForm::text($access_level, 'name'); ?>

	<?php print ModelForm::textarea($access_level, 'description'); ?>

	<?php print ModelForm::text($access_level, 'login_url', __('URL for the login page. Only change this if your site is not using the default.')); ?>

	<?php print ModelForm::text($access_level, 'home_url', __('Where to redirect the user after they login, unless otherwise overridden.')); ?>

	<?php print ModelForm::text($access_level, 'session_timeout', __('How many minutes before the user is automatically logged out. Defaults to zero, which is infinite.')); ?>

<?php print Form::footer($Authenticator, $access_level, (!$access_level->is_new() && $Authenticator->user_can_delete_access_level($access_level))); ?>
<script type="text/javascript">
	$(document).ready(function() {
		Biscuit.Crumbs.Forms.AddValidation('access-level-form');
	});
</script>
