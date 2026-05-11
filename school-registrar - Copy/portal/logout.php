<?php
session_name('parent_session');
session_start();
session_destroy();
header('Location: login.php');
exit();
