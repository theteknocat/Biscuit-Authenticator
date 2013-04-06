<?php
if ($reset_email_sent) {
	include('authenticator/views/reset_password/step1_email_sent.php');
} else {
	include('authenticator/views/reset_password/step1_form.php');
}
