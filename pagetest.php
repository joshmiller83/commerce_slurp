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

//echo "starting <a href=\"".$leftpart.$testpg."\">the test</a>...";
//krumo(commerce_slurp_page($extension));

// ````````````````````````````````````````````````
// Test search results

$testsearch = "searchpage-7-test4.html";

$source = array(
  'url' => $leftpart.$testsearch,
  //'etid' => 'Module',
  'etid' => 'Sandbox',
  //'etid' => 'Theme',
  //'etid' => 'Distribution',
);


echo "starting <a href=\"".$leftpart.$testsearch."\">the test</a>...";
krumo(commerce_slurp_searchresult($source));

function commerce_slurp_searchresult($source) {
  global $db, $client;

  // Pull HTML
  $crawler = $client->request('GET', $source['url']);
  $extensions_to_add = array();

  switch ($source['etid']) {
    case "Module":
    case "Theme":
    case "Distribution":
    case "Sandbox":
      $listings = $crawler->filter("ol.search-results li.search-result");
      if ($listings->count()) {
        for ($i = 0; $i < $listings->count(); ++$i) {
          $url = $listings->eq($i)->filter('h3.title a')->attr('href');
          // Sigh, we should do something more here.
          if (   ($source['etid']=='Module' && (stristr($url,"commerce_") || stristr($url,"_commerce")))
              || ($source['etid']=='Theme'
                  || $source['etid']=='Distribution'
                  || $source['etid']=='Sandbox')) {
            $extensions_to_add[] = array(
              "name" => $listings->eq($i)->filter("h3.title")->text(),
              "url" => $url,
              "author" => $listings->eq($i)->filter("p.submitted a.username")->text(),
            );
          }
        }
      }
      break;
  }
  return $extensions_to_add;
}
