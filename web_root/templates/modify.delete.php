<!DOCTYPE html>
<html>
    <head>
        <title>TaskTree - Delete?</title> 
        <link rel="stylesheet" href="css/login.css">
        <meta name="viewport" content="width=device-width, initial-scale=0.75">
    </head>
    <body>
        <block class="center">
            <form method="POST">
                Are you sure you want to delete the task: "<i><? echo $TEXT; ?></i>"?
                <br>
                <input type="submit" value="No, leave it be." name="back">
                <input type="submit" value="Yes, delete it." name="delete">
            </form>
        </block>
    </body>
</html>