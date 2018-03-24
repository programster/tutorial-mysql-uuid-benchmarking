This code is for benchmarking the performance impacts of using different types of ID for the primary key in a database, but can be easily modified to benchmark other aspects.

## Steps
* Run `composer install` to install the necessary packages.
* Set up a MySQL database somewhere and make it remotely accessible.
* Fill in the connection details for that database in the `settings.php.tmpl` file
* Rename the `settings.php.tmpl` file to `settings.php`
* Execute the `main.php` script.
* Look in the `results` folder for the time the queries took and how long each test took to run. If the test took a long time to run, but the queries were fast, this tells you that the generation of the UUID was what made it slow.
