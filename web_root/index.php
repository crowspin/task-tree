<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/IO/SQLFactory.php";

if (!crow\Session\open()) die();
if (empty($_SESSION["login"]["username"])) crow\Header\redirect("/login.php");
//! I *know* there's an Apache security thing I could be doing instead, but I've never taken the time to learn that, so for the sake of getting this done sooner-than-never...

echo "Hello " . $_SESSION["login"]["username"] . "! You have logged in successfully.";

/**
 * So we've logged in now
 * We have the username
 * SQL table for to-do items will probably be named by username
 * CREATE TABLE $username (uint id, children, parents, varchar256 text, bitflags, created, modified, completed)
 * children/parents will be text lines built from ids braced and interspersed with asterisks (ie. *2*4*43*51*)
 * This way we can just split on asterisk to get an array, and we can also search with a "parents CONTAINS *id_to_search*"
 * Though double-linkage pretty much removes the need for searching like that.. 
 * We also can fetch entries without parents by fetching indicies that have only an asterisk in the parents column
 * Those root rows would be treated as views. 
 * Ah, but we wanted to have categories on every level right? So the sidebar (which lists all the lists) must have a grouping tool as well,
 * meaning that the real root nodes will be list categories, and their children would be lists (pages), and their children would be task 
 * groups, then tasks, then sub-task groups, then sub-tasks.
 * But then every sub task is treated like a task, and so on...
 * 
 * Bitflags will include completion, urgent, ---category?---, readonly
 * Custom ranking can be implied through children column, automatic might be creation date, etc.
 * Whether or not a row is a category could be deduced from whether or not the object's depth in the tree is even (depth 0 (root) = category, group of lists, depth 1 = list, depth 2 = group of tasks)
 * Then, if we go from a list to a task directly, we could just have an 'ungrouped' default item added to each even-depth item automatically which would not be displayed in app.
 * Even-depth rows can still be 'completed' or 'urgent'
 * readonly prevents pruning as well as user modification, allows deletion with extra verification check
 * 
 * Categories can be collapsed, sorted (inner and outer)
 * My Day view must exist, should be able to show odd-depth tasks with their groupings in different places (so if you have to do something out of that group in the middle, the group is displayed twice)
 * My Day should have alternate ordering, suggest new tasks, collects tasks due same day, retains incomplete tasks from previous day
 * Basically Day displays categories, parentage, even-depths as decoration, and is actually ordered exclusively based on odd-depth rows.
 * 
 * Consider posibility of sharing tasks/lists among groups
 * (kind of need a register form for that though haha)
 * Another problem is long-text notes per task, that's a separate table. Can't just have username as table name if each user has two+ tables.
 * Add to my day button, move up/down button, due dates/times multiple each, repeat functionality
 * Automatic pruning
 * 
 * I'm going to be passing on any and all Javascript operations here, I don't want to deal with that at this junction. Not using XHRs is going to make the page 
 * feel pretty darn clunky, but I want a functioning prototype, not a finished product.
 * 
 * Feel like closeSession should refresh the page if no destination is supplied instead of redirecting to /login or webroot without any choice to stay put.
 */
//

/**
 * Operation plan: first we're just going to set up the database with tables for my user, then we'll do a 'pretty print' of listed tasks as a tree
 * We'll likely need to create a method to add tasks to the tree between those two operations.
 * And for the sake of making the whole thing a little lighter on my brain, we'll stop there.
 */

//Table now needs id, parent, children, bitflags
    //Unhappy with bitflags column, apparently I'm a psycho for wanting to do that because it's 'hard to read'
    //Change bitflags to column(bit, is_group) mainly because I can't think of another boolean value we need to keep right now
//Do we need double linkage? Or can we use another boolean to indicate root level? Or should we have a special row at position 0 that is itself 'root' just to hold children and order-of?
    //Double linkage offers some extra peace of mind for lost items, but whole-table traversal for M&S garbage collection wouldn't be difficult...
    //I don't think I really need doublelinkage, and boolean won't help with root order, so special row it is.
    //Make sure special row has id 0 and is_group 1
//Do we need to use the asterisks, or could we just do commas? What should the column type be?
    //yes, no, VarChar 512
//Table structure changing to int(id), varchar512(children), bit(is_group)

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
        $child_id_set = explode("*", substr($this->row["children"], 1, -1));
        if (empty($child_id_set)) return;

        $sql = crow\IO\SQLFactory::get();
        if (!$sql){
            //no sql connection
        }

        //!optimize me with stringbuilder, limit 50 to each call, lists must all be loaded, tasks can be loaded as 'pages'
/*$built_string = "";
$children = explode_children();
for ($i = 0; $i < count($children); $i++){
    $id = $children[$i];
    $built_string .= "`id`='$id'";
    if (isset($children[$i+1])) $built_string .= " OR ";
}*/
        //!Don't like this process bc on SQL side I imagine *all* rows will be compared against however many id=XX checks we put in the query, resulting in nm tests where n is table size and m is query comparison count
        //!Strong argument here for parent references on each row, because then I could just query with LIMIT 50 OFFSET (50*(pg - 1))
        //Trouble with that is then our want for multiple-parentage, because an easy lookup (WHERE parent=XX) would prevent that, but I'm not sure that a LIKE statement would be lightweight enough?
        //All this thinkelage is just because these single SELECT queries are also pretty cumbersome depending on how many children a row has. (children is Varchar512 ~= 100 children of ids (len=4))
        //100 queries in a fraction of a second doesn't *sound* good, but operation cost should actually be low thanks to indexing?
        //Could use an IN() statement to reduce comparisons per row from nm (ln102) to just n (WHERE id IN (XX, XY, XZ, YX, YY...)) instead of (WHERE id=XX OR id=XY OR...)
        //Also, somewhere in the to-refactor codebase I remember using a function that encoded and decoded arrays much more reliably than this explode might be. Want to look that up, but not sure how searchable the encoded string would be.
        //Options as of now for me to decide between then are (parent column + WHERE parents LIKE(row_id_blob)), (WHERE id IN(list_of_child_ids)), (pull whole table into php memory and skip database query problems giving up database optimized lookups..)
        //parent column option could retain order from child column by sorting on column id using child column as reference, shouldn't be heavy operation, but want to avoid sort operations
        //IN() option won't return items in order of child column anyway, sort would be neccesary either way.
        //Feel like setting up a test environment to compare operation times, feel like the better option is obvious and I'm just a dope.
        foreach ($child_id_set as $id){
            $query_children = $sql->query("SELECT * FROM `tasks_%0` WHERE `id`='%1'", [$_SESSION["login"]["id"], $id]);
            if (!$query_children->success){
                //query failed
            }
            foreach($query_children as $row){
                $this->children[] = new TaskTree_Node($row);
            }
        }
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

//Check that table exists, if not then make.
/** SHOW TABLES; */
//Remember to populate with special id0 <root>
//Populate with list "Tasks", ungrouped, no entries.
//Look for SQL option to fill gaps in id col instead of incrementing upward to inf.
/** Can use following query to obtain table of one column (NewIDToInsert) and one row where resulting field is ID for insert.
 *  Table no longer needs auto-increment if all insertions done with this ID generator.
 *  Just replace TableName with name of table. ((Thanks StackOverflow))
 * 
 * SELECT (Min(ID) + 1) AS NewIDToInsert FROM TableName T2A WHERE NOT EXISTS (SELECT ID FROM TableName T2B WHERE T2A.ID + 1 = T2B.ID)
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