dropinambour - Requests manager for Plex
========================================

**dropinambour** is a software that one can use to manage requests, from Plex users, to add new content (TV shows & movies) onto a Plex server.

It connects to your Plex server (using your Plex account), and manages its own database of requests, and will dispatch them to either Sonarr (v3) or Radarr (v3).
It will send email notifications to the requester, once the media has been downloaded by either Sonarr or Radarr, and is made available on the Plex server.

There is currently no users management; all users allowed to access your Plex server are also allowed to connect to *dropinambour* and make requests.
There is also no approval process; when a user requests a movie or TV show, it is immediately sent to Radarr or Sonarr for processing.
Of note: for TV shows, only the 1st season is requested, when a request is added for a new show. Users can then choose to request additional seasons.

## Current status

It-works-for-me™.

If anyone is interested in developping this further, and use it on their own Plex server, I'd be happy to answer any questons (use the [Discussion](https://github.com/gboudreau/dropinambour/discussions) tab), and merge [Pull Requests](https://github.com/gboudreau/dropinambour/pulls).

## Requirements

- Plex (obviously)
- Sonarr and/or Radarr (only v3 are supported)
- A MySQL-compatible server (eg. MariaDB)
- PHP 7.3+ & [composer](https://getcomposer.org/)

## Installation

1. Put all the files in any web-accessible (and PHP-enabled) folder.
   Alternativaly, you can use PHP to run a server:

   ```bash
   php -S 0.0.0.0:8080 ./index.php
   ```

2. Copy `_config/config.example.php` to `_config/config.php` and edit its content.

3. Create an empty MySQL database, and user:

   ```mysql
   mysql -u root -p
   > CREATE DATABASE dinb;
   > GRANT ALL on dinb.* TO dinb_user@'localhost' IDENTIFIED BY 'complicated-password';
   ```

4. Import the empty DB schema from `_db/schema.sql` :

   ```bash
   mysql -u root -p dinb < _db/schema.sql
   ```

5. Run `composer install` to install the required dependencies.
   It might complain about missing PHP extensions; install them using you preferred method (apt/yum).

6. Create a con job on your server to load the following URL every 5 minutes (or as often as you want):
   `http://localhost/dropinambour/?action=cron` 

## Development

If you'd like to develop new features, or fix bugs, you only need some PHP and/or CSS/JS know-how.

I use my own (simple) framework for the controller & DB layers, [PHP Plates](https://platesphp.com/) for views, and [Bootstrap (5)](https://getbootstrap.com/docs/5.0/getting-started/introduction/) + jQuery for the web front-end.

Just start a new [Discussion](https://github.com/gboudreau/dropinambour/discussions) on Github, and I'll be happy to point you in the right direction to start.
