<?php
class user
{
  static function logout()
  {
    session::logout();
    page::redirect('/home');
  }
}
