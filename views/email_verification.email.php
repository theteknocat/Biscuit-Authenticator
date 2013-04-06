Dear <?php echo $user_name ?>,

Thank you for registering for a new account with <?php echo html_entity_decode(SITE_TITLE) ?>. In order to complete your registration, please click the following link to confirm your email address:

<?php echo STANDARD_URL ?>/users/verify_email/<?php echo $user_hash ?>


Once your email address has been confirmed your account will be activated for you to login and start using the site.

Please do not reply to this email. If you have any questions or concerns, please visit the contact page on our site at <?php echo STANDARD_URL ?>.

<?php echo html_entity_decode(SITE_TITLE) ?>
