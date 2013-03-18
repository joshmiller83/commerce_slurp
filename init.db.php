<?php
// Connect and check connection
$db=mysqli_connect("localhost","root","root","commerce_slurp");
if (mysqli_connect_errno($db)) echo "Failed to connect to MySQL: " . mysqli_connect_error();

// Initialize extension types
if ($db->query("SHOW TABLES LIKE 'extension_types'")->fetch_row() == NULL) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`extension_types` (
    `etid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    PRIMARY KEY (  `etid` )
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
  $db->query("INSERT INTO  `commerce_slurp`.`extension_types` (`etid`) VALUES ('Module'), ('Theme'), ('Sandbox'), ('Distribution')");
}

// Initialize extension status
/* 1) Recommended (is not sandbox, has beta, rc or stable release)
 * 2) Active (has any recommended release)
 * 3) In-Development (default)
 * 4) Not Recommended (Obsolete, Unsupported)
 */
if ($db->query("SHOW TABLES LIKE 'extension_status'")->fetch_row() == NULL) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`extension_status` (
    `esid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    PRIMARY KEY (  `esid` )
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
  $db->query("INSERT INTO  `commerce_slurp`.`extension_status` (`esid`) VALUES ('Recommended'), ('Active'), ('In-Development'), ('Not Recommended')");
}

/* Initialize source types
 * The source types regulate how the job_queue gets filled. Only source_types
 * that should be refreshed can be refreshed. Additionally, the slots number
 * gets decremented for every job run per day and in total. Re-populating the
 * job_queue with these kinds of jobs will only happen if the following
 * conditions are met:
 *   * slot number is zero
 *   * the difference between today & last refresh is greater than
 *   * the difference between today & strtotime(+[refresh_every] days, last_refresh)
 */
if ($db->query("SHOW TABLES LIKE 'source_types'")->fetch_row() == NULL) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`source_types` (
    `stid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `multi` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '1' COMMENT  'Whether this source is multi-valued',
    `slots_for_today` INT NOT NULL COMMENT 'Slots counter for one 24 hour period, only used for multi-day refreshes.',
    `slots_until_refresh` INT NOT NULL COMMENT 'How many times can this job be executed until next refresh',
    `slots_allotment` INT NOT NULL COMMENT 'We will load only this many jobs per X days',
    `refresh_every` FLOAT UNSIGNED NOT NULL COMMENT '1 means this gets reset every day, 2 means this gets reset every 48 hours. Only source types with 0 slots can be refreshed.',
    `last_refresh` TIMESTAMP NOT NULL ,
    PRIMARY KEY (  `stid` )
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
  /**
   * Search results get refreshed every week, running approx. 10/day
   * Each RSS file can be processed daily
   * 50 Modules Pages can be updated daily, running through all of them every 14 days 
   */
  $db->query("INSERT INTO  `commerce_slurp`.`source_types`
             (`stid`,`multi`,`slots_for_today`,`slots_until_refresh`,`slots_allotment`,`refresh_every`) VALUES
             ('Search Result',1,10,70,70,7),
             ('RSS',1,70,70,70,1),
             ('Module Page',0,50,700,700,14)");
}

// Initialize primary sources

if ($db->query("SHOW TABLES LIKE 'primary_sources'")->fetch_row() == NULL) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`primary_sources` (
    `psid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `url` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `etid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Relationship to extension_type' ,
    `stid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Relationship to source_type' ,
    `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
    `last_checked` TIMESTAMP NOT NULL ,
    FOREIGN KEY ( `etid` ) REFERENCES extension_types(etid) ,
    FOREIGN KEY ( `stid` ) REFERENCES source_types(stid) 
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
  require_once 'primary_sources.php';
}

// Initialize extensions repository
if ($db->query("SHOW TABLES LIKE 'extensions'")->fetch_row() == NULL) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`extensions` (
    `extension_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `name` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `author` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `url` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `status` VARCHAR( 45 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `images` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `description` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
    `downloads` INT UNSIGNED NOT NULL ,
    `installs` INT UNSIGNED NOT NULL ,
    `bugs` INT UNSIGNED NOT NULL ,
    `esid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Status' ,
    `etid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Type' ,
    `psid` INT UNSIGNED NOT NULL COMMENT 'Source' ,
    `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
    `modified` TIMESTAMP NOT NULL ,
    `last_checked` TIMESTAMP NOT NULL ,
    FOREIGN KEY ( `esid` ) REFERENCES extension_status(esid) ,
    FOREIGN KEY ( `etid` ) REFERENCES extension_types(etid) ,
    FOREIGN KEY ( `psid` ) REFERENCES primary_sources(psid) 
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
}


// Initialize jobs
if ($db->query("SHOW TABLES LIKE 'jobs'")->fetch_row() == NULL) {
  $db->query(
   "CREATE TABLE  `commerce_slurp`.`jobs` (
    `jid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `psid` INT UNSIGNED NULL DEFAULT NULL ,
    `extension_id` INT UNSIGNED NULL DEFAULT NULL,
    `weight` INT NOT NULL,
    `stid` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Relationship to source_type'
    ) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
}