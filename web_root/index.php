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

/**
 * Did some more thinking, realized that there was a major flaw in allowing the recursion of declaration of TaskTree_Node to make SQL calls
 * Well, really already knew but realized how dangerous it really was
 * Had the thought this morning to try and separate the lists out and just pull all of them at once to prevent that issue
 * Then realized that not only would that complicate the many:many relationship table pretty severely, it wouldn't actually help
 *      at all because of the lists having group capabilities as well, resulting in the same flaw. (User creates a bajillion groups, 
 *      server makes a bajillion calls every pageload.)
 * Decided there had to be a way to convince SQL to find all the children in the tree from a single node downward, and still have it
 *      stop when it finds an item that isn't a group.
 * Lots of thinking, and a little googling later I find https://stackoverflow.com/a/44573759/31456364
 * Then some time in db<>fiddle (where their answer was hosted) modifying it for my many:many use-case
 * And now we have this:
DECLARE @TableA TABLE (
	id			INT NOT NULL PRIMARY KEY,
	is_group    BIT DEFAULT 0
);

DECLARE @TableB TABLE (
    parent      INT,
    child       INT
);

INSERT INTO @TableA
  VALUES 
  (0, 1),
  (1, 0),
  (2, 0),
  (3, 1),
  (4, 1),
  (5, 1),
  (6, 0),
  (7, 0);

INSERT INTO @TableB
  VALUES
  (0, 1),
  (0, 3),
  (0, 4),
  (3, 2),
  (4, 5),
  (5, 6),
  (0, 6),
  (6, 7);

WITH CteRecursive
AS (
	SELECT	a.*, CONVERT(HIERARCHYID, '/' + LTRIM(a.id) + '/') AS HID
	FROM	@TableA AS a
    JOIN    @TableB AS b
	ON	a.id = b.child AND b.parent = 5 -- change # to id of item to search children of
	UNION ALL 
    SELECT	cld.id, cld.is_group, CONVERT(HIERARCHYID, prt.HID.ToString() + LTRIM(cld.id) + '/') AS HID
	FROM	 CteRecursive prt
    JOIN @TableB rel ON prt.id = rel.parent AND prt.is_group = 1
	JOIN @TableA cld ON cld.id = rel.child
)
SELECT *, r.HID.ToString() AS HIDToString FROM CteRecursive r
ORDER BY r.HID ASC
 * The DECLARE TABLE statements make a temporary table for me to work with in query, apart from the addition of @TableA.id 7 (child of 6), 
 *      the DECLARE and INSERT statements are the same as they were in the previous experiments.
 * As I understand it, `WITH CteRecursive AS` declares a temporary table to be built with nested queries, and the Recursive part is just
 *      because it refers to itself in the second half. It's not a library like WITH suggests. This also (maybe unneccesarily) retains the
 *      HID style---
 * I went and made more changes. I didn't like the HID sorting because I don't have a use for the priority column used in the original
 *      version from StackOverflow, so I did away with that. Now it returns the requested row as well as all that rows children, and it
 *      does so in an order that I'm more comfortable with building a tree out of. I have more changes planned however: I think I want
 *      to change that order further (it's odd that it seems to build from the bottom of a list up to the top?) and I also need a version
 *      that pulls parents instead of children. (I've been derailed by a phone call. He always calls at the WORST times.)
 * Anyways this is what I have right now:
DECLARE @Q INT = 6; -- Only change this
WITH CteRecursive
AS (
	SELECT	a.*, b.parent
	FROM	@TableA AS a
    JOIN    @TableB AS b
	ON      a.id = b.child AND b.parent = @Q
	UNION ALL 
    SELECT	cld.*, rel.parent
	FROM	CteRecursive prt
    JOIN    @TableB rel ON prt.id = rel.parent AND prt.is_group = 1
	JOIN    @TableA cld ON cld.id = rel.child
)
SELECT  *, NULL AS parent 
FROM    @TableA
WHERE   id = @Q 
UNION ALL 
SELECT  *
FROM    CteRecursive r
 * No more HID stuff, prints the parent as null for the requested row and then the value from relation.parent for the children
 * Pretty happy with myself for getting *some* understanding of UNION ALL and JOIN, but I can't pretend I fully understand CTE yet.
 * I'm just looking at the result of searching for id=0 and thinking about how I'll work with that data.
 * Having one call that fetches everything is good, but then it means the recursive nature of declaring TaskTree_Node as I am doing
 *      currently goes out the window.
 * It's been a couple hours, I've had a puff and a drink and dinner and all.
 * I was originally going down the query line by line explaining it for myself for later. WITH..AS() declares CteRecursive as a 
 *      reference to a sub-query generated table. The table is the union of members of a where their id is referenced child/parent
 *      pair where a.id is b.child and b.parent is the target node, and also the results of recursively locating the children of those
 *      members with mostly the same logic. The change for the second half (apart from recursion) is that if the located child is not
 *      a group, we don't look past it. Then, we select the row we personally targetted and return that along with all the children
 *      from CteRecursive.
 * I stopped earlier because I was so compelled to rewrite it again, but had to copy the original into the fiddle to compare and
 *      make sure I wasn't goofin' it up.
 * The result of the most recent query format is:
 id     is_group    parent
 0 	    True 	    null
 1 	    False 	    0
 3 	    True 	    0
 4 	    True 	    0
 6 	    False 	    0
 5  	True 	    4
 6 	    False 	    5
 2 	    False 	    3

 * You can see the queried row 0 comes first, then it's children, but you'll notice then the first of the grandchildren is 5, not 2.
 * I'm pretty sure it's a factor of the structure of the query, but I think if I flip the two sides it'll explode. Either way it
 *      will produce a parent node before any of it's children, so looping over it shouldn't be a bad choice. My stress is just 
 *      with the ordering then. But theoretically, if I write the loop right, I'll have the value of the children row on hand,
 *      and I can just refer to that. Placing child row y into parent row x could look like:

x.children[x.child_ids[y.id]] = new TaskTree_Node(y);

 * But then child_ids would have to be {row.id=>rank} and right now it's just [id, id, id]. I'd rather not use array_find, or store
 *      rank explicitly. I could just write a separate wrapper for json_encode and json_decode that would take care of that for me.
 * Now I'm thinking about the size of the children column again. A kilobyte each isn't horrid, it'd be a thousand entries before
 *      children column allocations cost me one megabyte. And it's worth 200 children if the ids were all four digits in length?
 *      I think? Should be fine. But also I kinda wish it were better. The priority column has been an idea in the back of my head
 *      since project inception, but also with multiple parents, having a one-per-row priority ranking would be pretty complicated.
 *      If not impossible. Imagine a system where the priority was limited to 0-1000, and there were two lists, one filled with
 *      1000 direct descendants (children column wouldn't support it but whatever,) and the other with only five rows. If one of
 *      the five was a shared object, then there'd be an overflow. And that's not to mention the back of my head thought about
 *      cross account sharing of lists.
 * I can't see if I've mentioned it anywhere else here but we should also be caching the list list in SESSION and just maintaining
 *      both the local copy and the remote copy together. Changes are expected to be very limited compared to pageloads.
 */