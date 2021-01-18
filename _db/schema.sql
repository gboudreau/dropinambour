
CREATE TABLE `available_episodes` (
  `media_id` int(11) unsigned NOT NULL,
  `season` smallint(5) unsigned NOT NULL,
  `episodes` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`media_id`,`season`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `available_medias` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` tinytext CHARACTER SET utf8mb4 NOT NULL,
  `year` smallint(5) unsigned DEFAULT NULL,
  `type` enum('movie','show') NOT NULL,
  `key` tinytext DEFAULT NULL,
  `section_id` int(11) unsigned DEFAULT NULL,
  `guid` tinytext DEFAULT NULL,
  `added_when` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_id` (`section_id`,`guid`(255))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `available_medias_guids` (
  `media_id` int(11) unsigned NOT NULL,
  `source` enum('imdb','tmdb','tvdb','tmdbtv','tvdbm') NOT NULL,
  `source_id` varchar(32) DEFAULT '',
  PRIMARY KEY (`media_id`,`source`),
  KEY `source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `config` (
  `key` varchar(32) NOT NULL DEFAULT '',
  `value` text CHARACTER SET utf8mb4 NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `requested_episodes` (
  `request_id` int(11) unsigned NOT NULL,
  `season` smallint(5) unsigned NOT NULL,
  `episodes` smallint(5) unsigned DEFAULT NULL,
  `monitored` tinyint(1) NOT NULL,
  PRIMARY KEY (`request_id`,`season`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `requests` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` int(11) unsigned DEFAULT NULL,
  `monitored_by` enum('radarr','sonarr','none') NOT NULL,
  `type` enum('movie','show') NOT NULL,
  `requested_by` tinytext DEFAULT NULL,
  `quality_profile` tinyint(3) unsigned DEFAULT NULL,
  `language_profile` tinyint(3) unsigned DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `title` tinytext CHARACTER SET utf8mb4 DEFAULT NULL,
  `monitored` tinyint(1) NOT NULL DEFAULT 1,
  `imdb_id` varchar(11) DEFAULT NULL,
  `tmdb_id` int(11) unsigned DEFAULT NULL,
  `tvdb_id` int(11) unsigned DEFAULT NULL,
  `added_when` timestamp NOT NULL DEFAULT current_timestamp(),
  `filled_when` datetime DEFAULT NULL,
  `notified_when` datetime DEFAULT NULL,
  `hidden` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_id` (`external_id`,`monitored_by`),
  KEY `notified_when` (`notified_when`),
  KEY `monitored_by` (`monitored_by`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `sections` (
  `id` int(11) unsigned NOT NULL,
  `name` tinytext CHARACTER SET utf8mb4 DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `sessions` (
  `id` varchar(32) CHARACTER SET latin1 NOT NULL,
  `data` mediumtext CHARACTER SET utf8mb4 NOT NULL,
  `access_time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='PHP sessions';

CREATE TABLE `tmdb_cache` (
  `tmdbtv_id` int(11) unsigned NOT NULL,
  `details` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tmdbtv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `tmdb_external_ids` (
  `tmdb_id` int(11) unsigned NOT NULL,
  `tmdbtv_id` int(11) NOT NULL,
  `imdb_id` varchar(11) NOT NULL DEFAULT '0',
  `tvdb_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`tmdb_id`,`tmdbtv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
