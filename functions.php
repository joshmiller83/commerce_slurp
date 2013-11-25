<?php

// function that determines how many jobs will run today
// Also initializes job creation if no jobs exist
function commerce_slurp_jobs_today() {
  global $db;

  // How many jobs are there in the queue?
  $result = $db->query("SELECT COUNT(jid) FROM jobs")->fetch_row();
  $jobs_currently = array_pop($result);

  // How many jobs could there be in the queue?
  $result = $db->query("SELECT SUM(slots_for_today) FROM source_types WHERE slots_for_today > 0")->fetch_row();
  $jobs_possible = array_pop($result);

  // If we have less than possible, run our update to try to fill up the queue
  if ($jobs_currently < $jobs_possible) {
    echo "<p>Need to fill the Job Queue because we can run more jobs than we have queued.</p>";
    commerce_slurp_update();
  }

  // Final tally of jobs that can be run
  $result = $db->query("SELECT COUNT(jid) FROM jobs")->fetch_row();
  $jobs_currently = array_pop($result);

  // If we have no jobs, or can't run any more today, return zero
  if ($jobs_currently == 0 || $jobs_possible == 0) {
    echo "<p>No Jobs can be run because the queue is empty.</p>";
    return 0;
  }

  // else return the lowest number
  return ($jobs_currently < $jobs_possible)? $jobs_currently : $jobs_possible;
}

