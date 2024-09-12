<?php
ob_start();
date_default_timezone_set("Europe/Paris");
session_start();

require('settings/settings.php');

// GET actions are handled in the controller folder
// e.g.:
// http://request.request/?action=login
// will redirect to the "login" file in the "controller" folder
if (isset($_GET['action'])) {
    if(file_exists('controller/'.$_GET['action'].'.php')) {
        require ('controller/'.$_GET['action'].'.php');
    } else {
        header('HTTP/1.0 403 Forbidden', true, 403);
        exit;
    }
} else {
    // If no action is requested, we redirect to index.php in the public folder
    include ('public/index.php');
}

ob_end_flush();
