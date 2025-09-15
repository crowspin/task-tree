<?php

require_once __DIR__ . "/lib/crowlib-php/Header/redirect.php";
require_once __DIR__ . "/lib/crowlib-php/Session/open.php";
require_once __DIR__ . "/lib/crowlib-php/IO/SQLFactory.php";

$redirect_address = "/index.php";
if (isset($_GET["returnTo"]) && is_numeric($_GET["returnTo"])) $redirect_address .= "?pg=" . $_GET["returnTo"];

if (!isset($_GET["id"]) || !is_numeric($_GET["id"]) || empty($_GET["action"]) || !array_search($_GET["action"], ["", "addChild", "edit", "delete", "toggleComplete", "shiftUp", "shiftDown"])) \crow\Header\redirect($redirect_address);

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
    if (empty($_POST["txt"])) \crow\Header\redirect($GLOBALS["redirect_address"]);

    $id = $GLOBALS["sql"]->query("SELECT (Min(id) + 1) AS NewIDToInsert FROM tasks_%0 T2A WHERE NOT EXISTS (SELECT id FROM tasks_%0 T2B WHERE T2A.id + 1 = T2B.id);", [$_SESSION["login"]["id"]])[0]["NewIDToInsert"];
    $idx = $GLOBALS["sql"]->query("WITH cte AS (SELECT * FROM relations_%0 WHERE parent=%1) SELECT (Min(idx) + 1) AS NewIDToInsert FROM cte T2A WHERE NOT EXISTS (SELECT idx FROM cte T2B WHERE T2A.idx + 1 = T2B.idx);", [$_SESSION["login"]["id"], $_GET["id"]])[0]["NewIDToInsert"] ?: 0;
    $safe_txt = $_POST["txt"];//scrub
    $is_group = (!empty($_POST["is_group"]))?b'1':b'0';
    $complete = (!empty($_POST["complete"]))?b'1':b'0';

    $GLOBALS["sql"]->query("INSERT INTO tasks_%0 (id, text, is_group, complete) VALUES (%1, '%2', %3, %4);", [$_SESSION["login"]["id"], $id, $safe_txt, $is_group, $complete]);
    $GLOBALS["sql"]->query("INSERT INTO relations_%0 (parent, child, idx) VALUES (%1, %2, %3);", [$_SESSION["login"]["id"], $_GET["id"], $id, $idx]);

    \crow\Header\redirect($GLOBALS["redirect_address"]);
}

function edit(){
    if (empty($_POST["txt"])) \crow\Header\redirect($GLOBALS["redirect_address"]);

    $id = $_GET["id"];
    $safe_txt = $_POST["txt"];//scrub
    $is_group = (!empty($_POST["is_group"]))?b'1':b'0';
    $complete = (!empty($_POST["complete"]))?b'1':b'0';

    $GLOBALS["sql"]->query("UPDATE tasks_%0 SET text='%2', is_group=%3, complete=%4 WHERE id=%1;", [$_SESSION["login"]["id"], $id, $safe_txt, $is_group, $complete]);

    \crow\Header\redirect($GLOBALS["redirect_address"]);
}

