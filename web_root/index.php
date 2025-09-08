<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"])) crow\Header\redirect("/login.php");
//! I *know* there's an Apache security thing I could be doing instead, but I've never taken the time to learn that, so for the sake of getting this done sooner-than-never...

echo "Hello " . $_SESSION["login"]["username"] . "! You have logged in successfully.";

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

class TaskTree_Node {
    public int $id;
    public bool $is_group = false;
    public array $children;
    public array $row;

    function __construct($row, $override_populate = false){
        $this->id = $row["id"];
        $this->row = $row;
        $this->is_group = true;
        if ($row["is_group"] || $override_populate){
            $this->populate_children();
        }
    }

    private function populate_children(){
        $child_id_set = json_decode($this->row["children"]);
        if (empty($child_id_set)) return;

        $sql = crow\IO\SQLFactory::get();
        if (!$sql){
            //no sql connection
        }

        $query_children = $sql->query("SELECT tasks_%0.* FROM tasks_%0 JOIN relations_%0 ON tasks_%0.id = relations_%0.child AND relations_%0.parent = %1", [$_SESSION["login"]["id"], $this->id]);
        if(!$query_children->success){
            //query failed
        }
        $query_children->map_to_column("id");
        foreach ($child_id_set as $id) $this->children[] = new TaskTree_Node($query_children[$id]);
    }
    
    public function first(){
        foreach ($this->children as $child){
            if (!$child->is_group) return $child->row;
            if ($child->is_group && count($child->children) == 0) continue;
            $result = $child->first();
            if ($result) return $result;
        }
        return false;
    }

    public function find($id){
        if ($this->id == $id) return $this->row;
        if ($this->is_group){
            foreach ($this->children as $child){
                $result = $child->find($id);
                if ($result) return $result;
            }
        }
        return false;
    }
}

$sql = crow\IO\SQLFactory::get();
if (!$sql){
    //no sql connection
}
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

$query_root = $sql->query("SELECT * FROM `tasks_%0` WHERE `id`='0'", [$_SESSION["login"]["id"]]);
if (!$query_root->success){
    //query failed
}

$SIDEBAR = new TaskTree_Node($query_root[0]);

if (!empty($_GET["pg"])) $task_list = $SIDEBAR->find($_GET["pg"]) ?: $SIDEBAR->first();
else $task_list = $SIDEBAR->first();
if ($task_list == false){
    //error, no page requested, no lists?
}

$TASKS = new TaskTree_Node($task_list, true);

include __DIR__ . "/templates/index.php";