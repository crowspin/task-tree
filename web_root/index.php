<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";
require_once __DIR__ . "/lib/TaskTreeNode.php";

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"])) crow\Header\redirect("/login.php");
// I *know* there's an Apache security thing I could be doing instead, but I've never taken the time to learn that, so for the sake of getting this done sooner-than-never...


$sql = crow\IO\SQLFactory::get();
if (!$sql){
    //no sql connection
}
/**
 * We should be checking that the tables exist here (SHOW TABLES;), and if they don't we need to make them.
 * Must remember to populate tasks_ with special "Editing Lists..." task at id=0, and default "Tasks" list at id=1
 * Use structure available through phpma portal.//!
 */ 
$q_rid = 0;
$qs = "WITH RECURSIVE cte AS (
	SELECT	a.*, b.parent, b.idx
	FROM	tasks_%0 AS a
    JOIN    relations_%0 AS b
	ON      a.id = b.child AND b.parent = %1
	UNION ALL 
    SELECT	cld.*, rel.parent, rel.idx
	FROM	cte prt
    JOIN    relations_%0 rel ON prt.id = rel.parent AND prt.is_group = 1
	JOIN    tasks_%0 cld ON cld.id = rel.child
)
SELECT *, 'root' AS parent, null AS idx FROM tasks_%0 WHERE id = %1 
UNION ALL SELECT DISTINCT * FROM cte r";

$query_root = $sql->query($qs, [$_SESSION["login"]["id"], $q_rid]);
if (!$query_root->success){
    //query failed
}

/**
 * First iteration of function to separate completed tasks away from incomplete revealed that the way we're holding tasks in an array and
 *     children are only references is actually also overwriting rows when we process them (There's only one $LISTS[6], but we /had/ two
 *     rows with slightly different data (just parent and idx columns)). This might be a moot issue, but it could be bigger depending on
 *     how work on modify.php proceeds.
 * Use of arrays $LISTS and $TASKS is already relegated to the area below. Changing how we work with the data to use a proper tree structure
 *      shouldn't be too troublesome. This is where we're coming back to later.
 * I've decided that changing this is unneccesary (and difficult). I couldn't reason a way around changing this that I thought wouldn't
 *      cascade into another rewrite. Fact is that the parent and idx columns are only used in the processing below, so there isn't
 *      any issue with rows overwriting themselves. The linkage isn't affected, so all is well.
 */

$LISTS = [];
foreach ($query_root as $row){
    $LISTS[$row["id"]] = new TaskTreeNode($row);
    if ($row["parent"] == "root") continue;
    $LISTS[$row["parent"]]->children[$row["idx"]] = &$LISTS[$row["id"]];
}

$first_list = $LISTS[$q_rid]->first();
if (!$first_list){
    //error, no lists?
}
$CURRENT_LIST = (isset($_GET["pg"]) && $_GET["pg"] != "") ? $_GET["pg"] : $first_list;

$query_tasks = $sql->query($qs/*Pagination/ . " LIMIT 50 OFFSET " . 0*/, [$_SESSION["login"]["id"], $CURRENT_LIST]);
if (!$query_tasks->success){
    //query failed
}

$TASKS = [];
foreach ($query_tasks as $row){
    $TASKS[$row["id"]] = new TaskTreeNode($row);
    if ($row["parent"] == "root") continue;
    $TASKS[$row["parent"]]->children[$row["idx"]] = &$TASKS[$row["id"]];
}

$callback = function($value){ return $value[0] == $GLOBALS['CURRENT_LIST']; };

//Apparently my server is running on PHP 8.21.1 right now, so I had to implement this myself.
function array_find_key($array, $callback){
    for ($i = 0; $i < count($array); $i++){
        if ($callback($array[$i])) return $i;
    }
    return null;
}

if ($LISTS["0"]->find($CURRENT_LIST)) $_SESSION["reverseNav"] = [];
else if ($pos = array_find_key($_SESSION["reverseNav"], $callback)){
    $_SESSION["reverseNav"] = array_slice($_SESSION["reverseNav"], 0, $pos);
}
$_SESSION["reverseNav"][] = [$CURRENT_LIST, $TASKS[$CURRENT_LIST]->row["text"]];

$HTML_Sidebar = $LISTS["0"]->generate_html_sidebar($CURRENT_LIST);
$HTML_Tasklist = $TASKS[$CURRENT_LIST]->generate_html_tasklist();

include __DIR__ . "/templates/index.php";

//var_dump($_SESSION["reverseNav"]);

/**
 * For Future Versions:
 * ----------------------------------------------------------------------------------------------------
 * We should be caching $LISTS in SESSION and just maintaining both the local copy and the remote copy together.
 * Remember that every list is a task. And every task is a list (of sub-tasks).
 * Other possible-to-implement booleans for tasks_ table structure would be urgent, prunable.
 * Manual order of children maintained by "relation" column, automatic ordering also available but shouldn't affect.
 * Groups should at some point (when we do real JS and XHRs) be collapsible.
 * Need a "My Day" view, should track individual tasks and show them with parentage, but allow for those parents and groupings to be in multiple places around the list.
 * My Day should have alternate ordering, suggest new tasks, collects tasks due same day, retains incomplete tasks from previous day
 * Basically Day displays categories, parentage, groups as decoration.
 * Consider posibility of sharing tasks/lists among groups
 * Another problem is long-text notes per task (not much of a problem though)
 * Add to my day button, due dates/times multiple each, repeat functionality
 * Automatic pruning
 * Feel like closeSession should refresh the page if no destination is supplied instead of redirecting to /login or webroot without any choice to stay put.
 * Deletion cascades.
 * Could have "My Day" by adding int-like column to tasks_ that stores item's position (array index).
 * PWA + 2FA
*/