switch($_GET["action"]){
    case "toggleComplete":
        $sql->query("UPDATE `tasks_%0` SET `complete` = !`complete` WHERE `tasks_%0`.`id` = %1; ", [$_SESSION["login"]["id"], $_GET["id"]]);
        \crow\Header\redirect($redirect_address);
        break;
    case "addChild":
        if (isset($_POST["submit"])) add_child();
        else if (isset($_POST["back"])) \crow\Header\redirect($redirect_address);
        else {
            $EDIT_VALS = ["text" => "", "is_group" => false];
            include __DIR__ . "/templates/modify.edit.php";
        }
        break;
    case "edit":
        if (isset($_POST["submit"])) edit();
        else if (isset($_POST["del"])) \crow\Header\redirect("/modify.php?id=" . $_GET["id"] . "&action=delete&returnTo=" . $_GET["returnTo"]);
        else if (isset($_POST["back"])) \crow\Header\redirect($redirect_address);
        else {
            $EDIT_VALS = $sql->query("SELECT is_group, text, complete FROM tasks_%0 WHERE id=%1", [$_SESSION["login"]["id"], $_GET["id"]])[0];
            include __DIR__ . "/templates/modify.edit.php";
        }
        break;
    case "delete":
        if (isset($_POST["delete"])){
            $sql->query("DELETE FROM tasks_%0 WHERE `tasks_%0`.`id` = %1", [$_SESSION["login"]["id"], $_GET["id"]]);
            $sql->query("DELETE FROM relations_%0 WHERE parent=%1 OR child=%1", [$_SESSION["login"]["id"], $_GET["id"]]);
            \crow\Header\redirect($redirect_address);
        }
        else if (isset($_POST["back"])) \crow\Header\redirect($redirect_address);
        else {
            $TEXT = $sql->query("SELECT text FROM tasks_%0 WHERE id=%1", [$_SESSION["login"]["id"], $_GET["id"]])[0]["text"];
            include __DIR__ . "/templates/modify.delete.php";
        }
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
/*
Working on SQL Queries for delete:

INSERT IGNORE INTO `tasks_1` (`id`, `complete`, `is_group`, `text`) VALUES
(0, b'0', b'1', 'Editing Lists...'),
(1, b'0', b'0', 'Tasks'),
(2, b'0', b'0', 'Shopping'),
(3, b'0', b'1', 'Christmas 2025'),
(4, b'0', b'1', 'Responsibilities'),
(5, b'0', b'1', 'Housework'),
(6, b'0', b'0', 'Evening Chores'),
(7, b'0', b'0', 'Make Dinner'),
(8, b'0', b'0', 'Drink your coffee'),
(9, b'0', b'0', 'test'),
(10, b'0', b'0', 'test2'),
(11, b'0', b'0', 'testun'),
(12, b'0', b'0', 'adgaef'),
(13, b'0', b'0', 'feaasef'),
(14, b'0', b'0', 'another brankc');
INSERT IGNORE INTO `relations_1` (`parent`, `child`, `idx`) VALUES
(0, 1, 0),
(0, 3, 2),
(0, 4, 1),
(0, 5, 3),
(3, 2, 0),
(3, 13, 0),
(4, 5, 0),
(4, 9, 1),
(4, 10, 2),
(5, 6, 0),
(6, 7, 0),
(7, 8, 0),
(9, 13, 0),
(10, 11, 0),
(10, 12, 1),
(11, 14, 0),
(12, 14, 0);

create or replace temporary table to_delete (id smallint unsigned)
WITH RECURSIVE cte AS (
	SELECT	a.id
	FROM	tasks_1 AS a
    JOIN    relations_1 AS b
	ON      a.id = b.child AND b.parent = 4
	UNION ALL 
    SELECT	cld.id
	FROM	cte prt
    JOIN    relations_1 rel ON prt.id = rel.parent
	JOIN    tasks_1 cld ON cld.id = rel.child
) select 4 as id union all select * from cte;

# select * from to_delete;

create or replace temporary table exceptions (id smallint unsigned)
with recursive cte as (
    select a.id from to_delete as a
    join relations_1 as b
    on a.id != 4 and a.id = b.child and b.parent not in (select id from to_delete union all select 4)
    union all
    select b.child
    from cte
    join relations_1 as b
    on cte.id = b.parent
) select * from cte;

# select * from exceptions;

delete from to_delete where to_delete.id in (select * from exceptions);

# select * from to_delete;

delete from tasks_1 where id in (select * from to_delete);
delete from relations_1 where parent in (select * from to_delete);

create or replace temporary table links like relations_1;
insert into links (select * from relations_1 where relations_1.child = 4);

update relations_1, links set relations_1.idx = relations_1.idx-1 where links.parent = relations_1.parent and relations_1.idx > links.idx;
delete from relations_1 where child = 4;



Idea about future many-to-many ui linkage thing
Use XHR flyout (
    list of tasks starting with children of root
    each task/group whatever has a checkbox
    if you click checkbox, that's the one you're linking to
    if you click text you navigate into that task/group to choose from it's children
    should have .. option to move up tree
    )
Tasks must not ever have cyclical parentage
