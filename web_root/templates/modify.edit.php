<!DOCTYPE html>
<html>
    <head>
        <title>TaskTree - Editor</title> 
        <link rel="stylesheet" href="css/login.css">
        <meta name="viewport" content="width=device-width, initial-scale=0.75">
    </head>
    <body>
        <block class="center">
            <form method="POST">
                <input type="text" placeholder="Task" name="txt" value="<? echo $EDIT_VALS["text"]; ?>">
                <br>
                <input type="checkbox" id="is_group" name="is_group" <? if ($EDIT_VALS["is_group"]) echo "checked";?>>
                <label for="is_group">Is Group?</label>
                <? if (isset($EDIT_VALS["complete"])){?>
                    <br>
                    <input type="checkbox" id="complete" name="complete" <? if ($EDIT_VALS["complete"]) echo "checked";?>>
                    <label for="complete">Is Complete?</label>
                <? } ?>
                <br>
                <input type="submit" value="<? echo (isset($EDIT_VALS["complete"]))?"Submit Changes":"Add new task"; ?>" name="submit">
                <input type="submit" value="Cancel" name="back">
                <? if (isset($EDIT_VALS["complete"])){?>
                    <input type="submit" value="Delete this task" name="del">
                <? } ?>
            </form>
        </block>
    </body>
</html>