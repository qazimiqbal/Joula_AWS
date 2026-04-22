<?php
// The message
$message = "Line 1\r\nLine 2\r\nLine 3";

// In case any of our lines are larger than 70 characters, we should use wordwrap()
$message = wordwrap($message, 70, "\r\n");

// Send
echo $message;
$success = mail('qazi.iqbal@gmail.com', 'My Subject', $message);
if (!$success) {
    //$errorMessage = error_get_last()['message'];
	echo "Failed";
}
else{
	echo "Success";
}
?>