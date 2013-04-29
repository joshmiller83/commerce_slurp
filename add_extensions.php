<?php

//Initialize various submodules
require_once 'libraries/krumo/class.krumo.php';
require_once 'libraries/goutte/goutte.phar';

// Setup Databases
require_once 'init.db.php';
require_once 'functions.php';

$sql = "SELECT extension_id FROM extensions WHERE last_checked=0";
$extensions = $db->query($sql);
while ($extension = $extensions->fetch_assoc()) {
  $sql2 = "INSERT INTO jobs (extension_id, stid) VALUES ('".$extension['extension_id']."', 'Module Page')";
  $db->query($sql2);
}