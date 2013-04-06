<?php
$custom_buttons = array();
if ($Authenticator->user_can_index_access_level()) {
	$custom_buttons = array(
		array('href' => $Authenticator->url('index_access_level'), 'label' => __('Manage Access Levels'), 'classname' => 'access-button')
	);
}
print $Navigation->render_admin_bar($Authenticator,null,array(
	'bar_title' => __('User Administration'),
	'has_new_button' => $Authenticator->user_can_create(),
	'new_button_label' => __('New User'),
	'custom_buttons' => $custom_buttons
));
if (empty($users)) {
	?><p class="none-found"><?php echo __('There are currently no users in the system!') ?></p><?php
} else {
	if ($paginator->GetPageCount() > 1) {
		ob_start();
		?>
	<div class="paging"><div class="paging-title">Users <?php echo $paginator->GetPageItemNumbers($result_count) ?></div><div class="page-links-right"><?php echo $paginator->GetPageLinks(); ?></div></div>
		<?php
		$pagination_links = ob_get_flush();
	}
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
		<td<?php if ($Authenticator->active_user()->id() == $user->id()) { ?> style="font-weight: bold;"<?php } ?>><?php
		if ($Authenticator->user_can_show()) {
		    ?><a href="<?php echo $Authenticator->url('show',$user->id()); ?>"><?php
		}
		echo $user->full_name();
		if ($Authenticator->user_can_show()) {
		    ?></a><?php
		}
		if ($Authenticator->active_user()->id() == $user->id()) {
			?> <span class="small">(<?php echo __('You'); ?>)</span><?php
		}
		?></td>
		<td><?php echo $access_levels[$user->user_level()]->name() ?></td>
		<td><?php echo $account_statuses[$user->status()]->name() ?></td>
		<td><?php
		if ($Authenticator->user_can_edit($user) || $Authenticator->user_can_delete($user)) {
			?><div class="controls"><?php
			if ($Authenticator->user_can_delete($user)) {
				?><a href="<?php echo $Authenticator->url('delete', $user->id()); ?>" data-item-type="<?php echo __('User'); ?>" data-item-title="<?php echo Crumbs::entitize_utf8($user->full_name()) ?>"<?php if ($Authenticator->active_user()->id() == $user->id()) { ?> data-additional-text="<?php echo __('WARNING: By deleting your own account you will no longer have access to the site.'); ?>"<?php } ?> class="delete-button"><?php echo __('Delete') ?></a><?php
			}
			if ($Authenticator->user_can_edit($user)) {
				?><a href="<?php echo $Authenticator->url('edit', $user->id()); ?>" class="edit-button"><?php echo __('Edit') ?></a><?php
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
	if (!empty($pagination_links)) {
		echo $pagination_links;
	}
}
?>
<script type="text/javascript">
	$(document).ready(function() {
		$('.access-button').button({
			icons: {
				primary: 'ui-icon-key'
			}
		});
	});
</script>