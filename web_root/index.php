<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"])) crow\Header\redirect("/login.php");
//! I *know* there's an Apache security thing I could be doing instead, but I've never taken the time to learn that, so for the sake of getting this done sooner-than-never...

/**
 * I can't see if I've mentioned it anywhere else here but we should also be caching the list list in SESSION and just maintaining
 *      both the local copy and the remote copy together. Changes are expected to be very limited compared to pageloads.
 * Actually, I'm going to leverage ?pg=0 as an "edit lists" page, so id=0's text should be more like "Editing Lists...", something attractive.
 * Change ids to smaller int-type in db; "make them as small as is safe", unsigned.
 * Add position column to relation table to replace children column in tasks table? We thought about doing this before, but I think I was 
 *      going about it wrong then. If we store a position (array index) alongside each pair, we could observe that as `child` is 
 *      `position`'th child of `parent.` And it would be specific to the relation, working for many:many.
 * Drop "label" tags; want to have task name onclick like group names have. Also, when on a task's page, show checkbox beside page's name.
 * Project will be considered submittable once we can toggle completion, add/remove tasks, edit tasks. Shift position of tasks. Completed
 *      tasks should be separately grouped. Need register form.
 */

/**
 * For Future Versions:
 * ----------------------------------------------------------------------------------------------------
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

class TaskTreeNode {
    public int $id;
    public array $children;
    public array $child_order;
    public array $row;

    function __construct($row){
        $this->id = $row["id"];
        $this->children = [];
        $this->child_order = TaskTreeNode::decode_child_order($row["children"]);
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
        //! Show parentage somehow, reverse navigation
        //! Maybe when we cache the sidebar structure we'll create a navigation tree in session, and when we load a new page we'll either find 
        //!     the page id as a child of the last page we were on and show a link to go back, or we won't and we'll just highlight the sidebar.
        $rv = "";
        if ($this->id == $root_node_id){
            $rv .= "<div><h1>" . $this->row["text"] . "</h1>";
            if ($root_node_id != "0") $rv .= "<a href=uhoh.php>...</a>";
            $rv .= "</div>";
            for ($i = 0; $i < count($this->children); $i++){
                $rv .= $this->children[$i]->generate_html_tasklist($root_node_id);
            }
        } else {
            if ($this->row["is_group"]){
                $rv .= "<group><div><h3 onclick=\"location.href='?pg=" . $this->id . "'\">" . $this->row["text"] . "</h3><a href=uhoh.php>...</a></div>";
                for ($i = 0; $i < count($this->children); $i++){
                    $rv .= $this->children[$i]->generate_html_tasklist($root_node_id);
                }
                $rv .= "</group>";
            } else {
                $rv .= "<li><input type=checkbox id='toggleComplete_" . $this->id . "'/><label for='toggleComplete_" . $this->id . "'>" . $this->row["text"] . "</label><a href=uhoh.php>...</a></li>";
            }
        }
        return $rv;
    }

    private static function decode_child_order($children_json): array {
        $child_id_array = json_decode($children_json);
        $return_array = [];
        foreach ($child_id_array as $idx=>$val){
            $return_array[$val] = $idx;
        }
        return $return_array;
    }
}

$sql = crow\IO\SQLFactory::get();
if (!$sql){
    //no sql connection
}
/**
 * We should be checking that the tables exist here (SHOW TABLES;), and if they don't we need to make them.
 * Must remember to populate tasks_ with special <root> task at id=0, and default "Tasks" list at id=1
 * Use structure available through phpma portal.
 */ 
$q_rid = 0;
$qs = "WITH RECURSIVE cte AS (
	SELECT	a.*, b.parent
	FROM	tasks_%0 AS a
    JOIN    relations_%0 AS b
	ON      a.id = b.child AND b.parent = %1
	UNION ALL 
    SELECT	cld.*, rel.parent
	FROM	cte prt
    JOIN    relations_%0 rel ON prt.id = rel.parent AND prt.is_group = 1
	JOIN    tasks_%0 cld ON cld.id = rel.child
)
SELECT *, 'root' AS parent FROM tasks_%0 WHERE id = %1 
UNION ALL SELECT * FROM cte r";

$query_root = $sql->query($qs, [$_SESSION["login"]["id"], $q_rid]);
if (!$query_root->success){
    //query failed
}

$LISTS = [];
foreach ($query_root as $row){
    $LISTS[$row["id"]] = new TaskTreeNode($row);
    if ($row["parent"] == "root") continue;
    $LISTS[$row["parent"]]->children[$LISTS[$row["parent"]]->child_order[$row["id"]]] = &$LISTS[$row["id"]];
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
    $TASKS[$row["parent"]]->children[$TASKS[$row["parent"]]->child_order[$row["id"]]] = &$TASKS[$row["id"]];
}

$HTML_Sidebar = $LISTS["0"]->generate_html_sidebar($CURRENT_LIST);
$HTML_Tasklist = $TASKS[$CURRENT_LIST]->generate_html_tasklist($CURRENT_LIST);

include __DIR__ . "/templates/index.php";