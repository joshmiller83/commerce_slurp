<?php
// intialize krumo
require_once 'libraries/krumo/class.krumo.php';

/*
// loop through each line ...
$row = 1;
if (($handle = fopen("module_export.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        echo "<p> $num fields in line $row: <br /></p>\n";
        $row++;
        for ($c=0; $c < $num; $c++) {
            echo $data[$c] . "<br />\n";
        }
    }
    fclose($handle);
}
// */

function csv_to_array($filename='', $header=NULL, $delimiter=',')
{
    if(!file_exists($filename) || !is_readable($filename))
        return FALSE;

    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE)
    {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
        {
            if(!$header)
                $header = $row;
            else
                $data[] = array_combine($header, $row);
        }
        fclose($handle);
    }
    return $data;
}

$module_list = csv_to_array('module_export2.csv',array("name","type","nid","url"));
$last_key = array_pop(array_keys($module_list));
$duplicates = array();
$somethingswrong = array();
$i = 0;
$max = 1000;

foreach ($module_list as $key => $data) {
  $isit_duplicate = false;
  $before = $module_list[$key - 1];
  $after = $module_list[$key + 1];
  if ($key == 0) $before = NULL;
  if ($key == $last_key) $after = NULL;
  $isit_duplicate = compare_list($data,$before,$after);
  if ($isit_duplicate != false) {
    $duplicates[] = $isit_duplicate;
  }
  if ($data['type'] == "Sandbox" && stristr($data['url'],'/http')) {
    $somethingswrong[] = $data;
  }
  $i++;
  if ($i > $max) break;
}

// function to compare the modules to each other
function compare_list($current,$before=NULL,$after=NULL) {
  //krumo($current);
  //krumo($before);
  //krumo($after);
  if ($before != NULL){
    // before has same title
    if ($current['name'] == $before['name']) {
      //krumo("the title matches before.");
      return array($current, $before);
    }
  }
  if ($after != NULL){
    // after has same title
    if ($current['name'] == $after['name']) {
      //krumo("the title matches after.");
      return array($current, $after);
    }
  }
  //krumo("title doesn't match.");
  return false;
}

krumo($duplicates);
krumo($somethingswrong);

?><table>
  <?php foreach ($duplicates as $row) {
?><tr><td><strong><a href="http://drupalcommerce.org/node/<?php echo $row[0]['nid']; ?>/edit" target="_blank"><?php echo $row[0]['name']; ?></a></strong><br /><?php echo $row[0]['type']; ?> | <?php echo $row[0]['url']; ?></td>
<td><strong><a href="http://drupalcommerce.org/node/<?php echo $row[1]['nid']; ?>/edit" target="_blank"><?php echo $row[1]['name']; ?></a></strong><br /><?php echo $row[1]['type']; ?> | <?php echo $row[1]['url']; ?></td></tr><?php
    } ?>
</table>
?>
