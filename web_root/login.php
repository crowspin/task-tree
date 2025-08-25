<?php
    if (isset($_POST['submit'])){
        echo $_POST['un'] . " " . $_POST['pw'];
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