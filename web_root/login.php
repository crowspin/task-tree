<?php
#session start
    if (isset($_POST['submit'])){
        echo $_POST['un'] . " " . $_POST['pw'];
        #check fail count in session (gt 5, auto fail)
        #clean inputs
        #initialize connection to database
        #if fail > simple error page
        #test inputs against database
        #if successful > set username in session
        #else(fail) > increment fail count in session and display error
    }
#if username already set in session, redirect
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