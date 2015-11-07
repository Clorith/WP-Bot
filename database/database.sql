SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userhost` varchar(255) NOT NULL,
  `nickname` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `event` varchar(255) NOT NULL DEFAULT 'message',
  `channel` varchar(255) NOT NULL DEFAULT '#wordpress'
  `is_question` tinyint(1) NOT NULL,
  `is_appreciation` text NOT NULL,
  `is_docbot` text NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Table structure for table `stats_30d`
--

CREATE TABLE IF NOT EXISTS `stats_30d` (
  `nickname` varchar(255) NOT NULL,
  `messages` int(11) NOT NULL,
  `appreciation` int(11) NOT NULL,
  `questions` int(11) NOT NULL,
  `docbot` int(11) NOT NULL,
  UNIQUE KEY `nickname` (`nickname`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `predefined_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `command` varchar(255) NOT NULL,
  `response` text NOT NULL,
  `enabled` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `privileged` (
  `user` varchar(255) NOT NULL,
  `enabled` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;