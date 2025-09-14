<?php

require_once __DIR__ . "/lib/crowlib-php/Header/redirect.php";
require_once __DIR__ . "/lib/crowlib-php/Session/open.php";
require_once __DIR__ . "/lib/crowlib-php/IO/SQLFactory.php";

$redirect_address = "/index.php";
if (isset($_GET["returnTo"]) && is_numeric($_GET["returnTo"])) $redirect_address .= "?pg=" . $_GET["returnTo"];

if (!isset($_GET["id"]) || !is_numeric($_GET["id"]) || empty($_GET["action"]) || !array_search($_GET["action"], ["addChild", "edit", "delete", "toggleComplete", "shiftUp", "shiftDown"])) \crow\Header\redirect($redirect_address);

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"]) || empty($_SESSION["login"]["id"])) crow\Header\redirect("/login.php");

$sql = crow\IO\SQLFactory::get();
if (!$sql){
    //no sql connection
}

function shift(bool $up, callable $conditional){
    $step = ($up)? -1 : 1;

    if (!isset($_GET["parent"]) || !is_numeric($_GET["parent"])) \crow\Header\redirect($GLOBALS["redirect_address"]);

    $result = $GLOBALS["sql"]->query("SELECT * FROM relations_%0 WHERE parent=%1" , [$_SESSION["login"]["id"], $_GET["parent"], $_GET["id"]]);
    if(!$result->success){
        \crow\Header\redirect($GLOBALS["redirect_address"]);
    }

    $result->map_to_column("child");
        $old_idx = intval($result[$_GET["id"]]["idx"]);
        $new_idx = $old_idx + $step;

    if ($conditional($old_idx, $result)) \crow\Header\redirect($GLOBALS["redirect_address"]);
    
    $result->map_to_column("idx");
        $other_child_id = $result[$new_idx]["child"];

    $GLOBALS["sql"]->autocommit(false);
        $GLOBALS["sql"]->begin_transaction();
            //shouldn't be too hard to add a loop here later, when we do drag-to-reorder in the PWA
            $GLOBALS["sql"]->query("UPDATE relations_%0 SET idx = %3 WHERE parent=%1 AND child=%2;", [$_SESSION["login"]["id"], $_GET["parent"], $other_child_id, $old_idx]);
            $GLOBALS["sql"]->query("UPDATE relations_%0 SET idx = %3 WHERE parent=%1 AND child=%2;", [$_SESSION["login"]["id"], $_GET["parent"], $_GET["id"], $new_idx]);
        $GLOBALS["sql"]->commit();
    $GLOBALS["sql"]->autocommit(true);

    \crow\Header\redirect($GLOBALS["redirect_address"]);
}

function add_child(){
/**
 * Future INSERTs can be done by fetching lowest unused id, using following query:
 *      SELECT (Min(ID) + 1) AS NewIDToInsert FROM TableName T2A WHERE NOT EXISTS (SELECT ID FROM TableName T2B WHERE T2A.ID + 1 = T2B.ID)
 */
    //!

}

function edit(){
    //!
}

switch($_GET["action"]){
    case "toggleComplete":
        $sql->query("UPDATE `tasks_%0` SET `complete` = !`complete` WHERE `tasks_%0`.`id` = %1; ", [$_SESSION["login"]["id"], $_GET["id"]]);
        \crow\Header\redirect($redirect_address);
        break;
    case "addChild":
        if (isset($_POST["submit"])) add_child();
        else if (isset($_POST["back"])) \crow\Header\redirect($redirect_address);
        else include __DIR__ . "/templates/modify.edit.php";
        break;
    case "edit":
        if (isset($_POST["submit"])) edit();
        else if (isset($_POST["back"])) \crow\Header\redirect($redirect_address);
        else {
            $EDIT_VALS = $sql->query("SELECT is_group, text, complete FROM tasks_%0 WHERE id=%1", [$_SESSION["login"]["id"], $_GET["id"]])[0];
            include __DIR__ . "/templates/modify.edit.php";
        }
        break;
    case "delete":
        if (isset($_POST["delete"])){
            $sql->query("DELETE FROM tasks_%0 WHERE `tasks_%0`.`id` = %1", [$_SESSION["login"]["id"], $_GET["id"]]);
            \crow\Header\redirect($redirect_address);
        }
        else if (isset($_POST["back"])) \crow\Header\redirect($redirect_address);
        else include __DIR__ . "/templates/modify.delete.php";
        break;
    case "shiftUp":
        shift(true, function($old_idx, $result){
            return $old_idx == 0;
        });
        break;
    case "shiftDown":
        shift(false, function($old_idx, $result){
            return $old_idx + 1 == count($result);
        });
        break;
}