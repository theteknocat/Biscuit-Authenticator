<?php
print $Navigation->render_admin_bar($Authenticator,$user,array(
	'bar_title' => __('User Admin'),
	'has_edit_button' => $Authenticator->user_can_edit()
));
?>
<p><?php echo __('This is the default, basic user profile view. Customize this if you want detailed user profiles on your site.'); ?></p>
<table width="100%">
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('Username:'); ?></th>
		<td><?php echo $user->username(); ?></td>
	</tr>
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('User Level:'); ?></th>
		<td><?php echo $user->access_level()->name(); ?></td>
	</tr>
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('Account Status:'); ?></th>
		<td><?php echo $user->account_status()->name(); ?></td>
	</tr>
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('First Name:'); ?></th>
		<td><?php echo $user->first_name(); ?></td>
	</tr>
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('Last Name:'); ?></th>
		<td><?php echo $user->last_name(); ?></td>
	</tr>
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('Email:'); ?></th>
		<td><?php echo $user->email_address(); ?></td>
	</tr>
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('Registered on:'); ?></th>
		<td><?php
		if (!$user->created_at() || $user->created_at() == '0000-00-00 00:00:00') {
			echo __('Unknown');
		} else {
			echo sprintf(__('%s at %s'), Crumbs::date_format($user->created_at(), '%B %e, %Y', true), Crumbs::date_format($user->created_at(), '%l:%M %p', true));
		}
		?></td>
	</tr>
	<tr>
		<th style="width: 150px; border: none;"><?php echo __('Last updated on:'); ?></th>
		<td><?php
		if (!$user->updated_at() || $user->updated_at() == '0000-00-00 00:00:00') {
			echo __('Unknown');
		} else {
			echo sprintf(__('%s at %s'), Crumbs::date_format($user->updated_at(), '%B %e, %Y', true), Crumbs::date_format($user->updated_at(), '%l:%M %p', true));
		}
		?></td>
	</tr>
</table>
