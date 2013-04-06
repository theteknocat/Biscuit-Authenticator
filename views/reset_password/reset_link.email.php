Dear <?php echo $user_name ?>,

You are receiving this message because a password reset request was submitted. If you did not request this, just ignore this message. If you did in fact request that your password be reset, click the following link:

<?php echo $reset_url; ?>


You can use this link to reset your password within the next 2 hours. After that the link will expire and you will have to start the process over again.

Please do not reply to this email. If you have any questions or concerns, please visit the contact page on our site at <?php echo STANDARD_URL ?>.

<?php echo html_entity_decode(SITE_TITLE) ?>
