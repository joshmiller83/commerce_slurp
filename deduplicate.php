<?php

//Initialize various submodules
require_once 'libraries/krumo/class.krumo.php';
require_once 'libraries/goutte/goutte.phar';

// Setup Databases
require_once 'init.db.php';
require_once 'functions.php';


// Setup Page Scraper Class
use Goutte\Client;
$client = new Client();

$sql = "SELECT url, extension_id, `name`, etid FROM `extensions`";
$extensions = $db->query($sql);
$collate = array();
$i = 0;
while ($extension = $extensions->fetch_assoc()) {
  $collate[$extension['url']][] = $extension['extension_id'];
  if (strlen($extension['url'])<5 || strlen($extension['name'])<3) {
    echo "Found weird extension";
    krumo($extension);
    commerce_slurp_extension_update($extension['extension_id'],commerce_slurp_page($extension));
  }
  $i++;
}
echo "Modules found: ".$i;
echo "<br /><br />Single Modules found: ".count($collate);
$remove = array();
foreach ($collate as $single_extension) {
  if (count($single_extension) > 1) {
    $first = true;
    foreach ($single_extension as $duplicate) {
      if ($first) {
        $first = false;
      } else {
        $remove[] = $duplicate;
        $sql = "DELETE FROM extensions WHERE extension_id=".$duplicate;
        $db->query($sql);
      }
    }
  }
}
echo "<br /><br />Duplicates found and removed: ".count($remove);
