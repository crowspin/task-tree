<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"])) crow\Header\redirect("/login.php");
// I *know* there's an Apache security thing I could be doing instead, but I've never taken the time to learn that, so for the sake of getting this done sooner-than-never...

/**
 * First iteration of function to separate completed tasks away from incomplete reveals that the way we're holding tasks in an array and
 *     children are only references is actually also overwriting rows when we process them (There's only one $LISTS[6], but we /had/ two
 *     rows with slightly different data (just parent and idx columns)). This might be a moot issue, but it could be bigger depending on
 *     how work on modify.php proceeds.
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

    public function generate_html_tasklist(): string {
        // Show parentage somehow, reverse navigation
        // Maybe when we cache the sidebar structure we'll create a navigation tree in session, and when we load a new page we'll either find 
        //     the page id as a child of the last page we were on and show a link to go back, or we won't and we'll just highlight the sidebar.
        // Observed opportunity for this while fixing $CURRENT_PAGE logic; could be something to think about rel. "If $_GET["pg"] is not an index of $LISTS"
        $headblock = "<div>";
        if (!$this->row["is_group"]) $headblock .= "<input type=checkbox" . (($this->row["complete"])?" checked":"") . " onclick=\"location.href='modify.php?id=" . $this->id . "&action=toggleComplete" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'\"id=\"toggleComplete['" . $this->id . "']\"/>";
        $headblock .= "<h1>" . $this->row["text"] . "</h1>";
        $headblock .= "<a href='modify.php?id=" . $this->id . "&action=addChild" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>+</a>";
        if ($this->id != "0") $headblock .= "<a href='modify.php?id=" . $this->id . "&action=edit" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>...</a>";
        $headblock .= "</div>";

        $divider = "<div class='midline'><h2>Completed</h2></div>";

        $incomplete = "";
        $complete = "";

        for ($i = 0; $i < count($this->children); $i++){
            [$inc, $com] = $this->children[$i]->generate_html_tasklist_r();
            $incomplete .= $inc;
            $complete .= $com;
        }
        
        if (strlen($complete) > 0) return $headblock . $incomplete . $divider . $complete;
        else return $headblock . $incomplete;
    }

    private function generate_html_tasklist_r(): array {
        $INCOMPLETE = "";
        $COMPLETE = "";
        if ($this->row["is_group"]){
            $INC = "";
            $COM = "";
            for ($i = 0; $i < count($this->children); $i++){
                [$inc, $com] = $this->children[$i]->generate_html_tasklist_r();
                $INC .= $inc;
                $COM .= $com;
            }
            $grouphead = "<group><div><h3 onclick=\"location.href='?pg=" . $this->id . "'\">" . $this->row["text"] . "</h3><a href='modify.php?id=" . $this->id . "&action=addChild" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>+</a><a href='modify.php?id=" . $this->id . "&action=edit" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>...</a></div>";
            $groupfoot = "</group>";
            if (strlen($INC) > 0){
                $INCOMPLETE .= $grouphead . $INC . $groupfoot;
            }
            if (strlen($COM) > 0 || count($this->children) == 0){
                $COMPLETE .= $grouphead . $COM . $groupfoot;
            }
        } else {
            $p1 = "<li><input type=checkbox";
            $p2 = " onclick=\"location.href='modify.php?id=" . $this->id . "&action=toggleComplete" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'\"id=\"toggleComplete['" . $this->id . "']\"/><p onclick=\"location.href='?pg=" . $this->id . "'\">" . $this->row["text"] . "</p><a href='modify.php?id=" . $this->id . "&action=edit" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>...</a></li>";
            if ($this->row["complete"]){
                $COMPLETE .= $p1 . " checked" . $p2;
            } else {
                $INCOMPLETE .= $p1 . $p2;
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
$CURRENT_LIST = (isset($_GET["pg"]) && $_GET["pg"] != "") ? $_GET["pg"] : $first_list;

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
$HTML_Tasklist = $TASKS[$CURRENT_LIST]->generate_html_tasklist();

include __DIR__ . "/templates/index.php";

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