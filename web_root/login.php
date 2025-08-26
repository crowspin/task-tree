<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "lib/crowlib-php/crowSession.php";

if (!crow\openSession()){
    //error
}

if (!isset($_SESSION["login"]["failed_attempts"])){
    $_SESSION["login"]["failed_attempts"] = 0;
}

if ($_SESSION["login"]["failed_attempts"] >= 5){
    //fail login automatically..
} else {
    if (isset($_POST["submit"])){
        $username = clean($_POST["un"]);
        $password = clean($_POST["pw"]);

        $sql = crow\SQL...
        if (!$sql){
            //error page
        }

        $rv = $sql::query_clean("");
        if ($rv && $rv.count == 1){
            $_SESSION["login"]["username"] = $username;
        } else {
            $_SESSION["login"]["failed_attempts"] += 1;
            //error page
        }
    }
}

if ($_SESSION["login"]["username"]){
    crow\redirect("\applet.php");
}
?>

<html>
    <head>
        <link rel="stylesheet" href="login.css">
    </head>
    <body>
        <block class="center">
            <form method="POST">
                <input type="text" placeholder="Username" name="un">
                <input type="password" placeholder="Password" name="pw">
                <br>
                <input type="submit" value="Login" name="submit">
            </form>
        </block>
    </body>
</html>