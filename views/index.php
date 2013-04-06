<?php
print $Navigation->render_admin_bar($Authenticator,null,array(
	'bar_title' => __('User Administration'),
	'has_new_button' => $Authenticator->user_can_create(),
	'new_button_label' => __('New User')
));
if (empty($users)) {
	?><p class="none-found"><?php echo __('There are currently no users in the system!') ?></p><?php
} else {
	?>
<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<th><?php echo __('Name') ?></th>
		<th><?php echo __('Access Level') ?></th>
		<th><?php echo __('Status') ?></th>
		<th width="20%" style="text-align: right"><?php echo __('Admin') ?></th>
	</tr>
	<?php
	foreach ($users as $user) {
		?>
	<tr class="<?php echo $Navigation->tiger_stripe('admin-user-list'); ?>">
		<td><?php echo $user->full_name(); ?></td>
		<td><?php echo $access_levels[$user->user_level()]->name() ?></td>
		<td><?php echo $account_statuses[$user->status()]->name() ?></td>
		<td><?php
		if ($Authenticator->user_can_edit($user) || $Authenticator->user_can_delete($user)) {
			?><div class="controls"><?php
			if ($Authenticator->user_can_edit($user)) {
				?><a href="<?php echo $Authenticator->url('edit', $user->id()); ?>" class="edit_item"><?php echo __('Edit') ?></a><?php
			}
			if ($Authenticator->user_can_delete($user)) {
				?><a href="<?php echo $Authenticator->url('delete', $user->id()); ?>" rel="<?php echo __('User') ?>|<?php echo addslashes($user->full_name()) ?>" class="delete-button"><?php echo __('Delete') ?></a><?php
			}
			?></div><?php
		} else {
			echo '&nbsp';
		}
		?></td>
	</tr>
		<?php
	}
	?>
</table>
	<?php
}
?>