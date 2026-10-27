-- MariaDB dump 10.19  Distrib 10.4.22-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: savermate
-- ------------------------------------------------------
-- Server version	10.4.22-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_logs_user_id_foreign` (`user_id`),
  KEY `admin_logs_created_at_index` (`created_at`),
  KEY `admin_logs_action_index` (`action`),
  CONSTRAINT `admin_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_logs`
--

LOCK TABLES `admin_logs` WRITE;
/*!40000 ALTER TABLE `admin_logs` DISABLE KEYS */;
INSERT INTO `admin_logs` VALUES (23,1,'users.delete','Deleted the account of Mr. Avery Daniel (@elsa.volkman) and all their content','::1','2026-10-09 10:45:49','2026-10-09 10:45:49');
/*!40000 ALTER TABLE `admin_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `post_id` bigint(20) unsigned DEFAULT NULL,
  `movie_id` bigint(20) unsigned DEFAULT NULL,
  `comment_id` bigint(20) unsigned DEFAULT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `preview` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alerts_actor_id_foreign` (`actor_id`),
  KEY `alerts_post_id_foreign` (`post_id`),
  KEY `alerts_comment_id_foreign` (`comment_id`),
  KEY `alerts_conversation_id_foreign` (`conversation_id`),
  KEY `alerts_user_id_read_at_index` (`user_id`,`read_at`),
  KEY `alerts_movie_id_foreign` (`movie_id`),
  CONSTRAINT `alerts_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alerts_comment_id_foreign` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alerts_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alerts_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alerts_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alerts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=357 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alerts`
--

