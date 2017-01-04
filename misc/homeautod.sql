-- Homeautod database structure
-- VERSION: 0.0.2

CREATE TABLE `actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `action` varchar(45) DEFAULT NULL,
  `endpoint_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ep_status` tinyint(4) DEFAULT NULL,
  `min_time` int(11) DEFAULT '0',
  `add_time` int(11) DEFAULT '0',
  `options` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(128) DEFAULT NULL,
  `driver` varchar(64) DEFAULT NULL,
  `path` varchar(256) NOT NULL,
  `settings` varchar(2048) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `endpoints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) DEFAULT NULL,
  `device_id` int(11) NOT NULL,
  `epnumber` int(11) NOT NULL,
  `type` enum('DI','DO','Video stream','AI','AO') DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `io_type` enum('input','output') DEFAULT NULL,
  `normal_state` enum('NC','NO') DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  `value` varchar(100) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `parameters` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(256) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `lastrun` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `schedule_id` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `endpoint_id` int(11) NOT NULL,
  `time_end` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(256) NOT NULL,
  `second` varchar(50) NOT NULL DEFAULT '*',
  `minute` varchar(50) NOT NULL DEFAULT '*',
  `hour` varchar(50) NOT NULL DEFAULT '*',
  `day` varchar(50) NOT NULL DEFAULT '*',
  `month` varchar(50) NOT NULL DEFAULT '*',
  `weekday` varchar(50) NOT NULL DEFAULT '*',
  `year` varchar(50) NOT NULL DEFAULT '*',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `triggers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `event_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `endpoint_id` int(11) NOT NULL,
  `ep_status` int(11) DEFAULT NULL,
  `rel_operator` varchar(2) DEFAULT NULL,
  `value` decimal(10,0) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(256) NOT NULL,
  `username` varchar(128) NOT NULL,
  `password` varchar(128) NOT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `alarmstate` tinyint(1) DEFAULT '0',
  `armed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
