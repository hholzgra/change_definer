<?php

// ===== configration part starts =====

$for_real = true; // execute DDL statements
$verbose  = true; // show DDL statements 

$db_host = "127.0.0.1";
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


function ddl($query)
{
  global $verbose, $for_real;

  if ($verbose) {
    echo "Query: $query\n\n";
  }
  if ($for_real) {
    mysql_query($query) or die(mysql_error());
  }
}

mysql_connect($db_host, $db_user, $db_pass) or die("can't connect to mysql");

// modify views

$get_views = "SELECT * FROM INFORMATION_SCHEMA.VIEWS WHERE DEFINER = '$old_definer'";

$res = mysql_query($get_views) or die(mysql_error());
while($row = mysql_fetch_assoc($res)) {
  echo "modifying view $row[TABLE_SCHEMA].$row[TABLE_NAME]\n";

  mysql_select_db($row["TABLE_SCHEMA"]);

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";
  $alter = "ALTER DEFINER=$new_definer_sql VIEW `$row[TABLE_NAME]` AS $row[VIEW_DEFINITION]";
  
  ddl($set);
  ddl($alter);
}
mysql_free_result($res);

// modify triggers

$get_triggers = "SELECT * FROM INFORMATION_SCHEMA.TRIGGERS WHERE DEFINER = '$old_definer'";

$res = mysql_query($get_triggers) or die(mysql_error());
while($row = mysql_fetch_assoc($res)) {
  echo "modifying trigger $row[TRIGGER_SCHEMA].$row[TRIGGER_NAME] on table $row[EVENT_OBJECT_SCHEMA].$row[EVENT_OBJECT_TABLE]\n";

  mysql_select_db($row["TRIGGER_SCHEMA"]);

  $lock = "LOCK TABLES `$row[EVENT_OBJECT_TABLE]` WRITE";
  $unlock = "UNLOCK TABLES";

  $drop = "DROP TRIGGER `$row[TRIGGER_NAME]`";

  $show = "SHOW CREATE TRIGGER `$row[TRIGGER_NAME]`";

  $res2 = mysql_query($show) or die($show.":".mysql_error());
  $row2 = mysql_fetch_assoc($res2);
  mysql_free_result($res2);

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";
  $sql_mode = "SET SESSION SQL_MODE = '$row2[sql_mode]'";

  $create = preg_replace('|^CREATE DEFINER=('.preg_quote($old_definer_sql).')|', "CREATE DEFINER=$new_definer_sql", $row2["SQL Original Statement"]);

  ddl($set);
  ddl($sql_mode);
  ddl($lock);
  ddl($drop);
  ddl($create);
  ddl($unlock);
}  

// modify events

$get_events = "SELECT * FROM INFORMATION_SCHEMA.EVENTS WHERE DEFINER = '$old_definer'";

$res = mysql_query($get_events) or die(mysql_error());
while($row = mysql_fetch_assoc($res)) {
  echo "modifying event $row[EVENT_SCHEMA].$row[EVENT_NAME]\n";

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";

  // one extra option is needed after EVENT, ON COMPLETION is the simplest so we take this
  $alter = "ALTER DEFINER=$new_definer_sql EVENT `$row[EVENT_SCHEMA]`.`$row[EVENT_NAME]` ON COMPLETION $row[ON_COMPLETION]";

  ddl($set);
  ddl($alter);
}

// modify procedures and functions

$get_procedures = "SELECT * FROM INFORMATION_SCHEMA.ROUTINES WHERE DEFINER = '$old_definer'";

$res = mysql_query($get_procedures) or die(mysql_error());
while($row = mysql_fetch_assoc($res)) {
  echo "modifying ".strtolower($row['ROUTINE_TYPE'])." $row[ROUTINE_SCHEMA].$row[ROUTINE_NAME]\n";

  mysql_select_db($row["ROUTINE_SCHEMA"]);

  $drop = "DROP $row[ROUTINE_TYPE] `$row[ROUTINE_NAME]`";

  $show = "SHOW CREATE $row[ROUTINE_TYPE] `$row[ROUTINE_NAME]`";

  $res2 = mysql_query($show) or die($show.":".mysql_error());
  $row2 = mysql_fetch_assoc($res2);
  mysql_free_result($res2);

  $set = "SET SESSION character_set_client='$row[CHARACTER_SET_CLIENT]', collation_connection='$row[COLLATION_CONNECTION]'";

  $sql_mode = "SET SESSION SQL_MODE = '$row2[sql_mode]'";

  $create = preg_replace('|^CREATE DEFINER=('.preg_quote($old_definer_sql).')|', "CREATE DEFINER=$new_definer_sql", $row2["Create ".ucwords(strtolower($row['ROUTINE_TYPE']))]);

  ddl($set);
  ddl($sql_mode);
  ddl($drop);
  ddl($create);
}