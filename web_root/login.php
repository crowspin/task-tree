<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/GLOBALS.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Auth/Scrub/textbox.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Assert/strlen.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";

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
            && crow\Assert\strlen($_POST["un"], 2, 32)
            && isset($_POST["pw"])
            && crow\Assert\strlen($_POST["pw"], 8, 40)
        ){
            $username = crow\Auth\Scrub\textbox($_POST["un"]);
            $password = crow\Auth\Scrub\textbox($_POST["pw"]);

            $conn = crow\IO\SQLFactory::get();
            if (!$conn){
                $ERROR_MESSAGE = crow\ErrorMsg::$_[0];
                //maybe there's a way for us to create the whole database from outside? We'll look into it someday.
                //before that, we ought to add verification and repairs for potentially missing _users table
            } else {
                $data = $conn->query("SELECT * FROM _users WHERE `username`='%0' LIMIT 1", [$username]);
                if ($data->success && count($data) == 1){
                    $data->map_to_column("username");
                    
                    if (password_verify($password, $data[$username]["password"])){
                        if (password_needs_rehash($data[$username]["password"], PASSWORD_DEFAULT, crow\PASSWORD_OPTIONS)){
                            $newHash = password_hash($password, PASSWORD_DEFAULT, crow\PASSWORD_OPTIONS);
                            $q2 = $conn->query("UPDATE _users SET `password`='%0' WHERE `username`='%1'", [$newHash, $username]);
                            if (!$q2->success){
                                //log password hash update failure, continue anyway.
                            }
                        }
                        $_SESSION["login"]["username"] = $username;
                        $_SESSION["login"]["id"] = $data[$username]["id"];
                    } else {
                        $_SESSION["login"]["failed_attempts"] += 1;
                        $ERROR_MESSAGE = crow\ErrorMsg::$_[2];
                    }
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
    crow\Header\redirect("/index.php");
}
include __DIR__ . "/templates/login.php";

/**
 * Solved short tags issue by altering /etc/php/8.2/cli/php.ini and /etc/php/8.2/fpm/php.ini both to show short_tags_enabled = On
 * Working on SQL issue, set environment variables in /etc/environment now instead of /etc/profiles.d/whatever.sh and they're actually loaded according to printenv, but php still can't see them so probably another config change that needs to happen.
 * Edited both files again on ln.652 for variables_order = EGPCS from GPCS.
 * Attempt failed, tried setting clear_env = no for petc/sysconfig/phphp-fpm.conf as well, still no success.
 * Tried the same again but this time in /etc/php/8.2/fpm/pool.d/www.conf
 * Still no joy
 * Seems like at least with FPM I'll need to manually set the variables in the www.conf file mentioned before as well. rip.
 * Now the variables are declared, but not set. It's 100% an FPM issue, but boy is it getting me mad.
 * When I redeploy the webserver to my new machine, I'm dropping FPM. For now I'm going to just use the fallback option and work with the ini file.
 * An aside about that redeploy (I'll leave it here because I'm also leaving the config notes), Apparently it's not an uncommon practice to git diff the /etc directory for tracking configuration changes. Should do that next time around.
 * 
 * This would've been much wiser to arrange as a function that could return a tuple of (bool, int) for (success, errno). Then instead of stepping deeper and
 * deeper in scope I could've just returned on fail and in the calling scope I could've just used a switch case or passed that errno to the (painfully)
 * repeated $ERROR_MESSAGE calls above. All that said though, I am *trying* to speed along and actually make the TaskTree concept so I can get back to lessons
 * and that's going to mean skipping some bits. Long story short, login works as expected, I'm moving on.
 */