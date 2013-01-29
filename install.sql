SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

--
-- Table structure for table `application`
--

DROP TABLE IF EXISTS `application`;
CREATE TABLE IF NOT EXISTS `application` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `deployment`
--

DROP TABLE IF EXISTS `deployment`;
CREATE TABLE IF NOT EXISTS `deployment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `recipe` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `version` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `source_id` int(11) unsigned DEFAULT NULL,
  `target_id` int(11) unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `success` tinyint(3) unsigned DEFAULT NULL,
  `rollback` tinyint(3) unsigned DEFAULT NULL,
  `deployment_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_deployment_instance` (`source_id`),
  KEY `cons_fk_deployment_target_id_id` (`target_id`),
  KEY `index_foreignkey_deployment_deployment` (`deployment_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `instance`
--

DROP TABLE IF EXISTS `instance`;
CREATE TABLE IF NOT EXISTS `instance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `server_id` int(11) unsigned DEFAULT NULL,
  `application_id` int(11) unsigned DEFAULT NULL,
  `filehash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dbhash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_instance_server` (`server_id`),
  KEY `index_foreignkey_instance_application` (`application_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `process`
--

DROP TABLE IF EXISTS `process`;
CREATE TABLE IF NOT EXISTS `process` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `command` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `msg` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `percent` double DEFAULT NULL,
  `deployment_id` int(11) unsigned DEFAULT NULL,
  `exitcode` double DEFAULT NULL,
  `target` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_process_deployment` (`deployment_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `property`
--

DROP TABLE IF EXISTS `property`;
CREATE TABLE IF NOT EXISTS `property` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `deployment_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_property_deployment` (`deployment_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `server`
--

DROP TABLE IF EXISTS `server`;
CREATE TABLE IF NOT EXISTS `server` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `group` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `baseurl` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `host` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cpu` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `cpp` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `access` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `setting`
--

DROP TABLE IF EXISTS `setting`;
CREATE TABLE IF NOT EXISTS `setting` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `instance_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_setting_instance` (`instance_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `stdin`
--

DROP TABLE IF EXISTS `stdin`;
CREATE TABLE IF NOT EXISTS `stdin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `new` tinyint(3) unsigned DEFAULT NULL,
  `process_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_stdin_process` (`process_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

-- --------------------------------------------------------

--
-- Table structure for table `stdout`
--

DROP TABLE IF EXISTS `stdout`;
CREATE TABLE IF NOT EXISTS `stdout` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `output` longtext COLLATE utf8_unicode_ci,
  `process_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_stdout_process` (`process_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `deployment`
--
ALTER TABLE `deployment`
  ADD CONSTRAINT `cons_fk_deployment_deployment_id_id` FOREIGN KEY (`deployment_id`) REFERENCES `deployment` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `cons_fk_deployment_source_id_id` FOREIGN KEY (`source_id`) REFERENCES `instance` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `cons_fk_deployment_target_id_id` FOREIGN KEY (`target_id`) REFERENCES `instance` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `instance`
--
ALTER TABLE `instance`
  ADD CONSTRAINT `cons_fk_instance_application_id_id` FOREIGN KEY (`application_id`) REFERENCES `application` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  ADD CONSTRAINT `cons_fk_instance_server_id_id` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `process`
--
ALTER TABLE `process`
  ADD CONSTRAINT `cons_fk_process_deployment_id_id` FOREIGN KEY (`deployment_id`) REFERENCES `deployment` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `property`
--
ALTER TABLE `property`
  ADD CONSTRAINT `cons_fk_property_deployment_id_id` FOREIGN KEY (`deployment_id`) REFERENCES `deployment` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `setting`
--
ALTER TABLE `setting`
  ADD CONSTRAINT `cons_fk_setting_instance_id_id` FOREIGN KEY (`instance_id`) REFERENCES `instance` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `stdin`
--
ALTER TABLE `stdin`
  ADD CONSTRAINT `cons_fk_stdin_process_id_id` FOREIGN KEY (`process_id`) REFERENCES `process` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;

--
-- Constraints for table `stdout`
--
ALTER TABLE `stdout`
  ADD CONSTRAINT `cons_fk_stdout_process_id_id` FOREIGN KEY (`process_id`) REFERENCES `process` (`id`) ON DELETE SET NULL ON UPDATE SET NULL;
