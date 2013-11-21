<?php
/**
 * Variables
 */
date_default_timezone_set("America/Indiana/Indianapolis");
$seconds_between_cron = 15*60; // 15 minutes
$speed_limit = 6;
$now = time();
$start_time = strtotime("6:00am");
$end = "11:00 am"; // we use this as output
$end_time = strtotime($end);
?><html>
  <head>
    <meta http-equiv="refresh" content="<?php echo $seconds_between_cron; ?>">
    <link rel="icon" type="image/gif" href="favicon.gif" />
  </head>
  <body>
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

// Can only run during set period...
echo "<pre>Start: <strong>".date("g:ia l, F jS",$now)."</strong></pre><br />";

if ($now >= $start_time && $now <= $end_time){
  $cron_runs_left = ($end_time - $now) / $seconds_between_cron;
  $jobs_left_for_today = commerce_slurp_jobs_today();
  if ($jobs_left_for_today > 0) {
    $lets_run_this_many = ceil($jobs_left_for_today/$cron_runs_left);
    echo "<br /><hr><br />Jobs left for today: $jobs_left_for_today <br />
    Cron runs left: ".ceil($cron_runs_left)." <br />
    Which means we can run this many on this cron: $lets_run_this_many (or $speed_limit max)<br /><br /><hr><br />";

    // Slow down there buddy, we have a speed limit
    $lets_run_this_many = ($lets_run_this_many > $speed_limit)? $speed_limit : $lets_run_this_many;

    $return = true;
    while ($return && $lets_run_this_many > 0) {
      $return = commerce_slurp_run_next_job();
      $lets_run_this_many--;
    }
  } else {
    echo "No jobs were in the queue, all counters were zero.<br />";
  }
} else {
  echo "<br /><br /><pre>It's after ".$end.", so we'll just make sure the job queue is full.</pre><br /><hr><br />";
  commerce_slurp_update();
}
?>

  </body>
</html>
