<?php
require_once dirname(__DIR__).'/config/db.php';
if (isLoggedIn()) auditLog('logout','auth','user',(int)(currentUser()['id']??0));
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
}
session_destroy();
redirect(BASE_URL.'/login.php');