LOCK TABLES `alerts` WRITE;
/*!40000 ALTER TABLE `alerts` DISABLE KEYS */;
INSERT INTO `alerts` VALUES (1,1,2,'reaction',1,NULL,NULL,NULL,'Love on your post','2026-10-09 00:38:51','2026-10-08 22:20:16','2026-10-09 00:38:51'),(2,1,3,'reaction',1,NULL,NULL,NULL,'Fire on your post','2026-10-09 00:38:51','2026-10-08 22:27:16','2026-10-09 00:38:51'),(3,1,4,'reaction',1,NULL,NULL,NULL,'Clap on your post','2026-10-09 00:38:51','2026-10-08 22:34:16','2026-10-09 00:38:51'),(4,1,5,'reaction',1,NULL,NULL,NULL,'Wow on your post','2026-10-09 00:38:51','2026-10-08 22:41:16','2026-10-09 00:38:51'),(5,1,6,'reaction',1,NULL,NULL,NULL,'Idea on your post','2026-10-09 00:38:51','2026-10-08 22:48:16','2026-10-09 00:38:51'),(6,1,7,'reaction',1,NULL,NULL,NULL,'Like on your post','2026-10-09 00:38:51','2026-10-08 22:55:16','2026-10-09 00:38:51'),(7,6,1,'reaction',2,NULL,NULL,NULL,'Love on your post',NULL,'2026-10-08 19:13:16','2026-10-08 19:13:16'),(8,6,2,'reaction',2,NULL,NULL,NULL,'Fire on your post',NULL,'2026-10-08 19:20:16','2026-10-08 19:20:16'),(9,6,3,'reaction',2,NULL,NULL,NULL,'Clap on your post',NULL,'2026-10-08 19:27:16','2026-10-08 19:27:16'),(10,6,4,'reaction',2,NULL,NULL,NULL,'Wow on your post',NULL,'2026-10-08 19:34:16','2026-10-08 19:34:16'),(11,6,5,'reaction',2,NULL,NULL,NULL,'Idea on your post',NULL,'2026-10-08 19:41:16','2026-10-08 19:41:16'),(12,2,1,'reaction',3,NULL,NULL,NULL,'Fire on your post','2026-10-08 16:03:17','2026-10-08 15:13:17','2026-10-08 15:13:17'),(13,2,3,'reaction',3,NULL,NULL,NULL,'Wow on your post','2026-10-08 16:03:17','2026-10-08 15:27:17','2026-10-08 15:27:17'),(14,2,4,'reaction',3,NULL,NULL,NULL,'Idea on your post','2026-10-08 16:03:17','2026-10-08 15:34:17','2026-10-08 15:34:17'),(15,2,5,'reaction',3,NULL,NULL,NULL,'Like on your post','2026-10-08 16:03:17','2026-10-08 15:41:17','2026-10-08 15:41:17'),(16,3,1,'reaction',4,NULL,NULL,NULL,'Clap on your post','2026-10-07 23:03:17','2026-10-07 22:13:17','2026-10-07 22:13:17'),(17,3,2,'reaction',4,NULL,NULL,NULL,'Wow on your post','2026-10-07 23:03:17','2026-10-07 22:20:17','2026-10-07 22:20:17'),(18,3,4,'reaction',4,NULL,NULL,NULL,'Like on your post','2026-10-07 23:03:17','2026-10-07 22:34:17','2026-10-07 22:34:17'),(19,4,1,'reaction',5,NULL,NULL,NULL,'Wow on your post','2026-10-07 19:03:18','2026-10-07 18:13:18','2026-10-07 18:13:18'),(20,4,2,'reaction',5,NULL,NULL,NULL,'Idea on your post','2026-10-07 19:03:18','2026-10-07 18:20:18','2026-10-07 18:20:18'),(21,4,3,'reaction',5,NULL,NULL,NULL,'Like on your post','2026-10-07 19:03:18','2026-10-07 18:27:18','2026-10-07 18:27:18'),(22,8,1,'reaction',6,NULL,NULL,NULL,'Idea on your post','2026-10-06 21:03:18','2026-10-06 20:13:18','2026-10-06 20:13:18'),(23,8,2,'reaction',6,NULL,NULL,NULL,'Like on your post','2026-10-06 21:03:18','2026-10-06 20:20:18','2026-10-06 20:20:18'),(24,5,1,'reaction',7,NULL,NULL,NULL,'Like on your post','2026-10-05 23:03:18','2026-10-05 22:13:18','2026-10-05 22:13:18'),(25,5,2,'reaction',7,NULL,NULL,NULL,'Love on your post','2026-10-05 23:03:18','2026-10-05 22:20:18','2026-10-05 22:20:18'),(26,7,1,'reaction',8,NULL,NULL,NULL,'Love on your post','2026-10-05 01:03:18','2026-10-05 00:13:18','2026-10-05 00:13:18'),(27,7,2,'reaction',8,NULL,NULL,NULL,'Fire on your post','2026-10-05 01:03:18','2026-10-05 00:20:18','2026-10-05 00:20:18'),(28,1,2,'reaction',9,NULL,NULL,NULL,'Clap on your post','2026-10-04 01:03:19','2026-10-04 00:20:19','2026-10-04 00:20:19'),(29,1,4,'comment',1,NULL,1,NULL,'This is excellent. Waiting times for Ward 4 finally match what we see...','2026-10-09 00:38:51','2026-10-08 22:38:16','2026-10-09 00:38:51'),(30,1,3,'comment',1,NULL,2,NULL,'Added it to the browser homepage on all the front desk machines.','2026-10-09 00:38:51','2026-10-08 23:03:16','2026-10-09 00:38:51'),(31,2,4,'comment',3,NULL,3,NULL,'Noted on the cupboards. I will have the ward stock moved to the new sh...','2026-10-08 16:38:17','2026-10-08 15:38:17','2026-10-08 15:38:17'),(32,3,2,'comment',4,NULL,4,NULL,'Can we push it to 8 p.m.? The evening dispensing run finishes around h...','2026-10-07 23:38:17','2026-10-07 22:38:17','2026-10-07 22:38:17'),(33,2,3,'reply',4,NULL,5,NULL,'Fine by me, 8 p.m. it is. I will put a notice on the boards tomorrow.',NULL,'2026-10-07 22:56:17','2026-10-07 22:56:17'),(34,8,6,'comment',6,NULL,6,NULL,'If you send me the maps I can turn them into a poster for the communit...','2026-10-06 21:38:18','2026-10-06 20:38:18','2026-10-06 20:38:18'),(35,6,8,'reply',6,NULL,7,NULL,'Will do, thank you. Sending the shapefiles across this evening.',NULL,'2026-10-06 20:56:18','2026-10-06 20:56:18'),(36,1,2,'message',NULL,NULL,NULL,2,'That would save me a lot of chasing. Thank you.','2026-10-09 00:10:20','2026-10-07 08:03:22','2026-10-09 00:10:20'),(37,1,2,'message',NULL,NULL,NULL,2,'Here is the sheet, straight from my drive.','2026-10-09 00:10:20','2026-10-07 09:03:22','2026-10-09 00:10:20'),(38,1,4,'message',NULL,NULL,NULL,3,'Could you add a night-shift breakdown? That is where our delays actual...','2026-10-09 00:10:15','2026-10-08 02:03:22','2026-10-09 00:10:15'),(39,1,4,'message',NULL,NULL,NULL,3,'No rush - whenever you have a moment.','2026-10-09 00:10:15','2026-10-08 03:03:23','2026-10-09 00:10:15'),(40,1,6,'message',NULL,NULL,NULL,4,'Twelve months, it tells a better story.','2026-10-09 00:07:36','2026-10-08 19:03:23','2026-10-09 00:07:36'),(41,2,1,'message',NULL,NULL,NULL,2,'Attendance figures attached straight from my drive.',NULL,'2026-10-09 00:34:30','2026-10-09 00:34:30'),(44,6,1,'reaction',2,NULL,NULL,NULL,'Haha on your post',NULL,'2026-10-09 01:04:12','2026-10-09 01:04:12'),(45,6,1,'reaction',2,NULL,NULL,NULL,'Sad on your post',NULL,'2026-10-09 01:04:13','2026-10-09 01:04:13'),(46,3,1,'reaction',4,NULL,NULL,NULL,'Like on your post',NULL,'2026-10-09 01:11:08','2026-10-09 01:11:08'),(47,3,1,'reaction',4,NULL,NULL,NULL,'Love on your post',NULL,'2026-10-09 01:11:09','2026-10-09 01:11:09'),(48,3,1,'reaction',4,NULL,NULL,NULL,'Haha on your post',NULL,'2026-10-09 01:11:09','2026-10-09 01:11:09'),(49,3,1,'reaction',4,NULL,NULL,NULL,'Sad on your post',NULL,'2026-10-09 01:11:09','2026-10-09 01:11:09'),(50,3,1,'reaction',4,NULL,NULL,NULL,'Fire on your post',NULL,'2026-10-09 01:11:10','2026-10-09 01:11:10'),(51,15,1,'comment',15,NULL,8,NULL,'good',NULL,'2026-10-09 02:35:06','2026-10-09 02:35:06'),(52,18,1,'reaction',16,NULL,NULL,NULL,'Love on your post',NULL,'2026-10-09 03:02:12','2026-10-09 03:02:12'),(53,63,1,'message',NULL,NULL,NULL,6,'hi',NULL,'2026-10-09 06:41:06','2026-10-09 06:41:06'),(72,18,1,'reaction',16,NULL,NULL,NULL,'Love on your post',NULL,'2026-10-09 08:17:11','2026-10-09 08:17:11'),(79,5,1,'reaction',7,NULL,NULL,NULL,'Love on your post','2026-10-09 10:42:55','2026-10-09 08:36:25','2026-10-09 10:42:55'),(171,51,5,'message',NULL,NULL,NULL,12,'hi',NULL,'2026-10-09 10:44:11','2026-10-09 10:44:11'),(172,6,1,'reaction',2,NULL,NULL,NULL,'Fire on your post',NULL,'2026-10-09 10:59:45','2026-10-09 10:59:45'),(262,63,1,'message',NULL,NULL,NULL,6,'hi',NULL,'2026-10-09 11:18:04','2026-10-09 11:18:04'),(284,29,1,'group_added',NULL,NULL,NULL,26,'jknn',NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03'),(285,33,1,'group_added',NULL,NULL,NULL,26,'jknn',NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03'),(286,45,1,'group_added',NULL,NULL,NULL,26,'jknn',NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03'),(354,29,1,'group_message',NULL,NULL,NULL,26,'hi',NULL,'2026-10-09 11:24:37','2026-10-09 11:24:37'),(355,33,1,'group_message',NULL,NULL,NULL,26,'hi',NULL,'2026-10-09 11:24:37','2026-10-09 11:24:37'),(356,45,1,'group_message',NULL,NULL,NULL,26,'hi',NULL,'2026-10-09 11:24:37','2026-10-09 11:24:37');
/*!40000 ALTER TABLE `alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_user_id_foreign` (`user_id`),
  KEY `comments_parent_id_foreign` (`parent_id`),
  KEY `comments_post_id_parent_id_index` (`post_id`,`parent_id`),
  CONSTRAINT `comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
INSERT INTO `comments` VALUES (1,1,4,NULL,'This is excellent. Waiting times for Ward 4 finally match what we see on the floor.','2026-10-08 22:38:16','2026-10-08 22:38:16'),(2,1,3,NULL,'Added it to the browser homepage on all the front desk machines.','2026-10-08 23:03:16','2026-10-08 23:03:16'),(3,3,4,NULL,'Noted on the cupboards. I will have the ward stock moved to the new shelves by Friday.','2026-10-08 15:38:17','2026-10-08 15:38:17'),(4,4,2,NULL,'Can we push it to 8 p.m.? The evening dispensing run finishes around half past seven.','2026-10-07 22:38:17','2026-10-07 22:38:17'),(5,4,3,4,'Fine by me, 8 p.m. it is. I will put a notice on the boards tomorrow.','2026-10-07 22:56:17','2026-10-07 22:56:17'),(6,6,6,NULL,'If you send me the maps I can turn them into a poster for the community centre.','2026-10-06 20:38:18','2026-10-06 20:38:18'),(7,6,8,6,'Will do, thank you. Sending the shapefiles across this evening.','2026-10-06 20:56:18','2026-10-06 20:56:18'),(8,15,1,NULL,'good','2026-10-09 02:35:06','2026-10-09 02:35:06');
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_members`
--

DROP TABLE IF EXISTS `conversation_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `last_read_message_id` bigint(20) unsigned DEFAULT NULL,
  `muted_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_members_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_members_user_id_index` (`user_id`),
  CONSTRAINT `conversation_members_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_members`
--

LOCK TABLES `conversation_members` WRITE;
/*!40000 ALTER TABLE `conversation_members` DISABLE KEYS */;
INSERT INTO `conversation_members` VALUES (27,26,1,'admin',109,NULL,'2026-10-09 11:23:03','2026-10-09 11:24:37'),(28,26,29,'member',69,NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03'),(29,26,33,'member',69,NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03'),(30,26,45,'member',69,NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03');
/*!40000 ALTER TABLE `conversation_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_group` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `user_one_id` bigint(20) unsigned DEFAULT NULL,
  `user_two_id` bigint(20) unsigned DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_user_one_id_user_two_id_unique` (`user_one_id`,`user_two_id`),
  KEY `conversations_user_two_id_foreign` (`user_two_id`),
  KEY `conversations_created_by_foreign` (`created_by`),
  KEY `conversations_is_group_index` (`is_group`),
  CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_user_one_id_foreign` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_user_two_id_foreign` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
INSERT INTO `conversations` VALUES (1,0,NULL,NULL,1,3,'2026-10-02 00:03:21','2026-10-09 00:03:21','2026-10-09 00:03:21'),(2,0,NULL,NULL,1,2,'2026-10-07 09:03:22','2026-10-09 00:03:21','2026-10-09 00:36:48'),(3,0,NULL,NULL,1,4,'2026-10-08 03:03:23','2026-10-09 00:03:22','2026-10-09 00:03:23'),(4,0,NULL,NULL,1,6,'2026-10-08 19:03:23','2026-10-09 00:03:23','2026-10-09 00:03:23'),(5,0,NULL,NULL,2,4,'2026-10-07 20:03:23','2026-10-09 00:03:23','2026-10-09 00:03:23'),(6,0,NULL,NULL,1,63,'2026-10-09 11:18:04','2026-10-09 06:40:35','2026-10-09 11:18:04'),(7,0,NULL,NULL,1,11,'2026-10-09 08:20:40','2026-10-09 08:20:40','2026-10-09 08:20:40'),(8,0,NULL,NULL,1,229,'2026-10-09 08:27:43','2026-10-09 08:27:43','2026-10-09 08:27:43'),(12,0,NULL,NULL,5,51,'2026-10-09 10:44:11','2026-10-09 10:44:03','2026-10-09 10:44:11'),(26,1,'jknn',1,NULL,NULL,'2026-10-09 11:24:37','2026-10-09 11:23:03','2026-10-09 11:24:37');
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drive_files`
--

DROP TABLE IF EXISTS `drive_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drive_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `drive_files_folder_id_foreign` (`folder_id`),
  KEY `drive_files_user_id_folder_id_index` (`user_id`,`folder_id`),
  KEY `drive_files_is_public_index` (`is_public`),
  CONSTRAINT `drive_files_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `drive_folders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `drive_files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drive_files`
--

LOCK TABLES `drive_files` WRITE;
/*!40000 ALTER TABLE `drive_files` DISABLE KEYS */;
INSERT INTO `drive_files` VALUES (1,1,1,'Clinic attendance - Q3.csv','drive/1/2b686484-7723-4379-8733-e79a1f4c29b0.csv','text/csv',99,1,'2026-10-01 00:03:20','2026-10-09 00:14:07'),(2,1,1,'Dashboard user guide.txt','drive/1/6de00708-7d64-4635-a1bf-0f17701d33fa.txt','text/plain',338,1,'2026-10-01 01:03:20','2026-10-01 01:03:20'),(3,1,2,'Informatics plan 2026 (draft).txt','drive/1/fd2c1be3-ca83-4197-ad68-a285a3ded98e.txt','text/plain',190,0,'2026-10-01 00:03:20','2026-10-01 00:03:20'),(4,2,3,'Stock reconciliation - Q3.csv','drive/2/4a2a0a29-f1c7-4ab6-b80a-4d606a8fd9b4.csv','text/csv',226,1,'2026-10-01 00:03:20','2026-10-01 00:03:20'),(5,2,3,'Ordering cut-off dates.txt','drive/2/857740fc-5c4d-48c0-a975-18182bb8504f.txt','text/plain',185,1,'2026-10-01 01:03:20','2026-10-01 01:03:20'),(6,7,4,'Travel claim form.txt','drive/7/123d87e4-21ba-4916-906f-4572706ec69c.txt','text/plain',420,1,'2026-10-01 00:03:20','2026-10-01 00:03:20'),(7,7,4,'Petty cash reimbursement.txt','drive/7/b0dbdb18-cf15-4362-8d85-a3b2a4d596a0.txt','text/plain',173,1,'2026-10-01 01:03:20','2026-10-01 01:03:20'),(8,5,5,'Turnaround times - weekly.csv','drive/5/84971bd1-ff18-456d-a3bf-5e11fdeda374.csv','text/csv',143,1,'2026-10-01 00:03:20','2026-10-01 00:03:20'),(9,5,5,'Sample rejection criteria.txt','drive/5/534c9b5a-7065-45e6-9d9c-9b2d233634b5.txt','text/plain',357,1,'2026-10-01 01:03:21','2026-10-01 01:03:21'),(10,8,6,'Larval survey - Kadawatha.csv','drive/8/6ec0f4d9-00a3-4046-bd32-46bfe36d17b1.csv','text/csv',149,1,'2026-10-01 00:03:21','2026-10-01 00:03:21'),(11,6,7,'World Health Day programme.txt','drive/6/25a8b93b-81af-4a5e-bcee-10fcd763b83d.txt','text/plain',280,1,'2026-10-01 00:03:21','2026-10-01 00:03:21'),(12,6,8,'Dengue poster - working copy.txt','drive/6/850b03ec-11bd-4977-858f-ab284ceb85da.txt','text/plain',170,0,'2026-10-01 00:03:21','2026-10-01 00:03:21'),(13,3,9,'Server maintenance window.txt','drive/3/69e3b27e-d50a-45af-a2b1-74487695f26b.txt','text/plain',306,1,'2026-10-01 00:03:21','2026-10-01 00:03:21'),(14,3,10,'backup-schedule.txt','drive/3/f4bf45f3-bc1a-4eee-a7fd-25db4bd1ce04.txt','text/plain',203,0,'2026-10-01 00:03:21','2026-10-01 00:03:21'),(15,4,11,'Nursing roster - this month.csv','drive/4/73104309-19af-4289-b801-fc96e59ced57.csv','text/csv',173,1,'2026-10-01 00:03:21','2026-10-01 00:03:21');
/*!40000 ALTER TABLE `drive_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drive_folders`
--

DROP TABLE IF EXISTS `drive_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drive_folders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `drive_folders_parent_id_foreign` (`parent_id`),
  KEY `drive_folders_user_id_parent_id_index` (`user_id`,`parent_id`),
  CONSTRAINT `drive_folders_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `drive_folders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `drive_folders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drive_folders`
--

LOCK TABLES `drive_folders` WRITE;
/*!40000 ALTER TABLE `drive_folders` DISABLE KEYS */;
INSERT INTO `drive_folders` VALUES (1,1,NULL,'Clinic Reports',1,'2026-09-30 00:03:19','2026-09-30 00:03:19'),(2,1,NULL,'Drafts',0,'2026-09-30 00:03:20','2026-09-30 00:03:20'),(3,2,NULL,'Pharmacy',1,'2026-09-30 00:03:20','2026-09-30 00:03:20'),(4,7,NULL,'Forms',1,'2026-09-30 00:03:20','2026-09-30 00:03:20'),(5,5,NULL,'Laboratory',1,'2026-09-30 00:03:20','2026-09-30 00:03:20'),(6,8,NULL,'Field Surveillance',1,'2026-09-30 00:03:21','2026-09-30 00:03:21'),(7,6,NULL,'Health Promotion',1,'2026-09-30 00:03:21','2026-09-30 00:03:21'),(8,6,NULL,'Poster artwork',0,'2026-09-30 00:03:21','2026-09-30 00:03:21'),(9,3,NULL,'IT Notices',1,'2026-09-30 00:03:21','2026-09-30 00:03:21'),(10,3,NULL,'Server configs',0,'2026-09-30 00:03:21','2026-09-30 00:03:21'),(11,4,NULL,'Ward 4',1,'2026-09-30 00:03:21','2026-09-30 00:03:21'),(12,1,NULL,'sample',0,'2026-10-09 00:08:35','2026-10-09 00:08:35');
/*!40000 ALTER TABLE `drive_folders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_attachments`
--

DROP TABLE IF EXISTS `message_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) unsigned NOT NULL,
  `source` enum('upload','drive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upload',
  `drive_file_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `message_attachments_message_id_foreign` (`message_id`),
  KEY `message_attachments_drive_file_id_foreign` (`drive_file_id`),
  CONSTRAINT `message_attachments_drive_file_id_foreign` FOREIGN KEY (`drive_file_id`) REFERENCES `drive_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `message_attachments_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_attachments`
--

LOCK TABLES `message_attachments` WRITE;
/*!40000 ALTER TABLE `message_attachments` DISABLE KEYS */;
INSERT INTO `message_attachments` VALUES (1,12,'drive',4,'Stock reconciliation - Q3.csv','drive/2/4a2a0a29-f1c7-4ab6-b80a-4d606a8fd9b4.csv','text/csv',226,'2026-10-09 00:03:22','2026-10-09 00:03:22');
/*!40000 ALTER TABLE `message_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_conversation_id_id_index` (`conversation_id`,`id`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,1,3,'Did you see the maintenance notice? Saturday 8 p.m. now, Sanduni asked me to move it.',NULL,'2026-10-01 12:07:21','2026-10-01 12:03:21','2026-10-01 12:03:21'),(2,1,1,'Saw it. Fine by me - nothing of mine runs on a Saturday evening.',NULL,'2026-10-01 16:07:21','2026-10-01 16:03:21','2026-10-01 16:03:21'),(3,1,1,'One thing: can you take a database snapshot before the patch? I would rather have a clean restore point.',NULL,'2026-10-01 17:07:21','2026-10-01 17:03:21','2026-10-01 17:03:21'),(4,1,3,'Already scripted. Snapshot at 19:45, kept for a fortnight.',NULL,'2026-10-01 21:07:21','2026-10-01 21:03:21','2026-10-01 21:03:21'),(5,1,3,'I put the full notice on the drive if you want to forward it to the clinics.',NULL,'2026-10-01 22:07:21','2026-10-01 22:03:21','2026-10-01 22:03:21'),(6,1,1,'Perfect, thanks. I will send it round in the morning.',NULL,'2026-10-02 00:07:21','2026-10-02 00:03:21','2026-10-02 00:03:21'),(7,2,2,'The reconciliation sheet is published now. Can you pull the issue figures into the dashboard?',NULL,'2026-10-07 00:07:22','2026-10-07 00:03:22','2026-10-07 00:03:22'),(8,2,1,'Yes. Is the item coding the same as last quarter?',NULL,'2026-10-07 02:07:22','2026-10-07 02:03:22','2026-10-07 02:03:22'),(9,2,2,'Same codes, but I added a weeks-of-cover column at the end.',NULL,'2026-10-07 04:07:22','2026-10-07 04:03:22','2026-10-07 04:03:22'),(10,2,1,'Good, that is the useful one. I will add a low-cover alert under six weeks.',NULL,'2026-10-07 06:07:22','2026-10-07 06:03:22','2026-10-07 06:03:22'),(11,2,2,'That would save me a lot of chasing. Thank you.',NULL,'2026-10-09 00:10:20','2026-10-07 08:03:22','2026-10-09 00:10:20'),(12,2,2,'Here is the sheet, straight from my drive.',NULL,'2026-10-09 00:10:20','2026-10-07 09:03:22','2026-10-09 00:10:20'),(13,3,4,'Ward 4 waiting times look right on the new dashboard. Finally.',NULL,'2026-10-07 22:07:22','2026-10-07 22:03:22','2026-10-07 22:03:22'),(14,3,1,'Glad to hear it. The old version was averaging across the whole clinic, which flattered everybody.',NULL,'2026-10-08 00:07:22','2026-10-08 00:03:22','2026-10-08 00:03:22'),(15,3,4,'Could you add a night-shift breakdown? That is where our delays actually sit.',NULL,'2026-10-09 00:10:15','2026-10-08 02:03:22','2026-10-09 00:10:15'),(16,3,4,'No rush - whenever you have a moment.',NULL,'2026-10-09 00:10:15','2026-10-08 03:03:23','2026-10-09 00:10:15'),(17,4,6,'Programme for Friday is on the drive. Could you put the attendance numbers on a slide for the keynote?',NULL,'2026-10-08 17:07:23','2026-10-08 17:03:23','2026-10-08 17:03:23'),(18,4,1,'Sure. Quarterly figures or the twelve month trend?',NULL,'2026-10-08 18:07:23','2026-10-08 18:03:23','2026-10-08 18:03:23'),(19,4,6,'Twelve months, it tells a better story.',NULL,'2026-10-09 00:07:36','2026-10-08 19:03:23','2026-10-09 00:07:36'),(20,5,2,'About the ward cupboards - can I send someone up on Friday morning to count what is there?',NULL,'2026-10-07 18:07:23','2026-10-07 18:03:23','2026-10-07 18:03:23'),(21,5,4,'Yes, after the ward round please. Say half past ten.',NULL,'2026-10-07 20:07:23','2026-10-07 20:03:23','2026-10-07 20:03:23'),(25,6,1,'hi',NULL,NULL,'2026-10-09 06:41:06','2026-10-09 06:41:06'),(29,12,5,'hi',NULL,NULL,'2026-10-09 10:44:10','2026-10-09 10:44:10'),(31,6,1,'hi',NULL,NULL,'2026-10-09 11:18:03','2026-10-09 11:18:03'),(69,26,1,'created the group','created',NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03'),(70,26,1,'added Cordelia Legros, Jerel Lehner and Johnathan Krajcik','added',NULL,'2026-10-09 11:23:03','2026-10-09 11:23:03'),(109,26,1,'hi',NULL,NULL,'2026-10-09 11:24:37','2026-10-09 11:24:37');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2024_01_01_000100_create_posts_table',1),(6,'2024_01_01_000200_create_reactions_and_comments_tables',1),(7,'2024_01_01_000300_create_drive_tables',1),(8,'2024_01_01_000400_create_chat_tables',1),(9,'2024_01_01_000500_create_alerts_table',1),(10,'2024_01_02_000100_add_articles_to_posts_table',2),(11,'2024_01_02_000200_widen_posts_body_column',3),(12,'2024_02_01_000100_create_movies_tables',4),(13,'2024_02_01_000200_create_music_tables',4),(14,'2024_02_01_000300_create_movie_comments_table',5),(15,'2024_03_01_000100_add_admin_fields_to_users_table',6),(16,'2024_03_01_000200_create_settings_and_admin_logs_tables',6),(17,'2024_04_01_000100_add_group_chats',7);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movie_comments`
--

DROP TABLE IF EXISTS `movie_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `movie_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movie_comments_user_id_foreign` (`user_id`),
  KEY `movie_comments_parent_id_foreign` (`parent_id`),
  KEY `movie_comments_movie_id_parent_id_index` (`movie_id`,`parent_id`),
  CONSTRAINT `movie_comments_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `movie_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movie_comments`
--

LOCK TABLES `movie_comments` WRITE;
/*!40000 ALTER TABLE `movie_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `movie_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movie_likes`
--

DROP TABLE IF EXISTS `movie_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_likes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `movie_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `movie_likes_movie_id_user_id_unique` (`movie_id`,`user_id`),
  KEY `movie_likes_user_id_foreign` (`user_id`),
  CONSTRAINT `movie_likes_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_likes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movie_likes`
--

LOCK TABLES `movie_likes` WRITE;
/*!40000 ALTER TABLE `movie_likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `movie_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movie_subtitles`
--

DROP TABLE IF EXISTS `movie_subtitles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_subtitles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `movie_id` bigint(20) unsigned NOT NULL,
  `label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lang` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movie_subtitles_movie_id_foreign` (`movie_id`),
  CONSTRAINT `movie_subtitles_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movie_subtitles`
--

LOCK TABLES `movie_subtitles` WRITE;
/*!40000 ALTER TABLE `movie_subtitles` DISABLE KEYS */;
INSERT INTO `movie_subtitles` VALUES (1,1,'Sinhala','si','movies/subs/2c4e545d-3d9e-4c57-bc96-e01373df6d96.vtt','2026-10-09 06:59:31','2026-10-09 06:59:31'),(2,1,'English','en','movies/subs/c7924323-d39c-4381-93f9-f7c93b3fd7f1.vtt','2026-10-09 06:59:31','2026-10-09 06:59:31'),(3,2,'English','en','movies/subs/7bbaef36-250f-4b3f-9474-d3a6f7d8aaa3.vtt','2026-10-09 06:59:59','2026-10-09 06:59:59'),(4,4,'Sinhala','si','movies/subs/46a400be-d8c4-47bc-8f5b-e8b14cdabd6b.vtt','2026-10-09 07:00:57','2026-10-09 07:00:57'),(5,5,'Tamil','ta','movies/subs/dea6e4f9-09c6-481c-8f79-e31ca64da7f7.vtt','2026-10-09 07:01:33','2026-10-09 07:01:33'),(6,5,'English','en','movies/subs/c7603e58-533b-42de-8ded-c0fb38024b53.vtt','2026-10-09 07:01:33','2026-10-09 07:01:33'),(7,6,'English','en','movies/subs/1ccd864d-0816-45de-9f67-9107411950f6.vtt','2026-10-09 07:02:07','2026-10-09 07:02:07'),(8,6,'English (Forced)','en','movies/subs/70923d36-0dc7-4f9d-a38c-877547ac0d11.vtt','2026-10-09 07:02:07','2026-10-09 07:02:07'),(9,7,'English','en','movies/subs/d5a6249c-752b-4646-a232-8a25b03bb76f.vtt','2026-10-09 07:02:30','2026-10-09 07:02:30');
/*!40000 ALTER TABLE `movie_subtitles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movies`
--

DROP TABLE IF EXISTS `movies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `video_mime` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `thumbnail_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int(10) unsigned DEFAULT NULL,
  `views` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movies_user_id_foreign` (`user_id`),
  KEY `movies_created_at_index` (`created_at`),
  KEY `movies_views_index` (`views`),
  KEY `movies_category_index` (`category`),
  CONSTRAINT `movies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movies`
--

LOCK TABLES `movies` WRITE;
/*!40000 ALTER TABLE `movies` DISABLE KEYS */;
INSERT INTO `movies` VALUES (1,1,'Chickenhare And The Hamster Of Darkness (2022)','A clumsy young hare sets out on a quest to prove himself, with a hamster and a skunk for company.','Animation','movies/videos/52cd9fdc-44c7-4e58-85fb-d17369467c58.mp4','video/mp4',886369475,'movies/thumbs/ee18e17b-a895-4278-badb-084743fb2d7f.jpg',5518,0,'2026-10-09 06:59:30','2026-10-09 07:32:01'),(2,1,'Luck (2022)','The unluckiest person in the world stumbles into the Land of Luck and tries to turn her fortune around.','Animation','movies/videos/d0a16543-cdbe-4211-b24a-1db437a58768.mp4','video/mp4',1014979226,'movies/thumbs/21beaf0a-3f5c-4609-aaa2-89dc309b5a51.jpg',6324,2,'2026-10-09 06:59:59','2026-10-09 10:26:39'),(3,1,'Death On The Nile (2022)','A honeymoon cruise on the Nile turns into a murder investigation for a famous Belgian detective.','Thriller','movies/videos/b19dc2a5-3e2b-4b01-a856-8a0dc0cdd9d5.mp4','video/mp4',1223620694,'movies/thumbs/c6884c82-8eb4-4905-ac3d-5510ea2b7cec.jpg',7618,0,'2026-10-09 07:00:32','2026-10-09 07:31:59'),(4,1,'Eiffel (2021)','A period drama about the engineer behind the Eiffel Tower and a love story from his past.','Romance','movies/videos/fbbb6261-cd48-4b71-aa0c-52a34b21c5be.mp4','video/mp4',1036903223,'movies/thumbs/d055fcf9-e14f-46a6-8c0f-f3b753f6d76f.jpg',6463,0,'2026-10-09 07:00:57','2026-10-09 07:31:57'),(5,1,'Maaran (2022)','An investigative journalist takes on powerful people and pays a personal price.','Thriller','movies/videos/54d4846d-7cc8-4661-b39a-f50af1841063.mp4','video/mp4',1254364942,'movies/thumbs/5674ae18-f9f9-4364-b238-c2ebdb20f7e9.jpg',7816,0,'2026-10-09 07:01:33','2026-10-09 07:31:56'),(6,1,'Uncharted (2022)','A street-smart treasure hunter joins a veteran fortune seeker in search of a lost fortune.','Action','movies/videos/61c5a828-9747-4a39-b77e-cd310254839c.mp4','video/mp4',1116596468,'movies/thumbs/0a3ab59b-42dc-4945-9312-1188c525adc7.jpg',6951,1,'2026-10-09 07:02:07','2026-10-09 07:33:14'),(7,1,'Watcher (2022)','A young woman in a new city becomes convinced she is being watched from the building across the street.','Horror','movies/videos/88347ec9-15ae-47c5-ab75-2ba04e6b1b64.mp4','video/mp4',926238878,'movies/thumbs/1446b1b3-7e6e-46c8-b538-ee25ff9760b6.jpg',5774,0,'2026-10-09 07:02:30','2026-10-09 07:31:53'),(8,1,'Encanto (2021)','The one member of a magical family without a gift may be the only hope when their magic starts to fade.','Animation','movies/videos/07321315-297a-4348-b5bb-9b8c883913b7.mp4','video/mp4',982780734,'movies/thumbs/8b75f820-1c4d-4b97-a2f3-f126fff74a28.jpg',6127,0,'2026-10-09 07:02:58','2026-10-09 07:31:52');
/*!40000 ALTER TABLE `movies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `playlist_song`
--

DROP TABLE IF EXISTS `playlist_song`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `playlist_song` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `playlist_id` bigint(20) unsigned NOT NULL,
  `song_id` bigint(20) unsigned NOT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `playlist_song_playlist_id_song_id_unique` (`playlist_id`,`song_id`),
  KEY `playlist_song_song_id_foreign` (`song_id`),
  KEY `playlist_song_playlist_id_position_index` (`playlist_id`,`position`),
  CONSTRAINT `playlist_song_playlist_id_foreign` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `playlist_song_song_id_foreign` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `playlist_song`
--

LOCK TABLES `playlist_song` WRITE;
/*!40000 ALTER TABLE `playlist_song` DISABLE KEYS */;
INSERT INTO `playlist_song` VALUES (1,1,232,1,'2026-10-09 07:21:17','2026-10-09 07:21:17'),(2,1,235,2,'2026-10-09 07:21:20','2026-10-09 07:21:20'),(3,1,226,3,'2026-10-09 07:21:22','2026-10-09 07:21:22'),(4,1,231,4,'2026-10-09 07:21:24','2026-10-09 07:21:24'),(5,1,222,5,'2026-10-09 07:21:26','2026-10-09 07:21:26'),(6,1,206,6,'2026-10-09 07:21:28','2026-10-09 07:21:28');
/*!40000 ALTER TABLE `playlist_song` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `playlists`
--

DROP TABLE IF EXISTS `playlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `playlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plays` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `playlists_user_id_foreign` (`user_id`),
  CONSTRAINT `playlists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `playlists`
--

LOCK TABLES `playlists` WRITE;
/*!40000 ALTER TABLE `playlists` DISABLE KEYS */;
INSERT INTO `playlists` VALUES (1,1,'Evening classics','Calm Sinhala classics for the drive home.',0,'2026-10-09 07:21:17','2026-10-09 07:21:28');
/*!40000 ALTER TABLE `playlists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_images`
--

DROP TABLE IF EXISTS `post_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_images_post_id_foreign` (`post_id`),
  CONSTRAINT `post_images_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_images`
--

LOCK TABLES `post_images` WRITE;
/*!40000 ALTER TABLE `post_images` DISABLE KEYS */;
INSERT INTO `post_images` VALUES (1,11,'posts/a9851184-c81d-4046-8dd9-242fc9ef1480.jpg',0,'2026-10-09 01:02:09','2026-10-09 01:02:09');
/*!40000 ALTER TABLE `post_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `body` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `posts_user_id_foreign` (`user_id`),
  KEY `posts_created_at_index` (`created_at`),
  CONSTRAINT `posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posts`
--

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
INSERT INTO `posts` VALUES (1,1,NULL,'standard','The new clinic dashboard is live on the intranet. Attendance, waiting times and referrals all update every fifteen minutes.\n\nIf a figure looks wrong, tell me rather than working around it - that is usually a mapping problem I can fix in a few minutes.',NULL,'2026-10-08 22:03:16','2026-10-08 22:03:16'),(2,6,NULL,'colored','World Health Day programme this Friday, 9 a.m. at the main hall. Everyone welcome.','citrus','2026-10-08 19:03:16','2026-10-08 19:03:16'),(3,2,NULL,'standard','Stock check finished for the quarter. Two things worth knowing:\n\n1. Paracetamol syrup is down to six weeks of cover, order is placed.\n2. Please stop keeping ward stock in the old cupboards - we cannot count what we cannot see.\n\nThe full reconciliation sheet is in my drive, published to the network.',NULL,'2026-10-08 15:03:17','2026-10-08 15:03:17'),(4,3,NULL,'standard','Scheduled maintenance on the file server this Saturday from 7 p.m. Expect about an hour of downtime.\n\nSaverMate, the HMIS and the lab system will all be unavailable. Nothing is lost - the queue picks up once it is back.',NULL,'2026-10-07 22:03:17','2026-10-07 22:03:17'),(5,4,NULL,'colored','Ward 4 hit 100 days without a single pressure ulcer. Proud of this team.','mint','2026-10-07 18:03:18','2026-10-07 18:03:18'),(6,8,NULL,'standard','Dengue larval survey for the quarter is done. Three premises in the Kadawatha division need follow-up visits.\n\nI have put the field maps and the inspection photos on the network drive so the whole team can see the hotspots.',NULL,'2026-10-06 20:03:18','2026-10-06 20:03:18'),(7,5,NULL,'standard','Reminder from the lab: please write the collection time on every sample, not just the date. We are rejecting about a dozen samples a week purely for this.',NULL,'2026-10-05 22:03:18','2026-10-05 22:03:18'),(8,7,NULL,'colored','Travel claims for this month close on the 25th. Forms are on the network drive.','ocean','2026-10-05 00:03:18','2026-10-05 00:03:18'),(9,1,NULL,'colored','Welcome to SaverMate. Say hello, share what you are working on, and keep your files where colleagues can find them.','violet','2026-10-04 00:03:19','2026-10-04 00:03:19'),(10,1,NULL,'colored','eefkwjesfw','berry','2026-10-09 00:42:45','2026-10-09 00:42:45'),(11,1,NULL,'standard',NULL,NULL,'2026-10-09 01:02:08','2026-10-09 01:02:08'),(15,15,'My Modern Article','article','<h2>Introduction</h2><p>This is a modern article with <span style=\"color: #2563eb; font-weight: bold;\">blue colored text</span>.</p><ul><li>First point</li><li>Second point</li></ul>',NULL,'2026-10-09 02:00:26','2026-10-09 02:00:26'),(16,18,'My Modern Article','article','<h2>Introduction</h2><p>This is a modern article with <span style=\"color: #2563eb; font-weight: bold;\">blue colored text</span>.</p><ul><li>First point</li><li>Second point</li></ul>',NULL,'2026-10-09 02:01:16','2026-10-09 02:01:16');
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reactions`
--

DROP TABLE IF EXISTS `reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'like',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reactions_post_id_user_id_unique` (`post_id`,`user_id`),
  KEY `reactions_user_id_foreign` (`user_id`),
  CONSTRAINT `reactions_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reactions`
--

LOCK TABLES `reactions` WRITE;
/*!40000 ALTER TABLE `reactions` DISABLE KEYS */;
INSERT INTO `reactions` VALUES (1,1,2,'love','2026-10-08 22:20:16','2026-10-08 22:20:16'),(2,1,3,'fire','2026-10-08 22:27:16','2026-10-08 22:27:16'),(3,1,4,'fire','2026-10-08 22:34:16','2026-10-09 01:10:44'),(4,1,5,'haha','2026-10-08 22:41:16','2026-10-09 01:10:44'),(5,1,6,'like','2026-10-08 22:48:16','2026-10-09 01:10:44'),(6,1,7,'like','2026-10-08 22:55:16','2026-10-08 22:55:16'),(7,2,1,'fire','2026-10-08 19:13:16','2026-10-09 10:59:45'),(8,2,2,'fire','2026-10-08 19:20:16','2026-10-08 19:20:16'),(9,2,3,'fire','2026-10-08 19:27:16','2026-10-09 01:10:44'),(10,2,4,'haha','2026-10-08 19:34:16','2026-10-09 01:10:44'),(11,2,5,'like','2026-10-08 19:41:16','2026-10-09 01:10:44'),(12,3,1,'fire','2026-10-08 15:13:17','2026-10-08 15:13:17'),(13,3,3,'haha','2026-10-08 15:27:17','2026-10-09 01:10:44'),(14,3,4,'like','2026-10-08 15:34:17','2026-10-09 01:10:44'),(15,3,5,'like','2026-10-08 15:41:17','2026-10-08 15:41:17'),(16,4,1,'fire','2026-10-07 22:13:17','2026-10-09 01:11:10'),(17,4,2,'haha','2026-10-07 22:20:17','2026-10-09 01:10:44'),(18,4,4,'like','2026-10-07 22:34:17','2026-10-07 22:34:17'),(19,5,1,'haha','2026-10-07 18:13:18','2026-10-09 01:10:44'),(20,5,2,'like','2026-10-07 18:20:18','2026-10-09 01:10:44'),(21,5,3,'like','2026-10-07 18:27:18','2026-10-07 18:27:18'),(22,6,1,'like','2026-10-06 20:13:18','2026-10-09 01:10:44'),(23,6,2,'like','2026-10-06 20:20:18','2026-10-06 20:20:18'),(24,7,1,'love','2026-10-05 22:13:18','2026-10-09 08:36:25'),(25,7,2,'love','2026-10-05 22:20:18','2026-10-05 22:20:18'),(26,8,1,'love','2026-10-05 00:13:18','2026-10-05 00:13:18'),(27,8,2,'fire','2026-10-05 00:20:18','2026-10-05 00:20:18'),(28,9,2,'fire','2026-10-04 00:20:19','2026-10-09 01:10:44'),(29,1,1,'fire','2026-10-09 00:38:28','2026-10-09 00:38:28'),(30,10,1,'like','2026-10-09 01:11:54','2026-10-09 03:04:05'),(47,16,1,'love','2026-10-09 08:17:11','2026-10-09 08:17:11'),(50,9,1,'fire','2026-10-09 08:36:33','2026-10-09 08:36:39');
/*!40000 ALTER TABLE `reactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('announcement','','2026-10-09 10:34:40','2026-10-09 11:23:41'),('announcement_tone','info','2026-10-09 10:34:40','2026-10-09 11:23:41'),('registration_open','1','2026-10-09 10:34:39','2026-10-09 11:23:41');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `song_likes`
--

DROP TABLE IF EXISTS `song_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `song_likes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `song_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `song_likes_song_id_user_id_unique` (`song_id`,`user_id`),
  KEY `song_likes_user_id_foreign` (`user_id`),
  CONSTRAINT `song_likes_song_id_foreign` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `song_likes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `song_likes`
--

LOCK TABLES `song_likes` WRITE;
/*!40000 ALTER TABLE `song_likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `song_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `songs`
--

DROP TABLE IF EXISTS `songs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `songs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `artist` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `duration` int(10) unsigned DEFAULT NULL,
  `cover_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plays` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `songs_user_id_foreign` (`user_id`),
  KEY `songs_artist_index` (`artist`),
  KEY `songs_plays_index` (`plays`),
  KEY `songs_created_at_index` (`created_at`),
  CONSTRAINT `songs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=333 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `songs`
--

LOCK TABLES `songs` WRITE;
/*!40000 ALTER TABLE `songs` DISABLE KEYS */;
INSERT INTO `songs` VALUES (119,1,'Adaraye Unusum Susum','Amarasiri Piris',NULL,NULL,'music/tracks/eccc1609-1eb5-4fad-8db8-8a863d18db3a.mp3','audio/mpeg',3534183,216,NULL,0,'2026-10-09 06:58:42','2026-10-09 06:58:42'),(120,1,'Aya Yanna Giya Makila','Amarasiri Piris',NULL,'Classical','music/tracks/23b57710-133e-4410-82e8-bd06d7bbfb6d.mp3','audio/mpeg',4341610,268,NULL,0,'2026-10-09 06:58:43','2026-10-09 06:58:43'),(121,1,'Hanthanata Payana Sanda','Amarasiri Piris',NULL,NULL,'music/tracks/d75b00cc-1b12-462e-85cd-b0d6559f6c0b.mp3','audio/mpeg',3333740,207,'music/covers/7214c20e9e0a36226000a9b8a14aec5f.png',0,'2026-10-09 06:58:43','2026-10-09 06:58:43'),(122,1,'Iki Gasa Handana','Amarasiri Peiris',NULL,NULL,'music/tracks/cbaad65a-ac41-4d77-a7ea-6f572c152393.mp3','audio/mpeg',4105045,252,NULL,0,'2026-10-09 06:58:43','2026-10-09 06:58:43'),(123,1,'Iri Thalunu Wala','Amarasiri Piris',NULL,NULL,'music/tracks/a877867d-0696-4bd7-9d3b-f196f0794b2e.mp3','audio/mpeg',3358920,206,NULL,0,'2026-10-09 06:58:43','2026-10-09 06:58:43'),(124,1,'Kala Wawa Hindenawa','Amarasiri Pieris',NULL,NULL,'music/tracks/282008eb-0b3c-491d-af55-29949b14463a.mp3','audio/mpeg',3804115,237,'music/covers/064faa1fea1b5dbe170cc3cfd5e28d17.jpg',0,'2026-10-09 06:58:43','2026-10-09 06:58:43'),(125,1,'Ase Madura Jeewanaye Geetha','Ananda Samarakoon',NULL,NULL,'music/tracks/f80065fb-05e4-406a-86c9-659e0a79c6f1.mp3','audio/mpeg',3232416,197,NULL,0,'2026-10-09 06:58:43','2026-10-09 06:58:43'),(126,1,'Besa Seethala Gangule','Ananda Samarakoon',NULL,NULL,'music/tracks/ee8650f9-ba68-423b-9615-6bc2bb6f6537.mp3','audio/mpeg',3573053,218,NULL,0,'2026-10-09 06:58:44','2026-10-09 06:58:44'),(127,1,'Idiriyata Yamu Sawoma Original','Ananda Samarakoon',NULL,NULL,'music/tracks/38252a96-9568-431c-a9be-097beef12a3a.mp3','audio/mpeg',3104417,190,NULL,0,'2026-10-09 06:58:44','2026-10-09 06:58:44'),(128,1,'Kellane Numbala','Ananda Samarakoon',NULL,NULL,'music/tracks/11c53f70-9334-49eb-a945-ad45f6c40ea6.mp3','audio/mpeg',3551319,217,NULL,0,'2026-10-09 06:58:44','2026-10-09 06:58:44'),(129,1,'Namo Namo Maatha','Ananda Samarakoon',NULL,NULL,'music/tracks/8ba4cfbd-9301-4d67-80fe-463c9dc541e1.mp3','audio/mpeg',3092818,188,NULL,0,'2026-10-09 06:58:44','2026-10-09 06:58:44'),(130,1,'Peradiga Aakase','Ananda Samarakoon',NULL,NULL,'music/tracks/eec2ca26-9add-48ae-96ad-db7826d4a249.mp3','audio/mpeg',4047019,248,NULL,0,'2026-10-09 06:58:44','2026-10-09 06:58:44'),(131,1,'Athul Hithe Amma','Asanka Priyamantha',NULL,NULL,'music/tracks/0dea0c03-a2ea-4193-a7f2-f50d45f9d221.mp3','audio/mpeg',3685609,230,NULL,0,'2026-10-09 06:58:44','2026-10-09 06:58:44'),(132,1,'Deepawali Daata','Asanka Priyamantha',NULL,NULL,'music/tracks/2f86404f-e89b-4c1a-9f9e-b657b8d2d3e4.mp3','audio/mpeg',3664681,224,NULL,0,'2026-10-09 06:58:45','2026-10-09 06:58:45'),(133,1,'Kaviyak wee Tholpethi Matha Mumunanna','Asanka Priyamantha',NULL,NULL,'music/tracks/0acc8b11-c3d3-41ac-9cc8-399f8957439c.mp3','audio/mpeg',3692425,227,NULL,0,'2026-10-09 06:58:45','2026-10-09 06:58:45'),(134,1,'Labunath Neth Yuga','Asanka Priyamantha',NULL,NULL,'music/tracks/8119ab7f-3d99-4bf0-9957-5877ecd2aea9.mp3','audio/mpeg',4644082,287,NULL,0,'2026-10-09 06:58:45','2026-10-09 06:58:45'),(135,1,'Punchi Hadakariye','Asanka Priyamantha',NULL,NULL,'music/tracks/5585a559-d714-4ea6-ab44-8f80b6c248af.mp3','audio/mpeg',3712327,227,NULL,0,'2026-10-09 06:58:45','2026-10-09 06:58:45'),(136,1,'Senehasakata Aruthak Purawannata','Asanka Priyamantha',NULL,NULL,'music/tracks/70c334a2-de45-4c55-a197-780c7ebabffb.mp3','audio/mpeg',3632203,223,NULL,0,'2026-10-09 06:58:46','2026-10-09 06:58:46'),(137,1,'Alen Wela Ganna','Athula Adikari',NULL,NULL,'music/tracks/bdd4c5d0-b7b8-49a2-8e29-61784f25a364.mp3','audio/mpeg',2928619,178,NULL,0,'2026-10-09 06:58:46','2026-10-09 06:58:46'),(138,1,'Handa Panak se Awidin','Athula Adikari',NULL,NULL,'music/tracks/17525eac-46a4-4f75-bc09-dc5bb588ae7f.mp3','audio/mpeg',3833996,235,NULL,0,'2026-10-09 06:58:46','2026-10-09 06:58:46'),(139,1,'Hitha Assata Heena Godak','Athula Adikari',NULL,NULL,'music/tracks/89dedcba-485a-4fec-ae3a-a54c43625c0e.mp3','audio/mpeg',3722828,231,NULL,0,'2026-10-09 06:58:46','2026-10-09 06:58:46'),(140,1,'Ira Paaya','Athula Adikari',NULL,NULL,'music/tracks/a51d6a49-653a-4c4c-880e-d624a1dca0e3.mp3','audio/mpeg',3697013,231,NULL,0,'2026-10-09 06:58:46','2026-10-09 06:58:46'),(141,1,'Pinna Pipena Ra Yame','Athula Adikari',NULL,NULL,'music/tracks/5a4d4c62-534e-4b1a-96a7-27f02b0d326f.mp3','audio/mpeg',2949294,181,NULL,0,'2026-10-09 06:58:46','2026-10-09 06:58:46'),(142,1,'Sanda Paanak Se','Athula Adikari',NULL,NULL,'music/tracks/f7f97366-0571-4d4e-8572-7ba5cf6124b9.mp3','audio/mpeg',4052279,253,NULL,0,'2026-10-09 06:58:47','2026-10-09 06:58:47'),(143,1,'Nihanda Gamane','Bachi Susan ft Shanika Madhumali',NULL,NULL,'music/tracks/b1b49b38-9076-47a0-97cf-f5502bf8203c.mp3','audio/mpeg',3622021,222,'music/covers/aa397dd7d69a12663e826093b1ca7dbf.jpg',0,'2026-10-09 06:58:47','2026-10-09 06:58:47'),(144,1,'Hiru Hinahena Gana Nilwan Ahase Bandara Athawuda','Bachi Susan',NULL,NULL,'music/tracks/183dc114-f18c-4913-b2ea-f7d5f6a59924.mp3','audio/mpeg',3174589,195,NULL,0,'2026-10-09 06:58:47','2026-10-09 06:58:47'),(145,1,'Visekari','Bachi Susan ft Rude Bwoy Shiraz',NULL,NULL,'music/tracks/68019e95-5b62-4938-8fba-761445b90c2b.mp3','audio/mpeg',4374911,273,NULL,0,'2026-10-09 06:58:47','2026-10-09 06:58:47'),(146,1,'Maga Bala Handana','Bachi Susan',NULL,NULL,'music/tracks/23233cc9-2a82-4e17-b838-efc570b0bab9.mp3','audio/mpeg',3273104,203,NULL,0,'2026-10-09 06:58:47','2026-10-09 06:58:47'),(147,1,'Ashawe Mal Waten Ebi Nayana Kalu','Bandara Arharuda',NULL,NULL,'music/tracks/348910a0-f6af-4487-afb8-89593e8f2ba3.mp3','audio/mpeg',3133425,191,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(148,1,'Kandulu Pisadalana','Bandara Arharuda',NULL,NULL,'music/tracks/14bff586-a7ca-4ea6-a57b-dacaf60350dc.mp3','audio/mpeg',2727556,166,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(149,1,'Kohe Yanneda Kohe Inneda','Bandara Arharuda',NULL,NULL,'music/tracks/34010de2-432f-41d1-80c8-07efd5dbb68e.mp3','audio/mpeg',3106646,189,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(150,1,'Oba Atha Sanda Wee Paya','Bandara Arharuda',NULL,NULL,'music/tracks/acb2009c-dc62-499d-aae9-e5826852330c.mp3','audio/mpeg',3149722,192,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(151,1,'Ruwan Tharaka Nuwan Payala','Bandara Arharuda',NULL,NULL,'music/tracks/15471679-55d1-48b0-97d6-5319877adfc3.mp3','audio/mpeg',3109571,189,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(152,1,'Sihinayaki Oba Nihanda Madiyam Ra','Bandara Arharuda',NULL,NULL,'music/tracks/bda78854-4875-4d3d-b6d6-dd87e50020a3.mp3','audio/mpeg',3515657,216,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(153,1,'Jaya Jaya Sri Lanka','Centigrade',NULL,NULL,'music/tracks/f6cf20ac-b039-4c36-8c85-686a7d8eb471.mp3','audio/mpeg',3736534,229,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(154,1,'Kotuwa Centigradz','Centigrade',NULL,NULL,'music/tracks/ca6390cc-6b15-4ad9-9434-294acdbb906e.mp3','audio/mpeg',3120880,190,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(155,1,'Withoilding','Centigrade',NULL,NULL,'music/tracks/e80f14c9-fe4c-492e-b7ca-e8266f20fe68.wav','audio/mpeg',5276943,NULL,NULL,0,'2026-10-09 06:58:48','2026-10-09 06:58:48'),(156,1,'Weralu Gedi Pahena Kale','Chamara Ranawaka',NULL,NULL,'music/tracks/bf56566e-4ab6-4729-ae4f-e504c339b1f7.mp3','audio/mpeg',3959547,244,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(157,1,'Api Wen Wena Tharamata','Chamika',NULL,NULL,'music/tracks/3455fdf7-f6bf-4468-9ffd-675855d389db.mp3','audio/mpeg',3476875,212,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(158,1,'Aye Enna Pana Mage','Chamika',NULL,NULL,'music/tracks/74d76535-5938-4f01-9213-abbe416a2298.mp3','audio/mpeg',3809677,233,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(159,1,'Hitha Hadaganna Ba','Chamika',NULL,NULL,'music/tracks/4d897f62-6e06-48e0-a034-db81297d3ccc.mp3','audio/mpeg',3710202,227,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(160,1,'Mage Duppath As Deka','Chamika',NULL,NULL,'music/tracks/50abbdca-095e-4743-803a-d428c329af24.mp3','audio/mpeg',3801212,233,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(161,1,'Samawela Mata Kiyanna','Chamika',NULL,NULL,'music/tracks/619886bd-c952-4f2d-9023-eaf1eb1bf24d.mp3','audio/mpeg',3255357,199,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(162,1,'Amawakata Chamara Weerasingha','Charmara Weerasinghe',NULL,NULL,'music/tracks/26f96cab-79cc-4e2a-88b5-13d67a4b2332.mp3','audio/mpeg',4979205,306,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(163,1,'Budun Deka Niwan Dakinna','Charmara Weerasinghe',NULL,NULL,'music/tracks/ed5446f1-a3c9-4ba1-bf85-8d7d444e4688.mp3','audio/mpeg',3590283,219,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(164,1,'Dakune Pin','Charmara Weerasinghe',NULL,NULL,'music/tracks/35c78e02-e4cf-4ffe-80e5-e48793a7b4f5.mp3','audio/mpeg',4268674,262,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(165,1,'Guru Gedarata Yana','Charmara Weerasinghe',NULL,NULL,'music/tracks/36937f0e-3045-42ad-9bff-efe16e3d4219.mp3','audio/mpeg',3256193,199,NULL,0,'2026-10-09 06:58:49','2026-10-09 06:58:49'),(166,1,'Kawurunda Kawuluwa Wahuwe','Charmara Weerasinghe',NULL,NULL,'music/tracks/15b64905-31cc-40d4-b969-d27eeb3ed20e.mp3','audio/mpeg',3376233,206,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(167,1,'Adare Na Adare','Damith Asanka',NULL,NULL,'music/tracks/e61cf1c5-ce22-46fc-bd8b-e2aab78475f6.mp3','audio/mpeg',3449637,212,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(168,1,'Alapili Ispili Bala Bala','Damith Asanka',NULL,NULL,'music/tracks/b401b7e6-3f48-4002-a2a6-8b462a45fa38.mp3','audio/mpeg',3479801,213,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(169,1,'Bageta Salun Doren Ringa','Damith Asanka',NULL,NULL,'music/tracks/5d0d0b0d-deac-49dd-a4ef-10d79ecd6621.mp3','audio/mpeg',3049721,186,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(170,1,'Mata Dunna Duk Ginna','Damith Asanka',NULL,NULL,'music/tracks/b46e0130-dbf4-4789-97f7-bc3d3fcd0e5e.mp3','audio/mpeg',4088767,251,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(171,1,'Mata Heenayak Wela','Damith Asanka',NULL,NULL,'music/tracks/5e9c04ba-d6b8-4e2d-8aaf-8b57843a63c0.mp3','audio/mpeg',4441213,273,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(172,1,'Nuwara Eliye','Damith Asanka',NULL,NULL,'music/tracks/0913c8fe-1402-4eed-a96e-f6965ca6186b.mp3','audio/mpeg',5535393,230,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(173,1,'Ahasa Numba Metharam','Deepika',NULL,NULL,'music/tracks/fe6ff1fa-dcb1-4d35-b7f5-4a0df9d99674.mp3','audio/mpeg',3545332,218,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(174,1,'Ahasai Oba Mata','Deepika',NULL,NULL,'music/tracks/ef7c55bb-355b-440f-b2dd-9ea44375d879.mp3','audio/mpeg',3715831,229,NULL,0,'2026-10-09 06:58:50','2026-10-09 06:58:50'),(175,1,'Anna Balan Idikada Langa','Deepika',NULL,NULL,'music/tracks/1c63e02d-dda8-4585-a9f1-3ac24309f840.mp3','audio/mpeg',3763895,232,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(176,1,'Boralu Anena Paya Ridawana','Deepika',NULL,NULL,'music/tracks/f68a1b74-70d8-4c0b-9143-cf0be774d278.mp3','audio/mpeg',3337708,204,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(177,1,'Jaya Sirima Hamuduruwane','Deepika',NULL,NULL,'music/tracks/a77cfba5-a6a0-4f12-a3a2-87ffdc9580ad.mp3','audio/mpeg',3950750,243,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(178,1,'Ma Sithata Ahamben','Deepika',NULL,NULL,'music/tracks/07b998fb-d899-4033-883c-f384ccaeb50e.mp3','audio/mpeg',3229744,198,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(179,1,'Api Katha Nokara Imu','Janka Wikramasinha',NULL,NULL,'music/tracks/4a0ad6bd-b264-440f-bd56-65a25d8ae8c8.mp3','audio/mpeg',3339832,204,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(180,1,'Dewure Dilena','Janka Wikramasinha',NULL,NULL,'music/tracks/5c59b716-8d20-4b9e-9730-51c50b475297.mp3','audio/mpeg',4172824,256,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(181,1,'Kirula Muthu Lihi','Janka Wikramasinha',NULL,NULL,'music/tracks/cd4b01a8-e926-4f02-a31f-e2120301db35.mp3','audio/mpeg',3337324,204,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(182,1,'Kohe Ho Oba','Janka Wikramasinha',NULL,NULL,'music/tracks/3ccd381f-c7ae-405e-9079-e5443b0e452a.mp3','audio/mpeg',4010656,246,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(183,1,'Adaraya Sundara Waradaki','Karunaratna Diulgane',NULL,NULL,'music/tracks/9cacc1d4-3839-4ba1-a1d2-7d07f4b3d48f.mp3','audio/mpeg',3700948,226,NULL,0,'2026-10-09 06:58:51','2026-10-09 06:58:51'),(184,1,'Andara Yaye Waw Thawalle','Karunaratna Diulgane',NULL,NULL,'music/tracks/2acedd24-d102-47ac-bc61-f06f1a06ab8b.mp3','audio/mpeg',4629933,286,NULL,0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(185,1,'Asata Asuwana Maime','Karunaratna Diulgane',NULL,NULL,'music/tracks/06880ab0-ed4e-4fa6-8f3f-88c0db27dc9d.mp3','audio/mpeg',3840799,237,NULL,0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(186,1,'Ektam Malige Sonduru Hirakariye','Karunaratna Diulgane',NULL,NULL,'music/tracks/74157a37-39ce-48b1-999d-efefe7b95d90.mp3','audio/mpeg',3584570,223,NULL,0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(187,1,'Kohe Ho Numba Nathara Vee','Karunaratna Diulgane',NULL,NULL,'music/tracks/7dae2fd0-8946-48f7-8a49-5188ae483790.mp3','audio/mpeg',4784507,199,'music/covers/7214c20e9e0a36226000a9b8a14aec5f.png',0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(188,1,'La Dalu Bopath','Karunaratna Diulgane',NULL,NULL,'music/tracks/15285eb1-5813-46d0-bb9f-663d53216104.mp3','audio/mpeg',4341960,268,NULL,0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(189,1,'Nube Sithata',NULL,NULL,NULL,'music/tracks/af8e2edc-9a29-4281-b8e2-4b3becc2a758.mp3','audio/mpeg',2761840,173,NULL,0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(190,1,'Palu Reyedi','11 Palu Reyedi',NULL,NULL,'music/tracks/01d9483b-c9b6-461b-875a-bcc1545c8469.mp3','audio/mpeg',4632207,193,NULL,0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(191,1,'Usata','14Ahasa',NULL,NULL,'music/tracks/a7e85d23-6466-4945-a022-0c065606adac.mp3','audio/mpeg',3233828,167,NULL,0,'2026-10-09 06:58:52','2026-10-09 06:58:52'),(192,1,'Malata Suvada Se','Sunil Edirisingha',NULL,NULL,'music/tracks/24330956-eef7-4642-89b5-bd1a47b01319.mp3','audio/mpeg',3392585,212,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(193,1,'Kavrundha Oba',NULL,NULL,NULL,'music/tracks/d87c966b-aa7e-4f08-834f-988bd13a98ff.mp3','audio/mpeg',3186231,199,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(194,1,'Ahasa Pulawa',NULL,NULL,NULL,'music/tracks/191ada82-b01d-459a-ae4b-2b764b67e4a0.mp3','audio/mpeg',3156974,197,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(195,1,'Duhul Malaka Mal Peththaka','Nirosha',NULL,NULL,'music/tracks/563ce2de-af5d-4104-94c1-13d20d413e9f.mp3','audio/mpeg',4007390,245,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(196,1,'Igilenna Thahanam Nam Durak','Nirosha',NULL,NULL,'music/tracks/6efce38e-8809-4499-8c74-82df18448d19.mp3','audio/mpeg',3983024,244,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(197,1,'Man Kirilliyak','Nirosha',NULL,NULL,'music/tracks/18c7a91a-020d-49e3-9ca6-362dc349c402.mp3','audio/mpeg',3418921,209,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(198,1,'Mindadayo Awidin','Nirosha',NULL,NULL,'music/tracks/e503f959-75fd-4128-8c40-8cb9ce5a142e.mp3','audio/mpeg',3119847,191,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(199,1,'Punsanda Rata Awidin Hene','Nirosha',NULL,NULL,'music/tracks/a771a2fe-32cc-4c95-a017-c9b3b5b1012e.mp3','audio/mpeg',4359803,272,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(200,1,'Sina Thotak Wiya','Nirosha',NULL,NULL,'music/tracks/2eb8c15f-10c7-483f-9fa6-831e66f743c5.mp3','audio/mpeg',4377761,269,NULL,0,'2026-10-09 06:58:53','2026-10-09 06:58:53'),(201,1,'Amathaka Wenne Ne Remix Jayasiri Amarasekara',NULL,NULL,NULL,'music/tracks/61a0cfd3-a0f0-4e28-a898-db611ebf96a0.mp3','audio/mpeg',4178955,258,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(202,1,'Duka Thadakaran Senanayaka Weraliyadda',NULL,NULL,NULL,'music/tracks/a3b290ac-54d9-446d-8fa4-07c802653005.mp3','audio/mpeg',3561987,221,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(203,1,'Ha Ha Balagenai Pradeepa Darmadasa',NULL,NULL,NULL,'music/tracks/bdd76b6e-b6c7-4bed-84fd-181cb8311645.mp3','audio/mpeg',2931581,178,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(204,1,'Nilata Nile Nil Kateta Kada Halena','Kasun Kalhara ft Uresha Ravihari',NULL,NULL,'music/tracks/363bc6b2-a9dd-47d5-b738-fb506987fdd4.mp3','audio/mpeg',7202176,300,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(205,1,'Kaulu Piyan Path Wahanna Kasun Kalhara',NULL,NULL,NULL,'music/tracks/1f944573-4caf-4554-9d68-49a6cc264f09.mp3','audio/mpeg',3693938,226,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(206,1,'Kohe Kothanaka Senanayaka Weraliyadda',NULL,NULL,NULL,'music/tracks/01f951ad-60d0-4f62-9281-313fc08379de.mp3','audio/mpeg',3650237,225,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(207,1,'Adai Amawaka','Prince Udaya',NULL,NULL,'music/tracks/b176be97-074a-4020-bade-45ce68f44e88.mp3','audio/mpeg',4264033,262,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(208,1,'Kandulu Ganga Gala','Prince Udaya',NULL,NULL,'music/tracks/0749438c-ff3b-4e32-b117-eacff0a7f82b.mp3','audio/mpeg',3305141,202,NULL,0,'2026-10-09 06:58:54','2026-10-09 06:58:54'),(209,1,'Kandulu Wel Randala','Prince Udaya Priyantha',NULL,NULL,'music/tracks/481ce9cd-5451-468d-ace5-25b624ee0295.mp3','audio/mpeg',3211826,200,'music/covers/77bbef4781dc2002cd629a1a73c10575.jpg',0,'2026-10-09 06:58:55','2026-10-09 06:58:55'),(210,1,'Ma Eda Kiyu De','Prince Udaya',NULL,NULL,'music/tracks/4a91fead-75b4-48c8-a7c3-7b51c2de4fec.mp3','audio/mpeg',3134836,196,NULL,0,'2026-10-09 06:58:55','2026-10-09 06:58:55'),(211,1,'Ma Handawala','Prince Udaya',NULL,NULL,'music/tracks/b8a1bc37-6a3a-4d8c-bed0-7084568b642f.mp3','audio/mpeg',3887776,238,NULL,0,'2026-10-09 06:58:55','2026-10-09 06:58:55'),(212,1,'Mahada Premadare','Prince Udaya',NULL,NULL,'music/tracks/7b7012de-a8dd-4f3c-b00a-fc6dd7c9366c.mp3','audio/mpeg',3488625,213,NULL,0,'2026-10-09 06:58:55','2026-10-09 06:58:55'),(213,1,'Epa Kandulel','Aathma Liyanage',NULL,NULL,'music/tracks/4b456f0a-a480-49a1-aded-0cc3bdb274a0.mp3','audio/mpeg',4742147,296,NULL,0,'2026-10-09 06:58:55','2026-10-09 06:58:55'),(214,1,'Hitha Dura Handa','Athma ft Thilina R',NULL,'Blues','music/tracks/2c3de5a3-2565-4941-8050-f1063e3cbc58.mp3','audio/mpeg',4304375,260,'music/covers/e844f3ee5bd7b8090ddc5286d3c5e55a.jpg',0,'2026-10-09 06:58:55','2026-10-09 06:58:55'),(215,1,'Liyatambara','Aathma Liyanage',NULL,NULL,'music/tracks/a0ec0a41-62fc-420f-a14c-b85cf318f857.mp3','audio/mpeg',4486879,280,NULL,0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(216,1,'Na Thawath Hithak','Athma Liyanage',NULL,NULL,'music/tracks/1fc580b7-973f-4b98-9dd8-53027e3edd19.mp3','audio/mpeg',4723406,190,'music/covers/c01ff3f446a3082f0fedaf96fc839071.jpg',0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(217,1,'Penena Nopenena Duraka Idan','Athma Liyange ft Thilina R',NULL,NULL,'music/tracks/367d847e-3fe3-448b-9dfb-359c478a3eed.mp3','audio/mpeg',3910769,240,'music/covers/aa397dd7d69a12663e826093b1ca7dbf.jpg',0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(218,1,'Ra Ahase Tharukata Paya Diluna','Athma Liyanage',NULL,NULL,'music/tracks/9e7b2285-7eae-4d6f-80c2-c4be2e1f3f7c.mp3','audio/mpeg',3224005,197,'music/covers/aa397dd7d69a12663e826093b1ca7dbf.jpg',0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(219,1,'Athmayen Athmaye','Athula Sri Gamage',NULL,NULL,'music/tracks/78964b61-a4a0-47d8-b4ed-e75bdaa4a786.mp3','audio/mpeg',3389631,207,NULL,0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(220,1,'Kandulak Dewannada','Athula Sri Gamage',NULL,NULL,'music/tracks/ce2a5e96-bebf-49da-a9f1-ace20672af95.mp3','audio/mpeg',3698088,226,NULL,0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(221,1,'Kara Bandina Raththaran','Athula Sri Gamage',NULL,NULL,'music/tracks/d5313808-f09b-4384-b120-98cf8a91e039.mp3','audio/mpeg',3036676,186,NULL,0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(222,1,'Nil Nuwan Piyavila','Athula Sri Gamage',NULL,NULL,'music/tracks/f146659a-3343-4c30-afcd-d063da5b7afb.mp3','audio/mpeg',3754512,230,NULL,0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(223,1,'Pem Lowa Duk Gini','Athula Sri Gamage',NULL,NULL,'music/tracks/2ff3d722-dc13-4770-9ad6-d5bf71208763.mp3','audio/mpeg',4121895,253,NULL,0,'2026-10-09 06:58:56','2026-10-09 06:58:56'),(224,1,'Susuma Wee Mage','Athula Sri Gamage',NULL,NULL,'music/tracks/d88f5442-6772-4126-8fb4-a29c3e9f9900.mp3','audio/mpeg',3789694,232,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(225,1,'Ahala Mal Pipena','Chandana',NULL,NULL,'music/tracks/1defb783-ad36-4fd2-ba10-0739179754fa.mp3','audio/mpeg',3855911,237,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(226,1,'Dedunnak Wee Nil Ahasata','Chandana',NULL,NULL,'music/tracks/0a3f2656-e470-46cf-b5e4-3df5fe02d8fa.mp3','audio/mpeg',4423553,272,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(227,1,'Kavi Seepada','Chandana',NULL,NULL,'music/tracks/06519f22-8025-4c37-a9f9-6dc0f3e6d513.mp3','audio/mpeg',3466788,213,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(228,1,'Liyathambara Mala','Chandana',NULL,NULL,'music/tracks/f5e72a39-61a9-4dae-bed9-cb50668032f5.mp3','audio/mpeg',3781929,233,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(229,1,'Pol Ruppawe Danga Kala Sellam','Chandana',NULL,NULL,'music/tracks/ba7b720d-b3de-43a6-a2e0-70115c2e6a02.mp3','audio/mpeg',3838937,235,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(230,1,'Sanda Yathra Kala','Chandana',NULL,NULL,'music/tracks/c6f55c59-e345-4ff6-afc9-9d02eba1adb8.mp3','audio/mpeg',3558937,217,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(231,1,'Oya Dihai Balan Hitiye','Dayasiri',NULL,NULL,'music/tracks/da1b5f55-6a31-456e-9183-7f8760d6a216.mp3','audio/mpeg',3180889,195,NULL,0,'2026-10-09 06:58:57','2026-10-09 06:58:57'),(232,1,'Adara Samarum Kati Wu','Sanath Nandasiri',NULL,NULL,'music/tracks/3f7e1ffb-22b1-4c23-aed5-0e66ada6e7bc.mp3','audio/mpeg',3283919,200,NULL,0,'2026-10-09 06:58:58','2026-10-09 06:58:58'),(233,1,'Atha Epita Gammanen','Sanath Nandasiri',NULL,NULL,'music/tracks/6441a5cc-2afb-4b64-bd0a-38e2946fe65e.mp3','audio/mpeg',3504184,214,NULL,0,'2026-10-09 06:58:58','2026-10-09 06:58:58'),(234,1,'Hichchi Nage','Sanath Nandasiri',NULL,NULL,'music/tracks/4f9f5300-4bc5-4ac6-b477-38d7da3f55b2.mp3','audio/mpeg',3074522,187,NULL,2,'2026-10-09 06:58:58','2026-10-09 07:35:13'),(235,1,'Lassana Nam Na Penenne','Sanath Nandasiri',NULL,NULL,'music/tracks/df9c419e-f3fa-4da2-88f5-eec16d766c33.mp3','audio/mpeg',3315685,202,NULL,0,'2026-10-09 06:58:58','2026-10-09 06:58:58'),(236,1,'Mulu Lowa Magen Asawi','Sanath Nandasiri',NULL,NULL,'music/tracks/33dfe094-60d6-435f-a1f1-11920aed4f32.mp3','audio/mpeg',3010575,183,NULL,0,'2026-10-09 06:58:58','2026-10-09 06:58:58');
/*!40000 ALTER TABLE `songs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `designation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualifications` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `skills` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `suspension_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_last_seen_at_index` (`last_seen_at`),
  KEY `users_is_admin_index` (`is_admin`)
) ENGINE=InnoDB AUTO_INCREMENT=554 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Nimal Perera','nimal',NULL,NULL,'$2y$12$6FHyFWSJpBXEuaCXBGHxHOgK55UPBdbg2GwQDsJHpIZxk181oeX5y','Medical Officer, Grade I','MBBS (Colombo), MSc Health Informatics','Health informatics, Data analysis, Clinical audit, Training','I look after the clinic information systems and help teams make sense of their data.\r\nAlways happy to sit down with anyone who wants a dashboard built.','avatars/6d56b19c-10d7-4886-98c5-2ef9dfa28fd3.png','covers/c5b5ffa5-7370-4ecc-8e7a-430b5dfa7c0f.png',1,NULL,NULL,'2026-10-09 11:27:20',NULL,'2026-10-03 00:03:12','2026-10-09 11:27:20'),(2,'Sanduni Fernando','sanduni',NULL,NULL,'$2y$12$maXbQNftksTifsQV5M/AqO8zyDyFmmFpvdRZnvQO.XOBNITFl7D/i','Chief Pharmacist','BPharm (Peradeniya), MSc Clinical Pharmacy','Inventory management, Clinical pharmacy, Procurement','Running the dispensary and the stock system. Ask me before you order anything.',NULL,NULL,0,NULL,NULL,'2026-10-09 00:03:13',NULL,'2026-09-29 00:03:13','2026-10-09 00:03:13'),(3,'Ruwan Jayasuriya','ruwan',NULL,NULL,'$2y$12$8osCytNfCc4JnseZXbO31.Cj15GMpGZeD9gSVjIrJVqiuHKWFScM2','Systems Administrator','BSc Computer Science, RHCE','Linux, Networking, MySQL, Backups, Laravel','If the network is slow, I probably already know. Server room, second floor.',NULL,NULL,0,NULL,NULL,'2026-10-09 00:02:13',NULL,'2026-09-25 00:03:13','2026-10-09 00:03:13'),(4,'Dilani Wickramasinghe','dilani',NULL,NULL,'$2y$12$sgQQyvBabXKtqaiaTJP3ru7l99/j1hiJQASGwrbZSXLpnR2OjUzzm','Nursing Officer In-Charge','Diploma in Nursing, BSc Nursing (OUSL)','Ward management, Infection control, Staff rostering','Ward 4. Rosters go up every Thursday.',NULL,NULL,0,NULL,NULL,'2026-10-09 00:03:13',NULL,'2026-09-21 00:03:13','2026-10-09 00:03:14'),(5,'Kasun Bandara','kasun',NULL,NULL,'$2y$12$8OibbfN7l7IgKkr1GAJ4aOFe6DsD8Ooy6mU2bMZotR0G1EpR.3/di','Medical Laboratory Technologist','BSc MLS (Jayewardenepura)','Haematology, Quality control, LIS','Lab turnaround times are on the network drive, updated weekly.',NULL,NULL,0,NULL,NULL,'2026-10-09 10:41:54',NULL,'2026-09-17 00:03:14','2026-10-09 10:44:54'),(6,'Ayesha Rahman','ayesha',NULL,NULL,'$2y$12$FV4jmqxqWXRN745CH5wxieXMwAkGcn2j9UOK7GIsWdfc.NpQ6Z8WK','Health Education Officer','BA Communication, Dip. Public Health','Health promotion, Graphic design, Community outreach, Training','I make the posters and run the awareness programmes. Send me your ideas.',NULL,NULL,0,NULL,NULL,'2026-10-09 00:00:14',NULL,'2026-09-13 00:03:14','2026-10-09 00:03:15'),(7,'Tharindu Silva','tharindu',NULL,NULL,'$2y$12$VDNVd8sIEt7RqrOGaejDyuE9zb2GggoKJwfpnratvF79Sgd3Pm4L6','Accounts Officer','AAT, Reading CA Sri Lanka','Budgeting, Procurement, Excel, Reporting','Claims by the 25th, please. Forms are in the public drive.',NULL,NULL,0,NULL,NULL,'2026-10-08 00:03:15',NULL,'2026-09-09 00:03:15','2026-10-09 00:03:15'),(8,'Menaka Gunawardena','menaka',NULL,NULL,'$2y$12$de/TFHz6gR1Cf5ZVZoIXnOc4mb/vc8.YbeEWTvW3jT.y0lYa7QEwm','Public Health Inspector','Dip. Public Health Inspection','Field surveillance, Food safety, Dengue control, Reporting','Out in the field most mornings. Reachable here in the afternoons.',NULL,NULL,0,NULL,NULL,'2026-10-08 23:08:15',NULL,'2026-09-05 00:03:15','2026-10-09 00:03:15'),(9,'Jamil Bogan IV','nshields','coleman32@example.org','2026-10-09 01:57:28','$2y$04$dlp0tTqXpTSxSVgvfgUbleYuN76LPKBPMk5/JiVvX2BAWqRE4Xwn6',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'j5lFX7fsy1','2026-10-09 01:57:28','2026-10-09 01:57:28'),(10,'Toy Larkin','lawson33','tromp.myriam@example.net','2026-10-09 01:57:28','$2y$04$dlp0tTqXpTSxSVgvfgUbleYuN76LPKBPMk5/JiVvX2BAWqRE4Xwn6',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'135ZPCrCYZ','2026-10-09 01:57:28','2026-10-09 01:57:28'),(11,'Earnestine Muller','lockman.jaqueline','johnnie.murazik@example.net','2026-10-09 01:57:28','$2y$04$dlp0tTqXpTSxSVgvfgUbleYuN76LPKBPMk5/JiVvX2BAWqRE4Xwn6',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'5N9hY0ypmB','2026-10-09 01:57:28','2026-10-09 01:57:28'),(12,'Dakota Skiles','horace70','zhahn@example.org','2026-10-09 01:58:03','$2y$04$s8U9iJKHyX4AFn4.K1YBcOCvH7fDbSGuXK4nFoRy2Jt8wsrvEpm0S',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'eT1ncEWyG7','2026-10-09 01:58:03','2026-10-09 01:58:03'),(13,'Elijah Prosacco','larson.ilene','rosanna27@example.org','2026-10-09 01:58:04','$2y$04$s8U9iJKHyX4AFn4.K1YBcOCvH7fDbSGuXK4nFoRy2Jt8wsrvEpm0S',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'a4jo1GyQmZ','2026-10-09 01:58:04','2026-10-09 01:58:04'),(14,'Prof. Cristobal Emmerich III','brianne52','russell.predovic@example.net','2026-10-09 01:58:04','$2y$04$s8U9iJKHyX4AFn4.K1YBcOCvH7fDbSGuXK4nFoRy2Jt8wsrvEpm0S',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'aZemqu4qdK','2026-10-09 01:58:04','2026-10-09 01:58:04'),(15,'Prof. Bonnie Langosh','jared.frami','eondricka@example.com','2026-10-09 02:00:26','$2y$04$fgLTqO.GVNpLmGYq9XoqBuGu20qU7MxsrSHzG7CVXt4YjUICcTbXS',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:00:26','vbMsAD83AF','2026-10-09 02:00:26','2026-10-09 02:00:26'),(16,'Prof. Reece Williamson','dtowne','icremin@example.net','2026-10-09 02:00:26','$2y$04$fgLTqO.GVNpLmGYq9XoqBuGu20qU7MxsrSHzG7CVXt4YjUICcTbXS',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:00:26','2PTnlmfg5s','2026-10-09 02:00:26','2026-10-09 02:00:26'),(17,'Ms. Breana DuBuque','howe.valerie','emmalee.klein@example.net','2026-10-09 02:00:27','$2y$04$fgLTqO.GVNpLmGYq9XoqBuGu20qU7MxsrSHzG7CVXt4YjUICcTbXS',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:00:27','bwdQDQAPvw','2026-10-09 02:00:27','2026-10-09 02:00:27'),(18,'Dr. Sandy Ankunding','darian.stoltenberg','enos24@example.net','2026-10-09 02:01:16','$2y$04$zPzL1tS8LvhHNYorI3ek2.oUp.k7dnjBx6YT7BIdz8r2XAxe1gVQm',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:01:16','yDcQJJryuk','2026-10-09 02:01:16','2026-10-09 02:01:16'),(19,'Martina Skiles MD','trenton.fay','zschulist@example.com','2026-10-09 02:01:16','$2y$04$zPzL1tS8LvhHNYorI3ek2.oUp.k7dnjBx6YT7BIdz8r2XAxe1gVQm',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:01:17','uQIgy6EDZn','2026-10-09 02:01:16','2026-10-09 02:01:17'),(20,'Aletha Goyette','alec.murazik','cristian.koepp@example.com','2026-10-09 02:01:17','$2y$04$zPzL1tS8LvhHNYorI3ek2.oUp.k7dnjBx6YT7BIdz8r2XAxe1gVQm',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:01:17','JsFtgIi2rY','2026-10-09 02:01:17','2026-10-09 02:01:17'),(22,'Dr. Tyree Conn','douglas.jalon','bwisoky@example.com','2026-10-09 02:01:52','$2y$04$cefEz3IXarXjLpcmjFPMwu8U7YMNg.3tlXR0reG/wILOTshlMWkxG',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:01:52','7CzRhOSgUT','2026-10-09 02:01:52','2026-10-09 02:01:52'),(23,'Eldridge Sauer Jr.','lois.dooley','blake89@example.net','2026-10-09 02:01:52','$2y$04$cefEz3IXarXjLpcmjFPMwu8U7YMNg.3tlXR0reG/wILOTshlMWkxG',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:01:52','IpimYLhTrh','2026-10-09 02:01:52','2026-10-09 02:01:52'),(25,'Antwon Hilpert','bud.walsh','ondricka.alexis@example.org','2026-10-09 02:02:23','$2y$04$/xWZ2yK71FXIZwUT8stUx.VKRoPP5GUFSvY7rK23dasyISGWuAgx6',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:02:23','qhm1xk5lpN','2026-10-09 02:02:23','2026-10-09 02:02:23'),(29,'Cordelia Legros','beryl.kerluke','dkeeling@example.net','2026-10-09 02:03:02','$2y$04$Z/yysKFivjNk7Ugx2wxaZ.f5XC8yQBc3hc381bq1r/v2Fqv1jCb/G',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:03:02','w3HBUNB2N3','2026-10-09 02:03:02','2026-10-09 02:03:02'),(33,'Jerel Lehner','moses.hill','parker.fern@example.com','2026-10-09 02:36:45','$2y$04$uWLPsnnLQvUXyp5AM1lLyOM3TqbQYnCxj94gsPul8ZNa4Tt3ZFu8a',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:36:46','Bm9yhOfxur','2026-10-09 02:36:45','2026-10-09 02:36:46'),(37,'Jayden Considine','lhaag','zaufderhar@example.com','2026-10-09 02:51:49','$2y$04$Dw8yvJim6SvRT2mbho5LXe3Yo3Dwo02Cv6Lq4EpNMfcV0my67k5im',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 02:51:49','gdma9DNYj6','2026-10-09 02:51:49','2026-10-09 02:51:49'),(41,'Janet Wilkinson','seth74','kunze.christophe@example.com','2026-10-09 03:00:13','$2y$04$.khzv0No04/84wcL6kmnXetG5J2maoQjBy9TdiatmXaTKax2o6ryS',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 03:00:13','SBnReXfDpA','2026-10-09 03:00:13','2026-10-09 03:00:13'),(45,'Johnathan Krajcik','precious12','moshe.wehner@example.org','2026-10-09 03:01:06','$2y$04$WTA/VVnYucQ.wZSQ.fpA7uVavPeVlatit.gX3VzDSMytKDGNBzhZq',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 03:01:06','HLqPSypxwz','2026-10-09 03:01:06','2026-10-09 03:01:06'),(51,'Dr. Bert Satterfield','walsh.isidro','gabriella30@example.com','2026-10-09 05:54:34','$2y$04$90UacyFYSHPZyVmg3zyzBuxCDK7PAiPLF9CiVSg4G8JEAFeF6Zme2',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 05:54:35','IuG2x1rwSp','2026-10-09 05:54:34','2026-10-09 05:54:35'),(57,'Kayden Hansen','marion.zboncak','lula.buckridge@example.org','2026-10-09 06:32:53','$2y$04$YpSxb5uqKNMRVytQQo94iOzYx37S30uDsSH2BydRNzpT4FiXmolqq',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 06:32:53','6WHx2RuEPU','2026-10-09 06:32:53','2026-10-09 06:32:53'),(63,'Norbert Hansen','leonardo.satterfield','hoppe.randall@example.org','2026-10-09 06:39:25','$2y$04$.axCH4R4xeYGnWQRhA3QuuZ6mdd8LHRQOdpBJqkik6XKXybl4P2oy',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 06:39:25','rDLK2L8skR','2026-10-09 06:39:25','2026-10-09 06:39:25'),(78,'Prof. Curtis Kessler','elmira.thompson','evelyn.von@example.org','2026-10-09 07:23:13','$2y$04$NcNoQAtMuO7/uQ6AXkUEru7eZ2g39qOiiGiLJCU6AoHKD8pL1WXCa',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:23:13','jcMeB22Wza','2026-10-09 07:23:13','2026-10-09 07:23:13'),(93,'Albin Greenholt','jarrell.bednar','ukunde@example.net','2026-10-09 07:23:33','$2y$04$E.8EtxDGqyFwFg.WzI6uqe6rbB92QBg13OLQ6KqsDRRPJ6V3ARDZ.',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:23:33','VFAbiMuwg6','2026-10-09 07:23:33','2026-10-09 07:23:33'),(108,'Oswaldo Schiller','jzboncak','raegan27@example.org','2026-10-09 07:26:44','$2y$04$SGWedi5hWNKADFP0/BwKC.n5RbreMKHBrUodwFqHnOX2w2MfSodme',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:26:44','X8ZwHazz9X','2026-10-09 07:26:44','2026-10-09 07:26:44'),(123,'Freeman Torp V','tbrown','martine45@example.org','2026-10-09 07:43:14','$2y$04$YR2/KWSOnyJ2QdV9/9LvMeH6lMEIFonQDKBAz/vLqsM343Ot0s0cS',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:43:14','MSJhbZmYQu','2026-10-09 07:43:14','2026-10-09 07:43:14'),(149,'Marcelo Pfannerstill','christ56','lina.dibbert@example.net','2026-10-09 07:43:55','$2y$04$j0ACV5pqvX9iOxfsWt5t8eDk0Op7N6tcg5g.dpyXN0/694CVsQr/W',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:43:55','KU8X9pjRV9','2026-10-09 07:43:55','2026-10-09 07:43:55'),(169,'Cassandre Moen','armstrong.rolando','elena76@example.com','2026-10-09 07:44:09','$2y$04$ibMvsD7CtN0CReqe9Mn6zOHl9rp/bXittP2bDCSSydeO1jYVAmMq6',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:44:09','AQWa03XGPq','2026-10-09 07:44:09','2026-10-09 07:44:09'),(189,'Prof. Candelario Schaden IV','xander.hamill','alexandria07@example.com','2026-10-09 07:44:25','$2y$04$w/Vv2tqnUiTrnp9DPnF02uA2ykjSX0jaMikNCvV2eQsi9eVKCAQRO',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:44:25','6Yvb1tHneT','2026-10-09 07:44:25','2026-10-09 07:44:25'),(209,'Lesly Pfeffer','ariel.steuber','meda.koelpin@example.com','2026-10-09 07:48:23','$2y$04$uuhXc4uPk05CYjsjiDvb2OXtf5MB7IX0X2N31w.nYrADxBJ9QU1tS',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:48:23','cYfITtGZ2j','2026-10-09 07:48:23','2026-10-09 07:48:23'),(229,'Lurline Gutkowski','mayra23','xgerhold@example.org','2026-10-09 07:48:39','$2y$04$8ZxHTo/lbqK.0ArHtZxQWeiOmLiDXMIBrlegrqy5z5x44p96nNuI2',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:48:39','8tMWXPopJp','2026-10-09 07:48:39','2026-10-09 07:48:39'),(249,'Tracey Schiller','elda62','iberge@example.com','2026-10-09 07:53:36','$2y$04$VfGLhYeNB3QeHnkUPubbPeiSiVhbWmUVCg0h9Q86RDBRJtL/pdAYS',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 07:53:36','lrIY9hVe9z','2026-10-09 07:53:36','2026-10-09 07:53:36'),(269,'Randal Schaefer','jaiden.spinka','xyost@example.net','2026-10-09 08:18:03','$2y$04$3ICJDCaA1lKWA7aR7cIxN.D6.x4T1.Kacia/ezEYFyRhO0GmEvbou',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-10-09 08:18:03','bpvbwV5I3p','2026-10-09 08:18:03','2026-10-09 08:18:03');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-09 22:28:04
