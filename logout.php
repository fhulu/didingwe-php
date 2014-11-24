<?php 
	session_start(); 
	require_once('../common/session.php');
	session::logout();
?>
<script>
window.location.href = '/?c=home';
</script>
