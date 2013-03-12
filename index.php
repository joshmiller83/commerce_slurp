<?php
/**
 * Initialize various submodules
 */
require_once 'libraries/krumo/class.krumo.php';
require_once 'libraries/goutte/goutte.phar';
use Goutte\Client;
$client = new Client();

/**
 * Test pull in data
 */
$crawler = $client->request('GET', 'http://www.wikipedia.org/');

var_dump($crawler);

$nodes = $crawler->filter('#mw-content-text p');
if ($nodes->count())
{
  die(sprintf("P: %s\n", $nodes->text()));
} else {
  echo "Nope";
}