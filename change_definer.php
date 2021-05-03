<?php

// ===== configration part starts =====

$for_real = true; // execute DDL statements
$verbose  = true; // show DDL statements 

$db_host = "localhost";
$db_user = "root";
$db_pass = "";

$old_definer_user = "admin";
$old_definer_host = "localhost";

$new_definer_user = "foo";
$new_definer_host = "bar";

// ===== configration part ends =====





$old_definer_sql = "`".$old_definer_user."`@`".$old_definer_host."`";
$old_definer=$old_definer_user."@".$old_definer_host;

$new_definer_sql = "`$new_definer_user`@`$new_definer_host`";

$conn = mysqli_init();
if (!$conn) {
    die('mysqli_init failed');
}

if (!mysqli_real_connect($conn, $db_host, $db_user, $db_pass)) {
    die('Connect Error (' . mysqli_connect_errno() . ') '
        . mysqli_connect_error());
}

// modify views

$get_views = "SELECT * FROM INFORMATION_SCHEMA.VIEWS WHERE DEFINER = '$old_definer'";

$res = mysqli_query($conn, $get_views) or die(mysqli_error($conn));

while($row = mysqli_fetch_assoc($res)) {
  echo "modifying view $row[TABLE_SCHEMA].$row[TABLE_NAME]\n";

  mysqli_select_db($conn, $row["TABLE_SCHEMA"]);

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";
  $alter = "ALTER DEFINER=$new_definer_sql VIEW `$row[TABLE_NAME]` AS $row[VIEW_DEFINITION]";
  
  ddl($conn, $set);
  ddl($conn, $alter);
}
mysqli_free_result($res);

// modify triggers

$get_triggers = "SELECT * FROM INFORMATION_SCHEMA.TRIGGERS WHERE DEFINER = '$old_definer'";

$res = mysqli_query($conn, $get_triggers) or die(mysqli_error($conn));
while($row = mysqli_fetch_assoc($res)) {
  echo "modifying trigger $row[TRIGGER_SCHEMA].$row[TRIGGER_NAME] on table $row[EVENT_OBJECT_SCHEMA].$row[EVENT_OBJECT_TABLE]\n";

  mysqli_select_db($conn, $row["TRIGGER_SCHEMA"]);

  $lock = "LOCK TABLES `$row[EVENT_OBJECT_TABLE]` WRITE";
  $unlock = "UNLOCK TABLES";

  $drop = "DROP TRIGGER `$row[TRIGGER_NAME]`";

  $show = "SHOW CREATE TRIGGER `$row[TRIGGER_NAME]`";

  $res2 = mysqli_query($conn, $show) or die($show.":".mysqli_error($conn));
  $row2 = mysqli_fetch_assoc($res2);
  mysqli_free_result($res2);

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";
  $sql_mode = "SET SESSION SQL_MODE = '$row2[sql_mode]'";

  $create = preg_replace('|^CREATE DEFINER=('.preg_quote($old_definer_sql).')|', "CREATE DEFINER=$new_definer_sql", $row2["SQL Original Statement"]);

  ddl($conn, $set);
  ddl($conn, $sql_mode);
  ddl($conn, $lock);
  ddl($conn, $drop);
  ddl($conn, $create);
  ddl($conn, $unlock);
}  

// modify events

$get_events = "SELECT * FROM INFORMATION_SCHEMA.EVENTS WHERE DEFINER = '$old_definer'";

$res = mysqli_query($conn, $get_events) or die(mysqli_error($conn));
while($row = mysqli_fetch_assoc($res)) {
  echo "modifying event $row[EVENT_SCHEMA].$row[EVENT_NAME]\n";

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";

  // one extra option is needed after EVENT, ON COMPLETION is the simplest so we take this
  $alter = "ALTER DEFINER=$new_definer_sql EVENT `$row[EVENT_SCHEMA]`.`$row[EVENT_NAME]` ON COMPLETION $row[ON_COMPLETION]";

  ddl($conn, $set);
  ddl($conn, $alter);
}

// modify procedures and functions

$get_procedures = "SELECT * FROM INFORMATION_SCHEMA.ROUTINES WHERE DEFINER = '$old_definer'";

$res = mysqli_query($conn, $get_procedures) or die(mysqli_error($conn));
while($row = mysqli_fetch_assoc($res)) {
  echo "modifying ".strtolower($row['ROUTINE_TYPE'])." $row[ROUTINE_SCHEMA].$row[ROUTINE_NAME]\n";

  mysqli_select_db($conn, $row["ROUTINE_SCHEMA"]);

  $drop = "DROP $row[ROUTINE_TYPE] `$row[ROUTINE_NAME]`";

  $show = "SHOW CREATE $row[ROUTINE_TYPE] `$row[ROUTINE_NAME]`";

  $res2 = mysqli_query($conn, $show) or die($show.":".mysqli_error($conn));
  $row2 = mysqli_fetch_assoc($res2);
  mysqli_free_result($res2);

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";

  $sql_mode = "SET SESSION SQL_MODE = '$row2[sql_mode]'";

  $create = preg_replace('|^CREATE DEFINER=('.preg_quote($old_definer_sql).')|', "CREATE DEFINER=$new_definer_sql", $row2["Create ".ucwords(strtolower($row['ROUTINE_TYPE']))]);

  ddl($conn, $set);
  ddl($conn, $sql_mode);
  ddl($conn, $drop);
  ddl($conn, $create);
}


function ddl($conn, $query)
{
  global $verbose, $for_real;

  if ($verbose) {
    echo "Query: $query\n\n";
  }
  if ($for_real) {
    mysqli_query($conn, $query) or die(mysqli_error($conn));
  }
}

