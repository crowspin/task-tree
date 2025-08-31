<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/GLOBALS.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Auth/Scrub/textbox.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Assert/strlen.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/SQL/Factory.php";

$ERROR_MESSAGE = "";
$LOCK_FIELDS = false;

if (!crow\Session\open()){
    $ERROR_MESSAGE = crow\ErrorMsg::$_[3];
    $LOCK_FIELDS = true;
}

if (!isset($_SESSION["login"]["failed_attempts"])){
    $_SESSION["login"]["failed_attempts"] = 0;
}

if ($_SESSION["login"]["failed_attempts"] >= 5){
    $ERROR_MESSAGE = crow\ErrorMsg::$_[4];
    $LOCK_FIELDS = true;
} else {
    if (isset($_POST["submit"])){
        if (
            isset($_POST["un"])
            && crow\Assert\strlen($_POST["un"], 1, 32)
            && isset($_POST["pw"])
            && crow\Assert\strlen($_POST["pw"], 8, 40)
        ){
            $username = crow\Auth\Scrub\textbox($_POST["un"]);
            $password = crow\Auth\Scrub\textbox($_POST["pw"]);

            $conn = crow\SQL\Factory::get();
            if (!$conn){
                $ERROR_MESSAGE = crow\ErrorMsg::$_[0];
            } else {
                $data = $conn->query("SELECT * FROM _users WHERE `username`='%0' LIMIT 1", [$username]);
                if ($data->success && count($data) == 1){
                    $_SESSION["login"]["username"] = $username;
                } else {
                    $_SESSION["login"]["failed_attempts"] += 1;
                    $ERROR_MESSAGE = crow\ErrorMsg::$_[2];
                }
            }
        } else {
            $_SESSION["login"]["failed_attempts"] += 1;
            $ERROR_MESSAGE = crow\ErrorMsg::$_[1];
        }
    }
}

if (!empty($_SESSION["login"]["username"])){
    crow\Header\redirect("\applet.php");
}

include __DIR__ . "/templates/login.php";

//! TODO: After re-implementation of crowSQL, look through xAuth and pbAuth for login attempt functions. Comment above would confirm login without even testing password lolol
/**
 * $ERROR_MESSAGE not printing when sql fails
 * sql fails
 * 
 * $ERROR_MESSAGE not displaying because server configuration does not support php short tags. Will fix later.
 * 
 */