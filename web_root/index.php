<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"])) crow\Header\redirect("/login.php");
// I *know* there's an Apache security thing I could be doing instead, but I've never taken the time to learn that, so for the sake of getting this done sooner-than-never...

/**
 * When on a task's page, show checkbox beside h1.
 * H1 and H3 tags need link to addChild.
 * Completed tasks should be separately grouped.
 * Groups with no leaf nodes should be hidden.
 * //!
 */

class TaskTreeNode {
    public int $id;
    public array $children;
    public array $row;

    function __construct($row){
        $this->id = $row["id"];
        $this->children = [];
        $this->row = $row;
    }

    public function first(){
        foreach ($this->children as &$child){
            if (!$child->row["is_group"]) return $child->id;
            if (count($child->children) == 0) continue;
            $result = $child->first();
            if ($result) return $result;
        }
        return false;
    }

    public function generate_html_sidebar($current_list): string {
        $rv = "";
        if ($this->id != "0"){
            if ($this->id == $current_list) $rv .= "<li class='active'><img><a href='?pg=" . $this->id . "'>" . $this->row["text"] . "</a></li>";
            else $rv .= "<li><img><a href='?pg=" . $this->id . "'>" . $this->row["text"] . "</a></li>";
            if ($this->row["is_group"]) $rv .= "<group>";
        }
        for ($i = 0; $i < count($this->children); $i++){
            $rv .= $this->children[$i]->generate_html_sidebar($current_list);
        }
        if ($this->id != "0" && $this->row["is_group"]) $rv .= "</group>";
        return $rv;
    }

    public function generate_html_tasklist($root_node_id): string {
        // Show parentage somehow, reverse navigation
        // Maybe when we cache the sidebar structure we'll create a navigation tree in session, and when we load a new page we'll either find 
        //     the page id as a child of the last page we were on and show a link to go back, or we won't and we'll just highlight the sidebar.
        $rv = "";
        if ($this->id == $root_node_id){
            $rv .= "<div><h1>" . $this->row["text"] . "</h1>";
            if ($root_node_id != "0") $rv .= "<a href='modify.php?id=" . $this->id . "&action=edit'>...</a>";
            $rv .= "</div>";
            for ($i = 0; $i < count($this->children); $i++){
                $rv .= $this->children[$i]->generate_html_tasklist($root_node_id);
            }
        } else {
            if ($this->row["is_group"]){
                $rv .= "<group><div><h3 onclick=\"location.href='?pg=" . $this->id . "'\">" . $this->row["text"] . "</h3><a href='modify.php?id=" . $this->id . "&action=edit'>...</a></div>";
                for ($i = 0; $i < count($this->children); $i++){
                    $rv .= $this->children[$i]->generate_html_tasklist($root_node_id);
                }
                $rv .= "</group>";
            } else {
                $rv .= "<li><input type=checkbox" . (($this->row["complete"])?" checked":"") . " onclick=\"location.href='modify.php?id=" . $this->id . "&action=toggleComplete'\"id=\"toggleComplete['" . $this->id . "']\"/><p onclick=\"location.href='?pg=" . $this->id . "'\">" . $this->row["text"] . "</p><a href='modify.php?id=" . $this->id . "&action=edit'>...</a></li>";
            }
        }
        return $rv;
    }

    public function separate_completed_items(): array {
        $INCOMPLETE = [];
        $COMPLETE = [];

        for ($i = 0; $i < count($this->children); $i++){
            if ($this->children[$i]->row["is_group"]){
                [$a, $b] = $this->children[$i]->separate_completed_items();
                if (count($a) > 0){
                    $INCOMPLETE[] = $this->children[$i];
                    $INCOMPLETE = array_merge($INCOMPLETE, $a);
                }
                if (count($b) > 0 || count($this->children[$i]->children) == 0){
                    $COMPLETE[] = $this->children[$i];
                    $COMPLETE = array_merge($COMPLETE, $b);
                }
            } else {
                if ($this->children[$i]->row["complete"]) $COMPLETE[] = &$this->children[$i];
                else $INCOMPLETE[] = &$this->children[$i];
            }
        }

        return [$INCOMPLETE, $COMPLETE];
    }
}

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
UNION ALL SELECT * FROM cte r";

$query_root = $sql->query($qs, [$_SESSION["login"]["id"], $q_rid]);
if (!$query_root->success){
    //query failed
}

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
$CURRENT_LIST = (isset($_GET["pg"]) && $_GET["pg"] != "" && isset($LISTS[$_GET["pg"]])) ? $_GET["pg"] : $first_list;

$query_tasks = $sql->query($qs, [$_SESSION["login"]["id"], $CURRENT_LIST]);
if (!$query_tasks->success){
    //query failed
}

$TASKS = [];
foreach ($query_tasks as $row){
    $TASKS[$row["id"]] = new TaskTreeNode($row);
    if ($row["parent"] == "root") continue;
    $TASKS[$row["parent"]]->children[$row["idx"]] = &$TASKS[$row["id"]];
}

$HTML_Sidebar = $LISTS["0"]->generate_html_sidebar($CURRENT_LIST);
$HTML_Tasklist = $TASKS[$CURRENT_LIST]->generate_html_tasklist($CURRENT_LIST);

include __DIR__ . "/templates/index.php";

[$inc, $com] = $LISTS[0]->separate_completed_items();
echo "<pre><code>";
print_r($inc);
echo "\n\n\n";
print_r($com);
echo "</code></pre>";

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