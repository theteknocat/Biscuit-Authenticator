<?php
print $Navigation->render_admin_bar($Authenticator,null,array(
	'bar_title' => 'User Administration',
	'has_new_button' => $Authenticator->user_can_create(),
	'new_button_label' => 'New User'
));
if (empty($users)) {
	?><p class="none-found">There are currently no users in the system! Wha? How can you be logged in seeing this page???</p><?php
} else {
	?>
<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<th>Name</th>
		<th>Access Level</th>
		<?php
		if ($Authenticator->user_can_edit() || $Authenticator->user_can_delete()) {
			?>
		<th width="20%" style="text-align: right">Admin</th>
		<?php
		}
		?>
	</tr>
	<?php
	foreach ($users as $user) {
		?>
	<tr>
		<td><?php echo $user->full_name(); ?></td>
		<td><?php echo $access_levels[$user->user_level()]->name() ?></td>
		<?php
		if ($Authenticator->user_can_edit($user) || $Authenticator->user_can_delete($user)) {
			?>
		<td><div class="controls"><?php
			if ($Authenticator->user_can_edit($user)) {
				?><a href="<?php echo $Authenticator->url('edit', $user->id()); ?>" class="edit_item">Edit</a><?php
			}
			if ($Authenticator->user_can_delete($user)) {
				?><a href="<?php echo $Authenticator->url('delete', $user->id()); ?>" rel="User|<?php echo addslashes($user->full_name()) ?>" class="delete-button">Delete</a><?php
			}
			?></div>
		</td><?php
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