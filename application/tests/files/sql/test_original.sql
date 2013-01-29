/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `deploytool`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `deploytool` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;

USE `deploytool`;

--
-- Table structure for table `application`
--

DROP TABLE IF EXISTS `application`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application`
--

LOCK TABLES `application` WRITE;
/*!40000 ALTER TABLE `application` DISABLE KEYS */;
INSERT INTO `application` VALUES (1,'/var/www/jelle.dev/foodfirm');
/*!40000 ALTER TABLE `application` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deployment`
--

DROP TABLE IF EXISTS `deployment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deployment` (
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
  KEY `index_foreignkey_deployment_deployment` (`deployment_id`),
  CONSTRAINT `deployment_ibfk_1` FOREIGN KEY (`deployment_id`) REFERENCES `deployment` (`id`),
  CONSTRAINT `cons_fk_deployment_source_id_id` FOREIGN KEY (`source_id`) REFERENCES `instance` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  CONSTRAINT `cons_fk_deployment_target_id_id` FOREIGN KEY (`target_id`) REFERENCES `instance` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deployment`
--

LOCK TABLES `deployment` WRITE;
/*!40000 ALTER TABLE `deployment` DISABLE KEYS */;
INSERT INTO `deployment` VALUES (1,'/var/www/robin.dev/deploy/buildscripts/pimcore.xml','1.0',1,2,'deploy','2012-11-15 13:50:56',1,1,NULL),(2,'/var/www/robin.dev/deploy/buildscripts/pimcore.xml','1.0',1,2,'upgrade','2012-11-15 13:56:02',1,0,NULL),(3,'/var/www/robin.dev/deploy/buildscripts/pimcore.xml','1.0',1,2,'upgrade','2012-11-15 14:22:11',0,1,NULL),(12,'/var/www/robin.dev/deploy/buildscripts/pimcore.xml','1.0',1,2,'upgrade','2012-11-21 14:38:19',0,1,NULL);
/*!40000 ALTER TABLE `deployment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instance`
--

DROP TABLE IF EXISTS `instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `server_id` int(11) unsigned DEFAULT NULL,
  `application_id` int(11) unsigned DEFAULT NULL,
  `filehash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dbhash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_instance_server` (`server_id`),
  KEY `index_foreignkey_instance_application` (`application_id`),
  CONSTRAINT `cons_fk_instance_application_id_id` FOREIGN KEY (`application_id`) REFERENCES `application` (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
  CONSTRAINT `cons_fk_instance_server_id_id` FOREIGN KEY (`server_id`) REFERENCES `server` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instance`
--

LOCK TABLES `instance` WRITE;
/*!40000 ALTER TABLE `instance` DISABLE KEYS */;
INSERT INTO `instance` VALUES (1,1,1,'631a1fd6b545d7353bd5cfb24ad7d39fa953670a',''),(2,2,1,NULL,NULL);
/*!40000 ALTER TABLE `instance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `process`
--

DROP TABLE IF EXISTS `process`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `process` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `command` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `msg` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `percent` double DEFAULT NULL,
  `deployment_id` int(11) unsigned DEFAULT NULL,
  `exitcode` double DEFAULT NULL,
  `target` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_process_deployment` (`deployment_id`),
  CONSTRAINT `cons_fk_process_deployment_id_id` FOREIGN KEY (`deployment_id`) REFERENCES `deployment` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `process`
--

LOCK TABLES `process` WRITE;
/*!40000 ALTER TABLE `process` DISABLE KEYS */;
INSERT INTO `process` VALUES (1,'Finished','phing -f \"/var/www/robin.dev/deploy/buildscripts/pimcore.xml\" Deploy','Chmodding and changing symlink...',100,1,0,'Deploy'),(2,'Finished','phing -f \"/var/www/robin.dev/deploy/buildscripts/pimcore.xml\" Pilot','Comparing files',100,2,0,'Pilot'),(3,'Finished','phing -f \"/var/www/robin.dev/deploy/buildscripts/pimcore.xml\" Upgrade','Chmodding and changing symlink...',100,2,0,'Upgrade'),(4,'Finished','phing -f \"/var/www/robin.dev/deploy/buildscripts/pimcore.xml\" Pilot','Comparing files',100,3,0,'Pilot'),(5,'Queued','phing -f \"/var/www/robin.dev/deploy/buildscripts/pimcore.xml\" Upgrade',NULL,0,3,NULL,'Upgrade'),(16,'Finished','phing -f \"/var/www/robin.dev/deploy/buildscripts/pimcore.xml\" Pilot','Comparing files',100,12,0,'Pilot'),(17,'Queued','phing -f \"/var/www/robin.dev/deploy/buildscripts/pimcore.xml\" Upgrade',NULL,0,12,NULL,'Upgrade');
/*!40000 ALTER TABLE `process` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property`
--

DROP TABLE IF EXISTS `property`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `deployment_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_property_deployment` (`deployment_id`),
  CONSTRAINT `cons_fk_property_deployment_id_id` FOREIGN KEY (`deployment_id`) REFERENCES `deployment` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property`
--

LOCK TABLES `property` WRITE;
/*!40000 ALTER TABLE `property` DISABLE KEYS */;
INSERT INTO `property` VALUES (1,'deploymentDate','20121115135056',1),(2,'deploymentDir','/tmp/deployments/1',1),(3,'deploymentDate','20121115135602',2),(4,'deploymentDir','/tmp/deployments/2',2),(5,'deploymentDate','20121115142211',3),(6,'deploymentDir','/tmp/deployments/3',3),(24,'deploymentDate','20121121143819',12),(25,'deploymentDir','/tmp/deployments/12',12);
/*!40000 ALTER TABLE `property` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `server`
--

DROP TABLE IF EXISTS `server`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `server` (
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `server`
--

LOCK TABLES `server` WRITE;
/*!40000 ALTER TABLE `server` DISABLE KEYS */;
INSERT INTO `server` VALUES (1,'dev','www-data','www-data','dev','localhost','Development server','','','local'),(2,'demo','demo-data','demo-data','demo.local','demo.local','Demo','demos','ERewfdsfderTERGdfgfghyjYUOjn','remote');
/*!40000 ALTER TABLE `server` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `setting`
--

DROP TABLE IF EXISTS `setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `instance_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_setting_instance` (`instance_id`),
  CONSTRAINT `cons_fk_setting_instance_id_id` FOREIGN KEY (`instance_id`) REFERENCES `instance` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=353 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `setting`
--

LOCK TABLES `setting` WRITE;
/*!40000 ALTER TABLE `setting` DISABLE KEYS */;
INSERT INTO `setting` VALUES (1,'url','foodfirm.jelle.dev',1),(2,'user','jelle',1),(3,'project','foodfirm',1),(4,'buildsdir','',1),(5,'webroot','/var/www/jelle.dev/foodfirm',1),(6,'databaseName','jelle_foodfirm',1),(9,'url','test.demo.local',2),(10,'project','test',2),(11,'buildsdir','/home/demos/public_html/_test',2),(12,'webroot','/home/demos/public_html/test',2),(13,'databaseName','demos_test',2);
/*!40000 ALTER TABLE `setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stdin`
--

DROP TABLE IF EXISTS `stdin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stdin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `command` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `new` tinyint(3) unsigned DEFAULT NULL,
  `process_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_stdin_process` (`process_id`),
  CONSTRAINT `cons_fk_stdin_process_id_id` FOREIGN KEY (`process_id`) REFERENCES `process` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stdin`
--

LOCK TABLES `stdin` WRITE;
/*!40000 ALTER TABLE `stdin` DISABLE KEYS */;
/*!40000 ALTER TABLE `stdin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stdout`
--

DROP TABLE IF EXISTS `stdout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stdout` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `output` longtext COLLATE utf8_unicode_ci,
  `process_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_foreignkey_stdout_process` (`process_id`),
  CONSTRAINT `cons_fk_stdout_process_id_id` FOREIGN KEY (`process_id`) REFERENCES `process` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=387 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stdout`
--

LOCK TABLES `stdout` WRITE;
/*!40000 ALTER TABLE `stdout` DISABLE KEYS */;
/*!40000 ALTER TABLE `stdout` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
