<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Session/open.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/Header/redirect.php";

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

if (!($sql = loadTasks())){
    createTable();
}

