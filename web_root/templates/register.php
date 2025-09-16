<!DOCTYPE html>
<html>
    <head>
        <title>TaskTree - Register</title> 
        <link rel="stylesheet" href="css/login.css">
        <meta name="viewport" content="width=device-width, initial-scale=0.75">
    </head>
    <body>
        <block class="center">
            <?
                require_once $_SERVER["DOCUMENT_ROOT"] . "/lib/crowlib-php/GLOBALS.php";
                if (!empty($_GET["e"])){
                    $ERROR_MESSAGE = crow\ErrorMsg::$_[$_GET["e"]];
                }
                echo $ERROR_MESSAGE;
            ?>
            <form method="POST">
                <input type="text" placeholder="Username" name="un"><br>
                <br>
                <input type="password" placeholder="Password" name="pw"><br>
                <input type="password" placeholder="Confirm Password" name="pwc"><br>
                <br>
                <input type="submit" value="Register" name="submit">
                <input type="submit" value="Cancel" name="back" formaction="login.php">
            </form>
        </block>
    </body>
</html>