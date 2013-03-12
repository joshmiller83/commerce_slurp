<?php
/**
 * Initialize various submodules
 */
require_once 'libraries/krumo/class.krumo.php';
require_once 'libraries/goutte/goutte.phar';
use Goutte\Client;
$client = new Client();

// Connect and check connection
$db=mysqli_connect("localhost","root","root","commerce_slurp");
if (mysqli_connect_errno($db)) echo "Failed to connect to MySQL: " . mysqli_connect_error();

// Initialize module types
if (!$db->query("SHOW TABLES LIKE 'module_types'")->numrows) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`module_types` (
    `tid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    PRIMARY KEY (  `tid` )
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
  $db->query("INSERT INTO  `commerce_slurp`.`module_types` (`tid`) VALUES ('Module'), ('Theme'), ('Sandbox'), ('Distribution')");
}

// Initialize primary sources
if (!$db->query("SHOW TABLES LIKE 'primary_sources'")->numrows) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`primary_sources` (
    `psid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `url` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `multi` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '1' COMMENT  'Whether this source is multi-valued',
    `tid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Relationship to module_type' ,
    `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
    `last_checked` TIMESTAMP NOT NULL ,
    FOREIGN KEY ( `tid` ) REFERENCES module_types(tid) ON DELETE CASCADE 
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
  require_once 'primary_sources.php';
}

// Initialize secondary sources
if (!$db->query("SHOW TABLES LIKE 'extensions'")->numrows) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`extensions` (
    `extension_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `url` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `tid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Relationship to module_type' ,
    `psid` INT UNSIGNED NOT NULL COMMENT 'Relationship to primary source' ,
    `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
    `last_checked` TIMESTAMP NOT NULL ,
    FOREIGN KEY ( `tid` ) REFERENCES module_types(tid) ON DELETE CASCADE 
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
}
/**
 * Test pull in data
 *
$crawler = $client->request('GET', 'http://en.wikipedia.org/wiki/Drupal');
echo $crawler->filter("p")->text();*/