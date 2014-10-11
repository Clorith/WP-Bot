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
  `is_question` tinyint(1) NOT NULL,
  `is_appreciation` text NOT NULL,
  `is_docbot` text NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;