// function that will analyze source_types and fill job queue
function commerce_slurp_update() {
  global $db;
  $now = time();

  $sources = $db->query("SELECT * FROM source_types");
  while ($source = $sources->fetch_assoc()) {
    $refresh_time = strtotime("+".$source['refresh_every']." days",strtotime($source['last_refresh']));

    echo "It has been ".ceil(($now-strtotime($source['last_refresh']))/60)." minutes since ".$source['stid']." was refreshed<br />";
    echo $source['stid']." will refresh in ".ceil((($refresh_time - $now)/60)/60)." hours<br />";

    // days old
    if ($source['refresh_every']>1) {
      $days_left_until_refresh = ceil(((($refresh_time - $now)/60)/60)/24);
      $days_we_have_run = $source['refresh_every'] - $days_left_until_refresh;
      echo "Days left until refresh: $days_left_until_refresh<br />Days we have run: $days_we_have_run<br />";
    }

    // Complete Refresh
    if ($refresh_time < $now) {
      $db->query("UPDATE source_types SET
                 last_refresh=NOW(),
                 slots_for_today=".ceil($source['slots_allotment']/$source['refresh_every']).",
                 slots_until_refresh=".$source['slots_allotment']."
                 WHERE stid='".$source['stid']."'");
      echo "Refreshing the counters for ".$source['stid']."<br />";

    // Refresh daily counts
    } elseif ($source['slots_for_today'] == 0 && ($refresh_time-$now) > (86400*$days_we_have_run) && $source['refresh_every']>1) {

      $db->query("UPDATE source_types SET
                 last_refresh=last_refresh,
                 slots_for_today=".ceil($source['slots_allotment']/$source['refresh_every'])."
                 WHERE stid='".$source['stid']."'");
      echo "Refreshing the daily counters for ".$source['stid']."<br />";
    }

  }

  // Pull them again, this time, let's parse some multi jobs
  $sources = $db->query("SELECT stid,multi,slots_for_today FROM source_types WHERE slots_for_today > 0 AND multi=1");
  $first = true;
  $extension_ids = '';
  while ($source = $sources->fetch_assoc()) {
    $jobs = commerce_slurp_load_primary_source($source);
    while ($job = $jobs->fetch_assoc()) {

      // Add Source
      commerce_slurp_add_job_from_source($job,$source['multi']);

      /*// Add Extensions to List
      if ($source['multi'] > 0) {
        // Loop through each extension and add it to the list of possible jobs
        // we will then filter this list to make the oldest in this list go first
        $sql = "SELECT extension_id FROM extensions WHERE psid=".$job['psid'];
        $extensions = $db->query($sql);
        while ($extension = $extensions->fetch_assoc()) {

          if ($first) {
            $extension_ids = $extension['extension_id'];
            $first = false;
          } else {
            $extension_ids .= ', '.$extension['extension_id'];
          }
          /** /
        }
      }/**/
    }
  }
  // Add Non-multi elements to List
  $nonmulti_count = $db->query("SELECT stid,multi,slots_for_today FROM source_types WHERE slots_for_today > 0 AND multi != 1");
  $eids = array();
  while ($source = $nonmulti_count->fetch_assoc()) {
    // Primary Sources Non Multi
    $primary_jobs = commerce_slurp_load_primary_source($source);
    while ($job = $primary_jobs->fetch_assoc()) {
      $sql = "SELECT extension_id FROM extensions WHERE psid=".$job['psid'];
      $extensions = $db->query($sql);
      while ($extension = $extensions->fetch_assoc()) {
        $eids[] = $extension['extension_id'];
      }
    }
    // Secondary Sources in Extensions list (could be duplicates)
    $sql = "SELECT extension_id FROM extensions ORDER BY last_checked LIMIT 0,".$source['slots_for_today'];
    $extensions = $db->query($sql); krumo("Adding secondary extensions found by multi sources = ".$sql);
    while ($extension = $extensions->fetch_assoc()) {
      $eids[] = $extension['extension_id'];
    }
  }

  if (true) {
    krumo($eids);
    $sql = 'SELECT extension_id, psid FROM extensions WHERE extension_id IN ('.implode(",",$eids).') ORDER BY last_checked'; echo $sql.'<br />';
    $extensions = $db->query($sql); krumo($sql);
    while ($extension = $extensions->fetch_assoc()) {
      commerce_slurp_add_job_from_source(array(
          'psid' => $extension['psid'],
          'stid' => 'Module Page',
        ),0,$extension['extension_id']);
    }
  }/*/**/
}

function commerce_slurp_load_primary_source($source) {
  global $db;

  $sql = "SELECT psid,stid FROM primary_sources WHERE stid='".$source['stid']."'
          ORDER BY last_checked
          LIMIT 0, ".$source['slots_for_today']; echo 'commerce_slurp_load_primary_source() = '.$sql.'<br />';
  return $db->query($sql);
}

// function to add a job from a primary source
function commerce_slurp_add_job_from_source($job,$multi,$extension_id=NULL) {
  global $db;
  if ($multi == 1) {
    $result = $db->query("SELECT COUNT(psid) FROM jobs WHERE psid=".$job['psid'])->fetch_row();
    if (array_pop($result) == 0) {
      // add job
      $sql = "INSERT INTO jobs (stid, psid) VALUES ('".$job['stid']."', ".$job['psid'].")";
      $db->query($sql);
    }
    // update source
    $sql = "UPDATE primary_sources SET last_checked=NOW() WHERE psid=".$job['psid'];
    return $db->query($sql);
  } else {
    if ($extension_id == NULL){
      // Does this non-multi primary source exist in an updateable state in extensions
      $sql = "SELECT extension_id FROM extensions WHERE psid=".$job['psid']." LIMIT 1";
      $result = $db->query($sql);
      if (!is_object($result) || @$result->num_rows == 0) {
        // Nope, it's new. Add extension.
        $complete_source = $db->query("SELECT * FROM primary_sources WHERE psid=".$job['psid']." LIMIT 1")->fetch_assoc();
        $sql = "INSERT INTO extensions (url, psid, etid, esid) VALUES
                ('".$complete_source['url']."',
                ".$complete_source['psid'].",
                '".$complete_source['etid']."',
                'In-Development')";
        $db->query($sql);

        // Get ID
        $result2 = $db->query("SELECT extension_id FROM extensions WHERE psid=".$job['psid']." LIMIT 1");
        if (is_object($result2)) {
          $array = $result2->fetch_row();
          $extension_id = array_pop($array);
        } else {
          echo "Error. Couldn't retrieve extension_id for job.";
        }
      // otherwise we already have the eid
      } else {
        $array = $result->fetch_row();
        if ($array != NULL) {
          $extension_id = array_pop($array);
        } else {
          // the extension was deleted, but we have a primary source ... ?
          echo "WTF!! the extension was deleted, but we have a primary source ... ?";
          return;
        }
      }
    }
    $result = $db->query("SELECT COUNT(extension_id) FROM jobs WHERE extension_id=".$extension_id)->fetch_row();
    if (array_pop($result) == 0) {

      // Confirm that this extension has not been checked since the last time
      // we refreshed this kind of job

      // All "Modules" were loaded ...
      $sql = "SELECT last_refresh FROM source_types WHERE stid='".$job['stid']."' LIMIT 1";
      $result = $db->query($sql)->fetch_row();
      $last_refreshed = array_pop($result);

      // This "Module" was last updated ...
      $result = $db->query("SELECT last_checked FROM extensions WHERE extension_id=".$extension_id ." LIMIT 1")->fetch_row();
      $last_checked = array_pop($result);

      // If this module has never been checked or hasn't been checked since all
      // "Modules were updated...
      if ($last_checked == "0000-00-00 00:00:00" || strtotime($last_checked) < strtotime($last_refreshed)){
        // Let's file an extension update by adding a job
        if ($job['stid'] == "Search Result") $job['stid'] = 'Module Page';
        $sql = "INSERT INTO jobs (stid, extension_id) VALUES ('".$job['stid']."', ".$extension_id.")";
        $db->query($sql);
      }
    }

    // update source
    $sql = "UPDATE primary_sources SET last_checked=NOW() WHERE psid=".$job['psid'];
    return $db->query($sql);
  }
}

// Function that parses extension pages on drupal.org
function commerce_slurp_page($extension) {
  global $db, $client;
  $crawler = $client->request('GET', $extension['url']);

  // $data['name']
  $name = $crawler->filter("h1");
  if ($name->count() > 0) {
    $data['name'] = addslashes($name->text());
  }

  // $data['author']
  $author = $crawler->filter(".node .submitted a");
  if ($author->count() > 0) {
    $data['author'] = $author->text();
  } else {
    $data['author'] = 'unknown';
  }

  // Metadata
  $data['status'] = "In-Development"; // see below, based on release version
  $data['downloads'] = 0;
  $data['installs'] = 0;
  $data['bugs'] = $crawler->filter("div.issue-cockpit-1 div.issue-cockpit-totals a:first-child");
  $created = $crawler->filter("div.submitted em");
  if ($created->count() > 0) $data['created'] = $created->text();
  $data['last_modified'] = 0;
  $images = $crawler->filter("div.field-name-field-project-images div.field-item a");
  if ($images->count() > 0) $data['images'] = $images->attr("href");

  // Description
  $desc = $crawler->filter("div.field-name-body .field-item");
  if ($desc->count() > 0) $data['desc'] = mb_convert_encoding(utf8_decode($desc->html()), "HTML-ENTITIES", 'UTF-8');

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
        // Transliterate any UTF8 weird things.
        $object = iconv("UTF-8", "ISO-8859-1//TRANSLIT", utf8_decode($object));
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
        $data[$label] = addslashes(trim($data[$label]));
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

function commerce_slurp_extension_update ($eid,$extension_updated) {
  global $db;

  // Convert dates
  if ($extension_updated['created'] > 0 ) {
    $extension_updated['created'] = date("Y-m-d H:i:s",$extension_updated['created']);
  }
  if ($extension_updated['last_modified'] > 0 ) {
    $extension_updated['last_modified'] = date("Y-m-d H:i:s",$extension_updated['last_modified']);
  } else {
    $extension_updated['last_modified'] = $extension_updated['created'];
  }

  if (!array_key_exists("images",$extension_updated)) $extension_updated['images'] = "";
  // Update extension
  $sql = "UPDATE extensions SET
          `name`='".$extension_updated['name']."',
          `author`='".$extension_updated['author']."',
          `esid`='".$extension_updated['status']."',
          `downloads`=".$extension_updated['downloads'].",
          `installs`=".$extension_updated['installs'].",
          `bugs`=".$extension_updated['bugs'].",
          `created`='".$extension_updated['created']."',
          `modified`='".$extension_updated['last_modified']."',
          `images`='".$extension_updated['images']."',
          `description`='".$extension_updated['desc']."',
          `last_checked`=NOW()
          WHERE extension_id=".$eid;
  $update_result = $db->query($sql);
  if ($update_result != false) {
    echo "Updated one extension.";
  } else {
    echo "Failed to update:<br />";
    echo $sql."<br />";
    echo $db->error;
    return false;
  }
  krumo($extension_updated);

  // Move on to the next one...
  return true;
}

function commerce_slurp_run_next_job() {
  global $db, $client;
  $job = $db->query("SELECT jobs.*, source_types.*
                     FROM jobs, source_types
                     WHERE source_types.stid=jobs.stid
                     ORDER BY jobs.weight DESC, jobs.jid ASC
                     LIMIT 1")->fetch_assoc();
  echo "<Br /><br /><hr><br />Running a Job<br />";
  krumo($job);
  // if we can't run this kind of job..
  if ($job['slots_for_today'] == 0) {
    // reduce the weight of the job
    $sql = "UPDATE jobs SET weight=weight-1 WHERE jid=".$job['jid'];
    $db->query($sql); //krumo($sql);
    return true;

  // Else we can run this job
  } else {
    // Process Extension Update Jobs
    if ($job['extension_id'] > 0) {

      // Pull current extension info
      $sql = "SELECT * FROM extensions WHERE extension_id=".$job['extension_id']." LIMIT 1";
      $extension = $db->query($sql)->fetch_assoc(); //krumo($sql);

      // Extract most updated info
      // TODO: Pull local XML feed info if it's been less than a month
      commerce_slurp_extension_update($job['extension_id'],commerce_slurp_page($extension));

      // Remove job, update counter
      commerce_slurp_job_complete($job['jid'],$job['stid']);

      // move on to the next one...
      return true;

    // Assume we have only primary-sources left...
    } else {
      // Pull current source info
      $source = $db->query("SELECT * FROM primary_sources WHERE psid=".$job['psid']." LIMIT 1")->fetch_assoc();

      // Parse Page
      $extensions_to_add = commerce_slurp_searchresult($source);
      echo "Found ".count($extensions_to_add)." ".$source['etid']." ".$job['stid'];
      $inserted = 0;
      //krumo($extensions_to_add);

      // Add Extensions found
      if (count($extensions_to_add) > 0) {
        $sql = "INSERT INTO extensions (name, author, url, psid, etid, esid) VALUES";
        $first = true;
        $inserted_array = array();
        foreach ($extensions_to_add as $data) {
          $test = "SELECT * FROM extensions WHERE url='".$data['url']."' OR `name`='".trim(addslashes($data['name']))."'";
          $current_exist = $db->query($test);
          if ($current_exist->num_rows == 0) {
            $inserted++;
            $inserted_array[] = $data;
            if (!$first) $sql .= ', ';
            // Add minimal extension record
            $sql .= "('".trim(addslashes($data['name']))."',
                    '".trim(addslashes($data['author']))."',
                    '".trim($data['url'])."',
                    ".$job['psid'].",
                    '".$source['etid']."',
                    'In-Development')";
            $first = false;
          }
        }
        $db->query($sql); //echo $sql;
      }
      if ($inserted==0) {
        echo "<em>All of the ".$source['etid']." ".$job['stid'] . " already exist in database!</em><br />";
      } else if ($inserted != count($extensions_to_add)) {
        echo "Found <strong>$inserted</strong> new ".$source['etid']." ".$job['stid']."<br />";
        krumo($inserted_array);
      } else {
        echo "We added <strong>".count($extensions_to_add)."</strong> ".$source['etid']." ".$job['stid'] . " to the database.<br />";
        krumo($inserted_array);
      }
      // Move on to the next one...
      // Remove job, update counter
      commerce_slurp_job_complete($job['jid'],$job['stid']);
      return true;
    }
  }
  return false;
}

// function to parse search results
function commerce_slurp_searchresult($source) {

  global $db, $client;

  // Pull HTML
  $crawler = $client->request('GET', $source['url']);
  $extensions_to_add = array();
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
  return $extensions_to_add;
}

// remove the job, decrement the source type counter
function commerce_slurp_job_complete($jid, $stid) {
  global $db;

  // remove job
  $sql = "DELETE FROM jobs WHERE jid=".$jid;
  $db->query($sql);

  // decrement source type counters
  $sql = "UPDATE `source_types` SET
          `slots_for_today`=`slots_for_today`-1,
          `slots_until_refresh`=`slots_until_refresh`-1,
          `last_refresh`=`last_refresh`
          WHERE `stid`='".$stid."'";
  $db->query($sql);
}
