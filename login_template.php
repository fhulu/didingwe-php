<?php session_start(); ?>
<html>
  <head>
    <title>OTS Login</title>
  </head>
  <body>

    <big>
      <h1>Login to OTS</h1>
    </big>
    <form action="do.php/session/login" method="POST">
      <table>
        <tr>
          <td>Email Address</td>
          <td>
            <input type="text" name="email" size="20">
          </td>
        </tr>
        <tr>
          <td>Password</td>
          <td>
            <input type="password" name="password" size="20">
          </td>
        </tr>
        <tr>
          <td></td>
          <td>
            <input type="submit" value="Send"/>
            <input type="reset" value="Reset"/>
          </tr>
      </table>
      <?php
	if (isset($_SESSION['login_error']))
	  echo "<h4><font color='red'> " .  $_SESSION['login_error'] . "</font></h4>";
      ?>
    </form>
  </body>
</html>

