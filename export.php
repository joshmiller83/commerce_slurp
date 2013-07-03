<?php

//Initialize various submodules
require_once 'libraries/krumo/class.krumo.php';
require_once 'libraries/goutte/goutte.phar';

// Setup Databases
require_once 'init.db.php';
require_once 'functions.php';

// Pull Modules
$sql = "
	SELECT
	  `etid` as `module_pg_type`,
	  `name`,
	  `description`,
	  `url`,
	  `author`,
	  `esid` as `status`,
	  DATE_FORMAT(`modified`, '%Y-%m-%dT%TZ') as `modified`,
	  `downloads`,
	  `installs`,
	  `bugs`,
	  DATE_FORMAT(`created`, '%Y-%m-%dT%TZ') as `created`,
	  `images`
	FROM
	  extensions
	WHERE
	  `modified` > 0";

$extensions = $db->query($sql);

$columns = array(
	"module_pg_type",
	"name",
	"description",
	"url",
	"author",
	"status",
	"modified",
	"downloads",
	"installs",
	"bugs",
	"created",
	"images");

$fp = fopen('export-'.date('Ymd').'.csv', 'wb');
fputcsv($fp, $columns);

while ($extension = $extensions->fetch_assoc()) {
	foreach ($extension as $id => $field) {
		$extension[$id] = trim($field);
	}
	if ($extension['module_pg_type'] != "Sandbox") {
		$extension['url'] = str_replace('http://drupal.org/project/', '', $extension['url']);
	}
  fputcsv($fp, $extension);
}
fclose($fp);
// Sandbox,commerce_alipay,Commerce Alipay,http://drupal.org/sandbox/csunny/1470020,csunny,In Development,2012-03-06T08:16:00,0,0,0,2012-03-06T08:16:00,0
?>
<a href="export-<?php echo date('Ymd'); ?>.csv">Download Export</a>






























