<html>
    <head>
        <title>TaskTree - Home</title> 
        <link rel="stylesheet" href="index.css">
    </head>
    <body>
        <div class='container'>
            <div class='head'>
                <div>
                    TaskTree
                    <small> a prototype </small>
                </div>
                <options>xer01ne (<a href="logout.php">Logout</a>)</options>
            </div>
            <div class='sidebar'>
                <? echo $HTML_Sidebar ?>
                <option><a href="?pg=0">...</a></option>
            </div>
            <div class='main'>
                <? echo $HTML_Tasklist ?>
            </div>
        </div>
    </body>
</html>