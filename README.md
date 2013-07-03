Commerce Slurp
==============

Commerce Slurp is a PHP page scraper that finds and builds a database of all Drupal Commerce Modules, Sandboxes, Themes, and Distributions. This is designed to be run from a local machine on a daily basis. In practice, I run this for about a week every three or four weeks. The idea is to not "slam" drupal.org for information, but to "slurp" the search results and project pages one or two at a time. 

Install
-------

The initial database is actually in a flat file and gets built the first time it's run. No guarantees that this works, but it should<sup>(tm)</sup>. To install this, follow these steps:

1. Simply download the package into a live "site" folder on your local machine 
2. Create a database and change the 3rd line in "./init.db.php" to your own credentials.
3. Load up "index.php" in your favorite browser to activate the database.
4. You may want to modify the start/end time (meant to be 6am - 5pm to "mask" the crawling nature of the program)
5. You may also modify the "cron" which simply changes a "meta" refresh tag that loads index.php

The initialized database will start to create a long list of extensions based on "sources." Most of the sources are search result pages on drupal.org. This is the most "reliable" method of getting at new commerce modules.

How it works
------------

The system is based on keeping a tab open on your system for your local "Slurp" instance. It will reload every 15 minutes by default and give you quite a bit of feedback on each load. If the reload happens within the "start" and "end" times, it will try to fill the queue with jobs and then process each job based on when it was added and the "weight" of the item. 

You can limit certain items from being run. For example, search results are only limited to something like "10/day" so as not to hit drupal.org with too many search requests at once. These jobs will get moved down in the queue if they are "next" but are not "allowed" based on constraints. I highly recommend you open up the database tables that are created to see what is happening with your slurping. The table that I regularly visit and tweak is the "source_types" table. This is where the count of extensions and a bit of math will yield the number of jobs that should be in the queue and then number of jobs that "could" be run on that day.

Steps that happen when slurping:

1. Index.php Loads, sets up default variables
2. If necessary, a database is created
3. If within time limits, process jobs
4. If there are jobs, load top-most job and "run" it
   - When running a job, grab the "source" or the "module url" page
   - Process the output
   - Save the output into the extensions database table
   - If we are processing a "search result" we will save all new modules found in the extensions page
   - If we are processing a "module page" we will save all available additional information we could glean from the page that was loaded.
5. If there are not jobs, process the "sources" table and load both "sources" and "extensions" as necessary. This job creation is buggy and I need to spend more time in figuring out why. 

Exporting
---------

It's a very simple process to export your data. By default, you can visit "export.php" and it will save a CSV export of your extensions list. I've created a simple Feeds Importer that reads this into drupalcommerce.org and updates all the extension pages.
