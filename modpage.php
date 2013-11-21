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

$testpg = "modpage-7-test1.html";
$leftpart = "http://localhost:8888/commerce_slurp/";

$extension = array(
  'url' => $leftpart.$testpg,
  'etid' => 'Module',
);

echo "starting <a href=\"".$leftpart.$testpg."\">the test</a>...";
krumo(commerce_slurp_page_test($extension));

// Function that parses extension pages on drupal.org
function commerce_slurp_page_test($extension) {
  global $db, $client;
  $crawler = $client->request('GET', $extension['url']);

  krumo($crawler->count());

  // $data['name']
  $name = $crawler->filter("h1#page-subtitle");
  krumo($name->count());
  if ($name->count() > 0) {
    $data['name'] = addslashes($name->text());
  } else {
    $data['name'] = 'unknown';
  }

  // $data['author']
  $author = $crawler->filter(".node .submitted a");
  if ($author->count() > 0) {
    $data['author'] = $author->text();
  } else {
    $data['author'] = 'unknown';
  }
  $data['status'] = "In-Development"; // see below, based on release version
  $data['downloads'] = 0;
  $data['installs'] = 0;
  $data['bugs'] = $crawler->filter("div.issue-cockpit-bug div.issue-cockpit-totals a");
  $created = $crawler->filter("div.submitted em");
  if ($created->count() > 0) $data['created'] = $created->text();
  $data['last_modified'] = 0;
  $images = $crawler->filter("div.field-field-project-images div.field-item a");
  if ($images->count() > 0) $data['images'] = $images->attr("href");
  $desc = $crawler->filter("div.node-content");
  if ($desc->count() > 0) $data['desc'] = $desc->text();
  // Targeting on UL that has lots of data...
  // find maintenance & development status
  $maintenance_status = "";
  $dev_status = "";
  $li_count = $crawler->filter(".project-info ul li")->count();
  if ($li_count > 0) {
    for ($i = 0; $i < $li_count; ++$i) {
      $li_text = $crawler->filter(".project-info ul li")->eq($i);
      if ($li_text->count() > 0) {
        $text = $li_text->text();
        if (stristr($text,"Maintenance")){
          $maintenance_status = substr($text,20);
        }
        if (stristr($text,"Development")) {
          $dev_status = substr($text,20);
        }
        if (stristr($text,"Reported")) {
          $array = explode(" ",$text);
          $data['installs'] = intval(str_replace(",","",$array[2]));
        }
        if (stristr($text,"Downloads")) {
          $array = explode(" ",$text);
          $data['downloads'] = intval(str_replace(",","",$array[1]));
        }
        if (stristr($text,"Last")) {
          $data['last_modified'] = strtotime(substr($text,15));
        }

      }
    }
  }
  foreach ($data as $label=>$object) {
    switch($label) {
      case "name":
        if ($extension['etid'] == "Sandbox") {
          $title = explode(":",$object);
          if (count($title) > 1) {
            array_shift($title);
            $title = implode(" ",$title);
            $data[$label] = $title;
          }
        } else {
          $data[$label] = $object;
        }
        break;
      case "status":
        // Type of release (beta, rc, alpha, stable)
        $rec_release = $crawler->filter("div.download-table-ok tr.views-row-first td.views-field-version a");
        if ($rec_release->count() > 0){
          $rec_release = $rec_release->first()->text();
          $version_parts = explode('-', $rec_release);
          if (!empty($version_parts)) {
            $version_parts_count = count($version_parts);
            if (count($version_parts) > 2) {
              $last_version_part = $version_parts[$version_parts_count - 1];
              foreach (array('beta', 'alpha', 'rc') as $version_type) {
                if (stripos($last_version_part, $version_type) !== FALSE) {
                  $rec_release = $version_type;
                  break;
                }
              }
            }
            else {
             $rec_release = 'stable';
            }
          }
        }

        $data[$label] = "In-Development";
        if ($extension['etid'] != "Sandbox" && ($rec_release == "beta" || $rec_release == "rc" || $rec_release == "stable")) {
          $data[$label] = "Recommended";
        } elseif ($extension['etid'] != "Sandbox" && !empty($rec_release)) {
          $data[$label] = "Active";
        } elseif ($dev_status=="Obsolete" || $maintenance_status == "Unsupported") {
          $data[$label] = "Not Recommended";
        }
        break;
      case "created":
        if ($object) {
          $created = str_ireplace(' at ', ' ', $object);
          if (!empty($created)) {
            $created = date_create($created);
            $data[$label] = (int) $created->format("U");
          }
        } else {
          $data[$label] = $data['modified'];
        }
        break;
      case "bugs":
        $data[$label] = ($object->count() != 0) ? intval(str_ireplace(' open', '', $object->first()->text())) : 0;
        break;
      case "desc":
        $desc = "";
        // get paragraphs
        $paragraphs = preg_split('/$\R?^/m', $object, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($paragraphs as $p) {

          // go through each sentence, avoid if it's the disclaimer or headline, let it iterate to another paragraph
          preg_match_all('/([^\.\?!]+[\.\?!])/', $p, $sentences);

          if (count($sentences[0]) > 0) {
            foreach ($sentences[0] as $sentence) {
              // Avoid "obligatory" text and common header text
              if (!stristr(@$sentence,"Recommended releases") &&
                  !stristr(@$sentence,"This is a sandbox project") &&
                  !stristr(@$sentence,"Git repository") &&
                  strncmp(strtolower($sentence), "overview", 8) !== 0 &&
                  strncmp(strtolower($sentence), "description", 11) !== 0 &&
                  !stristr(@$sentence,"Experimental Project") &&
                  strlen($desc) < 350){
                $desc .= @$sentence . " ";
              }
              // Stop if we are at our maximum
              if (strlen($desc) > 350) break;

              // Stop if we have reached the download section
              if (stristr(@$sentence,"Recommended releases") || trim(@$sentence)=="7.") break;
            }
          }
          // Stop if we are at our maximum
          if (strlen($desc) > 350) break;

          // Stop if we have reached the download section
          if (stristr(@$sentence,"Recommended releases") || trim(@$sentence)=="7.") break;
        }

        $data[$label] = addslashes(trim($desc));
        break;
      case "last_modified":
        // determine last commit (usually the most recent change is code to dev)
        $last_commit = NULL;
        $last_commit_obj = $crawler->filter("div.vc-commit-times");
        // Does this exist? If so, let's create a date...
        if ($last_commit_obj->count() > 0) {
          $data2 = $crawler->filter("div.vc-commit-times")->text();
          $commits = preg_split('@\,\s*@', $data2, 2);
          foreach ($commits as $commit_i => $commit) {
            if (stripos($commit, 'last') !== FALSE) {
              $last_commit = preg_replace('@^.*\:\s*@', '', $commit);
              if (!empty($last_commit)) {
                $last_commit = date_create($last_commit);
              }
              break;
            }
          }

          // replace last_modified with last commit, if we found it.
          if ($object != 0) {
            if ($last_commit->format("U") > $data[$label]) {
              $data[$label] = $last_commit->format("U");
            }
          } else {
            $data[$label] = $last_commit->format("U");
          }
        }
        break;
      default: break;
    }
  }

  return $data;
}
