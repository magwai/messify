<?php

// Include class and create instance
include '../Messify.php';
$messify = new \Magwai\Messify\Messify;

// Add CSS and JavaScript files
$messify
	->add('js', 'http://code.jquery.com/jquery-latest.js')
	->add('js', '/demo/js/script.js')
	->add('js', '$(function() { $("#jquery_test_inline").html("Hello world inline"); });', array('inline' => true))
	->add('css', 'http://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.css')
	->add('css', '/demo/css/style.css')
	->add('css', 'body{color:#888888;}', array('inline' => true));

?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<?php echo $messify->render('css') ?>
	</head>
	<body>
		<div id="jquery_test_file"></div>
		<div id="jquery_test_inline"></div>
		<?php echo $messify->render('js') ?>
	</body>
</html>