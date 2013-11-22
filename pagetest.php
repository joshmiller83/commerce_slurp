<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php

//Initialize various submodules
require_once 'libraries/krumo/class.krumo.php';
require_once 'libraries/goutte/goutte.phar';

// Setup Databases
require_once 'init.db.php';
require_once 'functions.php';

// Test this page:
use Goutte\Client;
$client = new Client();

$testpg = "modpage-7-test2.html";
$leftpart = "http://localhost:8888/commerce_slurp/testpgs/";

$extension = array(
  'url' => $leftpart.$testpg,
  //'etid' => 'Sandbox',
  'etid' => 'Module',
  //'etid' => 'Theme',
);

echo "starting <a href=\"".$leftpart.$testpg."\">the test</a>...";
krumo(commerce_slurp_page($extension));
