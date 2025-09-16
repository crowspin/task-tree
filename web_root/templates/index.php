<!DOCTYPE html>
<html>
    <head>
        <title>TaskTree - Home</title> 
        <link rel="stylesheet" href="css/index.css">
        <meta name="viewport" content="width=device-width, initial-scale=0.75">
    </head>
    <body>
        <div class='container'>
            <div class='head'>
                <div>
                    TaskTree
                    <small> a prototype </small>
                </div>
                <options><? echo $_SESSION["login"]["username"] ?> (<a href="logout.php">Logout</a>)</options>
            </div>
            <div class='sidebar'>
                <? echo $HTML_Sidebar ?>
            </div>
            <div class='main'>
                <? echo $HTML_Tasklist ?>
            </div>
        </div>
    </body>
</html>