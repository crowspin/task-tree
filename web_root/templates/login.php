<html>
    <head>
        <title>TaskTree - Login</title> 
        <link rel="stylesheet" href="login.css">
    </head>
    <body>
        <block class="center">
            <?
                require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/GLOBALS.php";
                if (isset($_GET['e'])){
                    $ERROR_MESSAGE = crow\ErrorMsg::$_[$_GET['e']];
                    $LOCK_FIELDS = true;
                }
                echo $ERROR_MESSAGE;
                if (!$LOCK_FIELDS){
            ?>
                <form method="POST">
                    <input type="text" placeholder="Username" name="un">
                    <input type="password" placeholder="Password" name="pw">
                    <br>
                    <input type="submit" value="Login" name="submit">
                </form>
            <?}?>
        </block>
    </body>
</html>