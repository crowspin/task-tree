<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"])) crow\Header\redirect("/login.php");
//! I *know* there's an Apache security thing I could be doing instead, but I've never taken the time to learn that, so for the sake of getting this done sooner-than-never...

//echo "Hello " . $_SESSION["login"]["username"] . "! You have logged in successfully.";

/**
 * Current expected table structure: int(id), bit(is_group), varchar1024(children)
 * New MVP: fetch and display existing tasks, mark tasks completed, add new tasks. When we have that, we're calling it and getting back to lessons.
 * 
 * For Later: ----------------------------------------------------------------------------------------------------
 * Remember that every list is a task. And every task is a list (of sub-tasks).
 * Other possible-to-implement booleans for tasks_ table structure would be completion, urgent, readonly.
 * Manual order of children maintained by "children" column, automatic ordering also available by shouldn't affect.
 * Groups should at some point (when we do real JS and XHRs) be collapsible.
 * Need a "My Day" view, should track individual tasks and show them with parentage, but allow for those parents and groupings to be in multiple places around the list.
 * My Day should have alternate ordering, suggest new tasks, collects tasks due same day, retains incomplete tasks from previous day
 * Basically Day displays categories, parentage, groups as decoration.
 * Consider posibility of sharing tasks/lists among groups
 * (kind of need a register form for that though haha)
 * Another problem is long-text notes per task
 * Add to my day button, move up/down button, due dates/times multiple each, repeat functionality
 * Automatic pruning
 * Feel like closeSession should refresh the page if no destination is supplied instead of redirecting to /login or webroot without any choice to stay put.
 */
/** Reference code for many:many related rows, tables
 * 
-- create table tasks (id integer, is_group bit, children varchar(1022));
-- create table relation (parent integer, child integer);

-- insert into tasks (id, is_group) values (0, 1), (1, 0), (2, 0), (3, 1), (4, 1), (5, 1), (6, 0);
-- insert into relation (parent, child) values (0, 1), (0, 3), (0, 4), (3, 2), (4, 5), (5, 6), (0, 6);

SELECT tasks.* FROM tasks
-- JOIN relation ON tasks.id = relation.child AND relation.parent = 5 -- Find children of row id 5
-- JOIN relation ON tasks.id = relation.parent AND relation.child = 0 -- Find parents of row id 0
 *
 * Add indexing described here: https://mysql.rjweb.org/doc.php/index_cookbook_mysql#many_to_many_mapping_table
 * 
 * We should be checking that the tables exist here (SHOW TABLES;), and if they don't we need to make them.
 * Must remember to populate tasks_ with special <root> task at id=0, and default "Tasks" list at id=1
 * 
 * Future INSERTs can be done by fetching lowest unused id, using following query:
 *      SELECT (Min(ID) + 1) AS NewIDToInsert FROM TableName T2A WHERE NOT EXISTS (SELECT ID FROM TableName T2B WHERE T2A.ID + 1 = T2B.ID)
 */
/**
 * I think I want a version that pulls parents instead of children.
 * I can't see if I've mentioned it anywhere else here but we should also be caching the list list in SESSION and just maintaining
 *      both the local copy and the remote copy together. Changes are expected to be very limited compared to pageloads.
 * We'll probably need to leverage autocommit off and transactions for mass changes later on...
 */
/**
 * Late start today; I've scrubbed the entire house. Cleared all the cobwebs, cleaned the oven as best as I can do for now.
 * Anyway, glad as I am for all that, let's get cracking on another rewrite.
 *
 * Make sure that magic id=0 has text &lt;root&gt; instead of <root>, as the latter will be interpreted as html <3
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
            if ($this->row["is_group"]){
                $rv .= "<li><img>" . $this->row["text"] . "</li><group>";
            }
            else {
                if ($this->id == $current_list) $rv .= "<li class='active'><img><a href='?pg=" . $this->id . "'>" . $this->row["text"] . "</a></li>";
                else $rv .= "<li><img><a href='?pg=" . $this->id . "'>" . $this->row["text"] . "</a></li>";
            }
        }
        for ($i = 0; $i < count($this->children); $i++){
            $rv .= $this->children[$i]->generate_html_sidebar($current_list);
        }
        if ($this->id != "0" && $this->row["is_group"]) $rv .= "</group>";
        return $rv;
    }

    public function generate_html_tasklist($root_node_id): string {
        $rv = "";
        if ($this->id == $root_node_id){
            $rv .= "<div><h1>" . $this->row["text"] . "</h1><a href=uhoh.php>...</a></div>";
            for ($i = 0; $i < count($this->children); $i++){
                $rv .= $this->children[$i]->generate_html_tasklist($root_node_id);
            }
        } else {
            if ($this->row["is_group"]){
                $rv .= "<group><div><h3>" . $this->row["text"] . "</h3><a href=uhoh.php>...</a></div>";
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
//! Need logic to invalidate $_GET["pg"] where the page requested is_group.
//! Or maybe not? If a user wants to see that it's not harmful?? We could just not link it and leave the sneaky people alone.
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

/**
 * Full disclosure: I've had very little sleep last night. I'm not sure why, but over the last couple of months it's become
 *      increasingly common for me to awake at 4am and be unable to fall back asleep. Not sure why 4am specifically, if it's
 *      related to something happening in the house or if it's just "about 6 hours of sleep." Either way, I'm a bit underslept
 *      (probably) and I've just finished a pretty stiff drink. I'm going to keep working on the CSS, making this look decent,
 *      but I'm going to hit a wall, and soon. I'm sure this doesn't look great to some future employer, but I gotta enjoy my
 *      only "day off" this week somehow.
 * As things are this moment: We aren't making any tables, and don't have the ability to modify them in any way, but we are
 *      able to generate HTML to display them in what I think is an efficient manner. There's more work to be done yet on the
 *      HTML side as well, but mostly I'm worrying about the CSS for display of the two collections of lists.
 * ((oh boy i feel that drink already))
 * I'm not feeling it anymore Mr. Krabs. :c
 */