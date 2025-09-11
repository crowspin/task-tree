<?php

/**
 * //!
 * Add/Remove/Edit all in one here.
 * Project will be considered submittable once we can toggle completion, add/remove tasks, edit tasks. Shift position of tasks. 
 * We'll probably need to leverage autocommit off and transactions for mass changes later on...
 * Future INSERTs can be done by fetching lowest unused id, using following query:
 *      SELECT (Min(ID) + 1) AS NewIDToInsert FROM TableName T2A WHERE NOT EXISTS (SELECT ID FROM TableName T2B WHERE T2A.ID + 1 = T2B.ID)
 */