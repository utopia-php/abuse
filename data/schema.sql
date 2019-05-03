CREATE DATABASE abuse;

CREATE TABLE `namespace.abuse.abuse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `_key` varchar(255) NOT NULL,
  `_time` varchar(45) NOT NULL,
  `_count` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique1` (`_key`,`_time`),
  KEY `index1` (`_key`,`_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;