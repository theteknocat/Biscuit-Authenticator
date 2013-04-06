<?php
$custom_buttons = array();
if ($Authenticator->user_can_index()) {
	$custom_buttons = array(
		array('href' => $Authenticator->url(), 'label' => __('Manage Users'), 'classname' => 'person-button')
	);
}
print $Navigation->render_admin_bar($Authenticator,'access_level',array(
	'bar_title' => __('Access Level Administration'),
	'has_new_button' => $Authenticator->user_can_create_access_level(),
	'new_button_label' => __('New Access Level'),
	'custom_buttons' => $custom_buttons
));

if (empty($access_levels)) {
	?><p class="none-found"><?php echo __('No Access Levels'); // Un-possible! ?></p><?php
} else {
	?>
<table style="width: 100%;">
	<tr>
		<th style="width: 40px"><?php echo __('ID'); ?></th>
		<th style="width: 150px;"><?php echo __('Name'); ?></th>
		<th style="width: 150px;"><?php echo __('PHP Constant'); ?></th>
		<th><?php echo __('Description'); ?></th>
		<?php
		if ($Authenticator->user_can_edit_access_level() || $Authenticator->user_can_delete_access_level()) {
			?>
		<th style="width: 150px; text-align: right;"><?php echo __('Admin'); ?></th>
			<?php
		}
		?>
	</tr>
	<?php
	foreach ($access_levels as $access_level) {
		?>
	<tr class="<?php echo $Navigation->tiger_stripe('access-levels-list'); ?>">
		<td><?php echo $access_level->id(); ?></td>
		<td><?php echo $access_level->name(); ?></td>
		<td><?php echo $access_level->var_name(); ?></td>
		<td><?php
		$description = $access_level->description();
		if (empty($description)) {
			$description = __('None');
		}
		echo $description;
		?></td>
		<?php
		if ($Authenticator->user_can_edit_access_level() || $Authenticator->user_can_delete_access_level()) {
			?>
		<td><div class="controls"><?php
			if ($Authenticator->user_can_delete_access_level($access_level)) {
				?><a href="<?php echo $Authenticator->url('delete_access_level', $access_level->id()); ?>" data-item-type="<?php echo __('Access Level'); ?>" data-item-title="<?php echo Crumbs::entitize_utf8($access_level->name()); ?>" data-additional-text="<?php echo __('Any scripts using the PHP constant will break as a result.'); ?>" class="delete-button"><?php echo __('Delete'); ?></a><?php
			}
			if ($Authenticator->user_can_edit_access_level($access_level)) {
				?><a href="<?php echo $Authenticator->url('edit_access_level', $access_level->id()); ?>" class="edit-button"><?php echo __('Edit'); ?></a><?php
			}
			?></div>
		</td><?php
		}
		?>
	</tr>
		<?php
	}
	?>
</table>
	<?php
}
?>
<script type="text/javascript">
	$(document).ready(function() {
		$('.person-button').button({
			icons: {
				primary: 'ui-icon-person'
			}
		});
	});
</script>