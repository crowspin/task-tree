<?php

require_once __DIR__ . "/lib/crowlib-php/GLOBALS.php";
require_once __DIR__ . "/lib/crowlib-php/Header/redirect.php";
require_once __DIR__ . "/lib/crowlib-php/Assert/strlen.php";
require_once __DIR__ . "/lib/crowlib-php/IO/SQLFactory.php";
require_once __DIR__ . "/lib/crowlib-php/Auth/Scrub/textbox.php";
require_once __DIR__ . "/lib/crowlib-php/Session/open.php";

$ERROR_MESSAGE = "";

function register(){
    if (empty($_POST["un"]) || empty($_POST["pw"]) || empty($_POST["pwc"]) || $_POST["pw"] != $_POST["pwc"] || !\crow\Assert\strlen($_POST["un"], 2, 32) || !\crow\Assert\strlen($_POST["pw"], 8, 40)){
        $GLOBALS["ERROR_MESSAGE"] = "An error related to your inputs occurred, please try again.";
        return;
    }
    
    $USERNAME = \crow\Auth\Scrub\textbox($_POST["un"]);
    $PASSWORD = \crow\Auth\Scrub\textbox($_POST["pw"]);

    $sql = \crow\IO\SQLFactory::get();
    if (!$sql){
        $GLOBALS["ERROR_MESSAGE"] = crow\ErrorMsg::$_[0];
        return;
    }

    $test_username = $sql->query("SELECT * FROM _users WHERE `username` = '%0'", [$USERNAME]);
    if (!$test_username->success){
        $GLOBALS["ERROR_MESSAGE"] = "A database error occurred.";
        return;
    }
    if (count($test_username) != 0){
        $GLOBALS["ERROR_MESSAGE"] = "That username is already in use.";
        return;
    }
    
    $sql->autocommit(false);
    $sql->begin_transaction();
    $sql->query("INSERT INTO _users (username, password) VALUES ('%0', '%1')", [$USERNAME, password_hash($PASSWORD, PASSWORD_DEFAULT, \crow\PASSWORD_OPTIONS)]);
    $ID = $sql->query("SELECT * FROM _users WHERE `username` = '%0'", [$USERNAME])[0]["id"];
    //$sql->query("DROP TABLE IF EXISTS `tasks_%0`", [$ID]);
    $sql->query("CREATE TABLE `tasks_%0` (
                            `id` smallint(5) UNSIGNED NOT NULL,
                            `complete` bit(1) NOT NULL DEFAULT b'0',
                            `is_group` bit(1) NOT NULL DEFAULT b'0',
                            `text` varchar(512) NOT NULL
                        )       ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;", [$ID]);
    $sql->query("INSERT INTO `tasks_%0` (id, complete, is_group, text) VALUES (0, b'0', b'1', 'Editing Lists...'), (1, b'0', b'0', 'Tasks');", [$ID]);
    $sql->query("ALTER TABLE `tasks_%0` ADD PRIMARY KEY (`id`);", [$ID]);
    //$sql->query("DROP TABLE IF EXISTS `relations_%0`", [$ID]);
    $sql->query("CREATE TABLE `relations_%0` (
                            `parent` smallint(5) UNSIGNED NOT NULL,
                            `child` smallint(5) UNSIGNED NOT NULL,
                            `idx` smallint(5) UNSIGNED NOT NULL
                        )       ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;", [$ID]);
    $sql->query("INSERT INTO `relations_%0` (`parent`, `child`, `idx`) VALUES (0, 1, 0);", [$ID]);
    $sql->query("ALTER TABLE `relations_%0` ADD PRIMARY KEY (`parent`,`child`) USING BTREE, ADD KEY `child` (`child`,`parent`) USING BTREE;", [$ID]);
    if (!$sql->commit()){
        $GLOBALS["ERROR_MESSAGE"] = "A database error occurred.";
        return;
    }
    $sql->autocommit(true);

    \crow\Session\open();
    $_SESSION["login"]["id"] = $ID;
    $_SESSION["login"]["username"] = $USERNAME;
    \crow\Header\redirect("/index.php");
}

if (isset($_POST["back"])) \crow\Header\redirect("/index.php");
if (isset($_POST["submit"])) register();

if (empty($ERROR_MESSAGE)) $ERROR_MESSAGE = "Please enter a username <small>(between 2 and 32 characters in length)</small> and a password <small>(between 8 and 40 characters in length)</small>";
include __DIR__ . "/templates/register.php";