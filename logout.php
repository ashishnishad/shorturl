<?php
require_once 'config.php';

unset($_SESSION['logged_in']);
unset($_SESSION['userinfo']);

header('Location: /url-short'); die;