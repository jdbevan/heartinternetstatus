Alternative status page for Heart Internet.

The code in the master branch was used to scrape the actual Heart Internet status page for information and that was added to a database. See heartinternet.sql for the database schema (MySQL).

The systemstatus.php file was run using a cronjob to scrape the actual status page, and the index.php file was used to generate a static HTML file that was pushed to the gh-pages branch of this repo so it was then served up as a static Github page.
