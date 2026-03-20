<?php
require_once '../includes/config.php';
require_once '../includes/conexao.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

destroySession();
header('Location: ../login.php');
exit;
