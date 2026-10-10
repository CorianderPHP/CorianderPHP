<?php
use CorianderCore\Core\Bootstrap\SessionBootstrap;
use CorianderCore\Core\Http\Responses;
return static function ($request) {
    SessionBootstrap::start();
    $_SESSION['visits'] = ($_SESSION['visits'] ?? 0) + 1;
    session_write_close();
    SessionBootstrap::start();
    return Responses::json(['visits' => $_SESSION['visits']]);
};
