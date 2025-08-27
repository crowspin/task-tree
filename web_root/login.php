<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/crowSession.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/f/scrub_login.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/f/assert_strlen.php";

$ERROR_MESSAGE = "";
$LOCK_FIELDS = false;

if (!crow\openSession()){
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
            && crow\assert_strlen($_POST["un"], 1, 32)
            && isset($_POST["pw"])
            && crow\assert_strlen($_POST["pw"], 8, 40)
        ){
            $username = crow\login\scrub_TBX($_POST["un"]);
            $password = crow\login\scrub_TBX($_POST["pw"]);

            $sql = crow\SQL;
            if (!$sql){
                $ERROR_MESSAGE = crow\ErrorMsg::$_[0];
            }

            $rv = $sql::query_clean("SELECT * FROM _users WHERE `username`='$username' LIMIT 1");
            if ($rv && $rv.count == 1){
                //$_SESSION["login"]["username"] = $username;
            } else {
                $_SESSION["login"]["failed_attempts"] += 1;
                $ERROR_MESSAGE = crow\ErrorMsg::$_[2];
            }
        } else {
            $_SESSION["login"]["failed_attempts"] += 1;
            $ERROR_MESSAGE = crow\ErrorMsg::$_[1];
        }
    }
}

if ($_SESSION["login"]["username"]){
    crow\redirect("\applet.php");
}

include "__login_template.php";

//! TODO: After re-implementation of crowSQL, look through xAuth and pbAuth for login attempt functions. Comment above would confirm login without even testing password lolol