<p class="success">Thank you, <?php echo $user->full_name(); ?>, an email has been sent to <?php echo $user->email_address(); ?> with a link you can use to reset your password.</p>
<p>The link in the email is valid for the next 2 hours. After that it will expire and you will need to start the process over again.</p>
<p><strong>Be sure to check your junk mail folder in case the message was caught by your spam filter.</strong> The message was sent from <?php echo Crumbs::site_from_address(); ?>, so you can add that to your authorized senders list to ensure that you receive it.</p>
