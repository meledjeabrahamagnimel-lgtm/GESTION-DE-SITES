-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: gestionsites
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `abonnements_push`
--

DROP TABLE IF EXISTS `abonnements_push`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `abonnements_push` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `cle_p256dh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cle_auth` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `appareil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `empreinte` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `abonnements_push_user_id_empreinte_unique` (`user_id`,`empreinte`),
  CONSTRAINT `abonnements_push_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `abonnements_push`
--

LOCK TABLES `abonnements_push` WRITE;
/*!40000 ALTER TABLE `abonnements_push` DISABLE KEYS */;
/*!40000 ALTER TABLE `abonnements_push` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB AUTO_INCREMENT=243 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (1,'default','created','App\\Models\\User','created',1,NULL,NULL,'{\"attributes\": {\"name\": \"Super Admin\", \"email\": \"superadmin@plateforme.local\", \"est_actif\": true, \"entreprise_id\": null}}',NULL,'2026-08-13 14:18:06','2026-08-13 14:18:06'),(2,'default','updated','App\\Models\\User','updated',1,NULL,NULL,'{\"old\": {\"est_actif\": null}, \"attributes\": {\"est_actif\": true}}',NULL,'2026-08-13 14:18:06','2026-08-13 14:18:06'),(3,'default','created','App\\Models\\User','created',2,NULL,NULL,'{\"attributes\": {\"name\": \"Jean-Baptiste Kouassi\", \"email\": \"gerant@gmail.com\", \"est_actif\": true, \"entreprise_id\": 1}}',NULL,'2026-08-13 14:18:08','2026-08-13 14:18:08'),(4,'default','created','App\\Models\\User','created',3,NULL,NULL,'{\"attributes\": {\"name\": \"Marie-Claire Aya\", \"email\": \"responsable@gmail.com\", \"est_actif\": true, \"entreprise_id\": 1}}',NULL,'2026-08-13 14:18:09','2026-08-13 14:18:09'),(5,'default','created','App\\Models\\User','created',4,NULL,NULL,'{\"attributes\": {\"name\": \"Koffi Yao\", \"email\": \"commercial@gmail.com\", \"est_actif\": true, \"entreprise_id\": 1}}',NULL,'2026-08-13 14:18:12','2026-08-13 14:18:12'),(6,'default','created','App\\Models\\User','created',5,NULL,NULL,'{\"attributes\": {\"name\": \"Fatou Diabaté\", \"email\": \"caissier@gmail.com\", \"est_actif\": true, \"entreprise_id\": 1}}',NULL,'2026-08-13 14:18:13','2026-08-13 14:18:13'),(7,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',1,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Yao\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:13','2026-08-13 14:18:13'),(8,'default','created','App\\Domain\\Operations\\Models\\Devis','created',1,NULL,NULL,'{\"attributes\": {\"client\": \"M. Yao\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-15T00:00:00.000000Z\", \"montant_devis\": 782000, \"date_reception\": \"2026-06-15T00:00:00.000000Z\", \"montant_valide\": 547400, \"n_fiche_reception\": \"FR--499\"}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(9,'default','created','App\\Domain\\Operations\\Models\\Facture','created',1,NULL,NULL,'{\"attributes\": {\"date\": \"2026-06-20T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"M. Yao\", \"montant\": 547400, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--5079\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(10,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',2,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"CIE\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(11,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',3,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(12,'default','created','App\\Domain\\Operations\\Models\\Devis','created',2,NULL,NULL,'{\"attributes\": {\"client\": \"Groupe Alliances\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-16T00:00:00.000000Z\", \"montant_devis\": 345000, \"date_reception\": \"2026-06-15T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--309\"}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(13,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',4,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. Koné\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(14,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',5,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Gnahoré\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(15,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',6,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(16,'default','created','App\\Domain\\Operations\\Models\\Devis','created',3,NULL,NULL,'{\"attributes\": {\"client\": \"NSIA Assurances\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-19T00:00:00.000000Z\", \"montant_devis\": 86000, \"date_reception\": \"2026-06-16T00:00:00.000000Z\", \"montant_valide\": 73100, \"n_fiche_reception\": \"FR--137\"}}',NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(17,'default','created','App\\Domain\\Operations\\Models\\Facture','created',2,NULL,NULL,'{\"attributes\": {\"date\": \"2026-06-24T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"NSIA Assurances\", \"montant\": 73100, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--9747\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(18,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',7,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Koné\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(19,'default','created','App\\Domain\\Operations\\Models\\Devis','created',4,NULL,NULL,'{\"attributes\": {\"client\": \"M. Koné\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-17T00:00:00.000000Z\", \"montant_devis\": 431000, \"date_reception\": \"2026-06-16T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--651\"}}',NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(20,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',8,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Yao\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(21,'default','created','App\\Domain\\Operations\\Models\\Devis','created',5,NULL,NULL,'{\"attributes\": {\"client\": \"M. Yao\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-17T00:00:00.000000Z\", \"montant_devis\": 808000, \"date_reception\": \"2026-06-17T00:00:00.000000Z\", \"montant_valide\": 670640, \"n_fiche_reception\": \"FR--388\"}}',NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(22,'default','created','App\\Domain\\Operations\\Models\\Facture','created',3,NULL,NULL,'{\"attributes\": {\"date\": \"2026-06-22T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"M. Yao\", \"montant\": 670640, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--7171\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(23,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',9,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Bolloré Transport\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(24,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',10,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(25,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',11,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. N\'Dri\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(26,'default','created','App\\Domain\\Operations\\Models\\Devis','created',6,NULL,NULL,'{\"attributes\": {\"client\": \"M. N\'Dri\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-21T00:00:00.000000Z\", \"montant_devis\": 777000, \"date_reception\": \"2026-06-18T00:00:00.000000Z\", \"montant_valide\": 606060, \"n_fiche_reception\": \"FR--935\"}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(27,'default','created','App\\Domain\\Operations\\Models\\Facture','created',4,NULL,NULL,'{\"attributes\": {\"date\": \"2026-06-27T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"M. N\'Dri\", \"montant\": 606060, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--5249\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(28,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',12,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(29,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',13,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(30,'default','created','App\\Domain\\Operations\\Models\\Devis','created',7,NULL,NULL,'{\"attributes\": {\"client\": \"Groupe Alliances\", \"statut\": \"En attente\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-06-22T00:00:00.000000Z\", \"montant_devis\": 156000, \"date_reception\": \"2026-06-19T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--864\"}}',NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(31,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',14,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Kouassi\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(32,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',15,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(33,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',16,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Yao\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(34,'default','created','App\\Domain\\Operations\\Models\\Devis','created',8,NULL,NULL,'{\"attributes\": {\"client\": \"M. Yao\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-06-22T00:00:00.000000Z\", \"montant_devis\": 652000, \"date_reception\": \"2026-06-20T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--766\"}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(35,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',17,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Orange CI\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(36,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',18,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(37,'default','created','App\\Domain\\Operations\\Models\\Devis','created',9,NULL,NULL,'{\"attributes\": {\"client\": \"Nestlé CI\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-25T00:00:00.000000Z\", \"montant_devis\": 665000, \"date_reception\": \"2026-06-22T00:00:00.000000Z\", \"montant_valide\": 545300, \"n_fiche_reception\": \"FR--711\"}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(38,'default','created','App\\Domain\\Operations\\Models\\Facture','created',5,NULL,NULL,'{\"attributes\": {\"date\": \"2026-06-30T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"Nestlé CI\", \"montant\": 545300, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--3454\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(39,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',19,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(40,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',20,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Bamba\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(41,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',21,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Mme Traoré\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(42,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',22,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(43,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',23,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Gnahoré\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(44,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',24,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Kouassi\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(45,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',25,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Bamba\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(46,'default','created','App\\Domain\\Operations\\Models\\Devis','created',10,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Bamba\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-26T00:00:00.000000Z\", \"montant_devis\": 202000, \"date_reception\": \"2026-06-25T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--227\"}}',NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(47,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',26,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(48,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',27,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bernabé\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(49,'default','created','App\\Domain\\Operations\\Models\\Devis','created',11,NULL,NULL,'{\"attributes\": {\"client\": \"Bernabé\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-28T00:00:00.000000Z\", \"montant_devis\": 209000, \"date_reception\": \"2026-06-25T00:00:00.000000Z\", \"montant_valide\": 198550, \"n_fiche_reception\": \"FR--827\"}}',NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(50,'default','created','App\\Domain\\Operations\\Models\\Facture','created',6,NULL,NULL,'{\"attributes\": {\"date\": \"2026-06-30T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"Bernabé\", \"montant\": 198550, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--3025\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(51,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',28,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. N\'Dri\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(52,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',29,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(53,'default','created','App\\Domain\\Operations\\Models\\Devis','created',12,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Aka\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-27T00:00:00.000000Z\", \"montant_devis\": 147000, \"date_reception\": \"2026-06-26T00:00:00.000000Z\", \"montant_valide\": 107310, \"n_fiche_reception\": \"FR--417\"}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(54,'default','created','App\\Domain\\Operations\\Models\\Facture','created',7,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-02T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Mme Aka\", \"montant\": 107310, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--5068\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(55,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',30,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Orange CI\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(56,'default','created','App\\Domain\\Operations\\Models\\Devis','created',13,NULL,NULL,'{\"attributes\": {\"client\": \"Orange CI\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-27T00:00:00.000000Z\", \"montant_devis\": 429000, \"date_reception\": \"2026-06-27T00:00:00.000000Z\", \"montant_valide\": 300300, \"n_fiche_reception\": \"FR--499\"}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(57,'default','created','App\\Domain\\Operations\\Models\\Facture','created',8,NULL,NULL,'{\"attributes\": {\"date\": \"2026-06-29T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Orange CI\", \"montant\": 300300, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--7145\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(58,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',31,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Koné\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(59,'default','created','App\\Domain\\Operations\\Models\\Devis','created',14,NULL,NULL,'{\"attributes\": {\"client\": \"M. Koné\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-06-28T00:00:00.000000Z\", \"montant_devis\": 501000, \"date_reception\": \"2026-06-27T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--651\"}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(60,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',32,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Aka\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(61,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',33,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. N\'Dri\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(62,'default','created','App\\Domain\\Operations\\Models\\Devis','created',15,NULL,NULL,'{\"attributes\": {\"client\": \"M. N\'Dri\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-06-29T00:00:00.000000Z\", \"montant_devis\": 319000, \"date_reception\": \"2026-06-27T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--657\"}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(63,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',34,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(64,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',35,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(65,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',36,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Ouattara\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(66,'default','created','App\\Domain\\Operations\\Models\\Devis','created',16,NULL,NULL,'{\"attributes\": {\"client\": \"M. Ouattara\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-01T00:00:00.000000Z\", \"montant_devis\": 226000, \"date_reception\": \"2026-06-30T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--739\"}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(67,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',37,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Yao\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(68,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',38,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Bamba\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(69,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',39,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"SODECI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(70,'default','created','App\\Domain\\Operations\\Models\\Devis','created',17,NULL,NULL,'{\"attributes\": {\"client\": \"SODECI\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-04T00:00:00.000000Z\", \"montant_devis\": 105000, \"date_reception\": \"2026-07-01T00:00:00.000000Z\", \"montant_valide\": 93450, \"n_fiche_reception\": \"FR--199\"}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(71,'default','created','App\\Domain\\Operations\\Models\\Facture','created',9,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-09T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"SODECI\", \"montant\": 93450, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--5969\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(72,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',40,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Bernabé\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(73,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',41,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Orange CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(74,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',42,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(75,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',43,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Traoré\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(76,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',44,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"SIFCA\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(77,'default','created','App\\Domain\\Operations\\Models\\Devis','created',18,NULL,NULL,'{\"attributes\": {\"client\": \"SIFCA\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-02T00:00:00.000000Z\", \"montant_devis\": 417000, \"date_reception\": \"2026-07-02T00:00:00.000000Z\", \"montant_valide\": 391980, \"n_fiche_reception\": \"FR--520\"}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(78,'default','created','App\\Domain\\Operations\\Models\\Facture','created',10,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-08T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"SIFCA\", \"montant\": 391980, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--2361\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(79,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',45,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. N\'Dri\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(80,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',46,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Ouattara\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(81,'default','created','App\\Domain\\Operations\\Models\\Devis','created',19,NULL,NULL,'{\"attributes\": {\"client\": \"M. Ouattara\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-05T00:00:00.000000Z\", \"montant_devis\": 428000, \"date_reception\": \"2026-07-03T00:00:00.000000Z\", \"montant_valide\": 415160, \"n_fiche_reception\": \"FR--790\"}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(82,'default','created','App\\Domain\\Operations\\Models\\Facture','created',11,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-08T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"M. Ouattara\", \"montant\": 415160, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--9132\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(83,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',47,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(84,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',48,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Bamba\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(85,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',49,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"SIFCA\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(86,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',50,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Orange CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(87,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',51,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bernabé\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(88,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',52,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"CFAO Motors\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(89,'default','created','App\\Domain\\Operations\\Models\\Devis','created',20,NULL,NULL,'{\"attributes\": {\"client\": \"CFAO Motors\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-09T00:00:00.000000Z\", \"montant_devis\": 87000, \"date_reception\": \"2026-07-07T00:00:00.000000Z\", \"montant_valide\": 84390, \"n_fiche_reception\": \"FR--594\"}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(90,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',53,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Orange CI\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(91,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',54,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Traoré\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(92,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',55,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"SIFCA\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(93,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',56,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Kouassi\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(94,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',57,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(95,'default','created','App\\Domain\\Operations\\Models\\Devis','created',21,NULL,NULL,'{\"attributes\": {\"client\": \"Groupe Alliances\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-12T00:00:00.000000Z\", \"montant_devis\": 259000, \"date_reception\": \"2026-07-09T00:00:00.000000Z\", \"montant_valide\": 204610, \"n_fiche_reception\": \"FR--959\"}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(96,'default','created','App\\Domain\\Operations\\Models\\Facture','created',12,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-17T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Groupe Alliances\", \"montant\": 204610, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--5197\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(97,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',58,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"SODECI\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(98,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',59,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Gnahoré\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(99,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',60,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Nestlé CI\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(100,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',61,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(101,'default','created','App\\Domain\\Operations\\Models\\Devis','created',22,NULL,NULL,'{\"attributes\": {\"client\": \"Bolloré Transport\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-12T00:00:00.000000Z\", \"montant_devis\": 663000, \"date_reception\": \"2026-07-10T00:00:00.000000Z\", \"montant_valide\": 470730, \"n_fiche_reception\": \"FR--215\"}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(102,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',62,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bernabé\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(103,'default','created','App\\Domain\\Operations\\Models\\Devis','created',23,NULL,NULL,'{\"attributes\": {\"client\": \"Bernabé\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-13T00:00:00.000000Z\", \"montant_devis\": 705000, \"date_reception\": \"2026-07-11T00:00:00.000000Z\", \"montant_valide\": 514650, \"n_fiche_reception\": \"FR--368\"}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(104,'default','created','App\\Domain\\Operations\\Models\\Facture','created',13,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-15T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Bernabé\", \"montant\": 514650, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--4115\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(105,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',63,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(106,'default','created','App\\Domain\\Operations\\Models\\Devis','created',24,NULL,NULL,'{\"attributes\": {\"client\": \"Nestlé CI\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-13T00:00:00.000000Z\", \"montant_devis\": 832000, \"date_reception\": \"2026-07-11T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--289\"}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(107,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',64,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(108,'default','created','App\\Domain\\Operations\\Models\\Devis','created',25,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Aka\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-15T00:00:00.000000Z\", \"montant_devis\": 244000, \"date_reception\": \"2026-07-13T00:00:00.000000Z\", \"montant_valide\": 178120, \"n_fiche_reception\": \"FR--841\"}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(109,'default','created','App\\Domain\\Operations\\Models\\Facture','created',14,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-17T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"Mme Aka\", \"montant\": 178120, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--1074\", \"observations\": null, \"commercial_id\": 2}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(110,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',65,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"CIE\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(111,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',66,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(112,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',67,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Yao\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(113,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',68,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(114,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',69,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"SODECI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(115,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',70,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. Diallo\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(116,'default','created','App\\Domain\\Operations\\Models\\Devis','created',26,NULL,NULL,'{\"attributes\": {\"client\": \"M. Diallo\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-16T00:00:00.000000Z\", \"montant_devis\": 609000, \"date_reception\": \"2026-07-16T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--624\"}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(117,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',71,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(118,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',72,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Orange CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(119,'default','created','App\\Domain\\Operations\\Models\\Devis','created',27,NULL,NULL,'{\"attributes\": {\"client\": \"Orange CI\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-17T00:00:00.000000Z\", \"montant_devis\": 752000, \"date_reception\": \"2026-07-16T00:00:00.000000Z\", \"montant_valide\": 564000, \"n_fiche_reception\": \"FR--653\"}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(120,'default','created','App\\Domain\\Operations\\Models\\Facture','created',15,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-20T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Orange CI\", \"montant\": 564000, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--4061\", \"observations\": null, \"commercial_id\": 2}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(121,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',73,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Yao\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(122,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',74,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"CFAO Motors\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(123,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',75,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Traoré\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(124,'default','created','App\\Domain\\Operations\\Models\\Devis','created',28,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Traoré\", \"statut\": \"En attente\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-20T00:00:00.000000Z\", \"montant_devis\": 321000, \"date_reception\": \"2026-07-18T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--733\"}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(125,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',76,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"CIE\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(126,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',77,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. N\'Dri\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(127,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',78,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(128,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',79,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(129,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',80,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Diallo\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(130,'default','created','App\\Domain\\Operations\\Models\\Devis','created',29,NULL,NULL,'{\"attributes\": {\"client\": \"M. Diallo\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-21T00:00:00.000000Z\", \"montant_devis\": 207000, \"date_reception\": \"2026-07-20T00:00:00.000000Z\", \"montant_valide\": 159390, \"n_fiche_reception\": \"FR--787\"}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(131,'default','created','App\\Domain\\Operations\\Models\\Facture','created',16,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-27T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"M. Diallo\", \"montant\": 159390, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--5865\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(132,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',81,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Bernabé\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(133,'default','created','App\\Domain\\Operations\\Models\\Devis','created',30,NULL,NULL,'{\"attributes\": {\"client\": \"Bernabé\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-21T00:00:00.000000Z\", \"montant_devis\": 93000, \"date_reception\": \"2026-07-21T00:00:00.000000Z\", \"montant_valide\": 74400, \"n_fiche_reception\": \"FR--990\"}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(134,'default','created','App\\Domain\\Operations\\Models\\Facture','created',17,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-22T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Bernabé\", \"montant\": 74400, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--4723\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(135,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',82,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Aka\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(136,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',83,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(137,'default','created','App\\Domain\\Operations\\Models\\Devis','created',31,NULL,NULL,'{\"attributes\": {\"client\": \"Nestlé CI\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-25T00:00:00.000000Z\", \"montant_devis\": 486000, \"date_reception\": \"2026-07-22T00:00:00.000000Z\", \"montant_valide\": 349920, \"n_fiche_reception\": \"FR--136\"}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(138,'default','created','App\\Domain\\Operations\\Models\\Facture','created',18,NULL,NULL,'{\"attributes\": {\"date\": \"2026-07-26T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"Nestlé CI\", \"montant\": 349920, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--6107\", \"observations\": null, \"commercial_id\": 2}}',NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(139,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',84,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"CIE\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:27','2026-08-13 14:18:27'),(140,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',85,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:27','2026-08-13 14:18:27'),(141,'default','created','App\\Domain\\Operations\\Models\\Devis','created',32,NULL,NULL,'{\"attributes\": {\"client\": \"NSIA Assurances\", \"statut\": \"En attente\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-23T00:00:00.000000Z\", \"montant_devis\": 810000, \"date_reception\": \"2026-07-23T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--493\"}}',NULL,'2026-08-13 14:18:27','2026-08-13 14:18:27'),(142,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',86,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"SIFCA\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:28','2026-08-13 14:18:28'),(143,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',87,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Groupe Alliances\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:29','2026-08-13 14:18:29'),(144,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',88,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:29','2026-08-13 14:18:29'),(145,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',89,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:29','2026-08-13 14:18:29'),(146,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',90,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:30','2026-08-13 14:18:30'),(147,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',91,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Orange CI\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:30','2026-08-13 14:18:30'),(148,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',92,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Bernabé\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:30','2026-08-13 14:18:30'),(149,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',93,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Orange CI\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:30','2026-08-13 14:18:30'),(150,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',94,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bolloré Transport\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(151,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',95,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(152,'default','created','App\\Domain\\Operations\\Models\\Devis','created',33,NULL,NULL,'{\"attributes\": {\"client\": \"Bolloré Transport\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-26T00:00:00.000000Z\", \"montant_devis\": 732000, \"date_reception\": \"2026-07-25T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--860\"}}',NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(153,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',96,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(154,'default','created','App\\Domain\\Operations\\Models\\Devis','created',34,NULL,NULL,'{\"attributes\": {\"client\": \"Nestlé CI\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-27T00:00:00.000000Z\", \"montant_devis\": 366000, \"date_reception\": \"2026-07-27T00:00:00.000000Z\", \"montant_valide\": 270840, \"n_fiche_reception\": \"FR--482\"}}',NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(155,'default','created','App\\Domain\\Operations\\Models\\Facture','created',19,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-02T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Nestlé CI\", \"montant\": 270840, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--3016\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(156,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',97,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. N\'Dri\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(157,'default','created','App\\Domain\\Operations\\Models\\Devis','created',35,NULL,NULL,'{\"attributes\": {\"client\": \"M. N\'Dri\", \"statut\": \"Refusé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-30T00:00:00.000000Z\", \"montant_devis\": 717000, \"date_reception\": \"2026-07-27T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--527\"}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(158,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',98,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Yao\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(159,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',99,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(160,'default','created','App\\Domain\\Operations\\Models\\Devis','created',36,NULL,NULL,'{\"attributes\": {\"client\": \"Nestlé CI\", \"statut\": \"Refusé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-07-30T00:00:00.000000Z\", \"montant_devis\": 386000, \"date_reception\": \"2026-07-27T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--599\"}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(161,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',100,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. Yao\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(162,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',101,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. Ouattara\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(163,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',102,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Diallo\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(164,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',103,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"CIE\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(165,'default','created','App\\Domain\\Operations\\Models\\Devis','created',37,NULL,NULL,'{\"attributes\": {\"client\": \"CIE\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-07-29T00:00:00.000000Z\", \"montant_devis\": 468000, \"date_reception\": \"2026-07-28T00:00:00.000000Z\", \"montant_valide\": 453960, \"n_fiche_reception\": \"FR--923\"}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(166,'default','created','App\\Domain\\Operations\\Models\\Facture','created',20,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-02T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"CIE\", \"montant\": 453960, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--1239\", \"observations\": null, \"commercial_id\": 2}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(167,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',104,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"SODECI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(168,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',105,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(169,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',106,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"CIE\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(170,'default','created','App\\Domain\\Operations\\Models\\Devis','created',38,NULL,NULL,'{\"attributes\": {\"client\": \"CIE\", \"statut\": \"Refusé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-02T00:00:00.000000Z\", \"montant_devis\": 108000, \"date_reception\": \"2026-07-30T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--851\"}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(171,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',107,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"CFAO Motors\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(172,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',108,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(173,'default','created','App\\Domain\\Operations\\Models\\Devis','created',39,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Aka\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-01T00:00:00.000000Z\", \"montant_devis\": 815000, \"date_reception\": \"2026-07-31T00:00:00.000000Z\", \"montant_valide\": 725350, \"n_fiche_reception\": \"FR--855\"}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(174,'default','created','App\\Domain\\Operations\\Models\\Facture','created',21,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-05T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"Mme Aka\", \"montant\": 725350, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--7054\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(175,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',109,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(176,'default','created','App\\Domain\\Operations\\Models\\Devis','created',40,NULL,NULL,'{\"attributes\": {\"client\": \"Groupe Alliances\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-01T00:00:00.000000Z\", \"montant_devis\": 543000, \"date_reception\": \"2026-07-31T00:00:00.000000Z\", \"montant_valide\": 401820, \"n_fiche_reception\": \"FR--273\"}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(177,'default','created','App\\Domain\\Operations\\Models\\Facture','created',22,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-07T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"Groupe Alliances\", \"montant\": 401820, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--6534\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(178,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',110,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Bolloré Transport\", \"passage\": false, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(179,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',111,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. N\'Dri\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(180,'default','created','App\\Domain\\Operations\\Models\\Devis','created',41,NULL,NULL,'{\"attributes\": {\"client\": \"M. N\'Dri\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-01T00:00:00.000000Z\", \"montant_devis\": 671000, \"date_reception\": \"2026-08-01T00:00:00.000000Z\", \"montant_valide\": 570350, \"n_fiche_reception\": \"FR--123\"}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(181,'default','created','App\\Domain\\Operations\\Models\\Facture','created',23,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-06T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"M. N\'Dri\", \"montant\": 570350, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--2791\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(182,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',112,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Gnahoré\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(183,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',113,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"SIFCA\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(184,'default','created','App\\Domain\\Operations\\Models\\Devis','created',42,NULL,NULL,'{\"attributes\": {\"client\": \"SIFCA\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-03T00:00:00.000000Z\", \"montant_devis\": 217000, \"date_reception\": \"2026-08-01T00:00:00.000000Z\", \"montant_valide\": 201810, \"n_fiche_reception\": \"FR--470\"}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(185,'default','created','App\\Domain\\Operations\\Models\\Facture','created',24,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-07T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"SIFCA\", \"montant\": 201810, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--5744\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(186,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',114,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Nestlé CI\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(187,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',115,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(188,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',116,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bernabé\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(189,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',117,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Kouassi\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(190,'default','created','App\\Domain\\Operations\\Models\\Devis','created',43,NULL,NULL,'{\"attributes\": {\"client\": \"M. Kouassi\", \"statut\": \"En attente\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-07T00:00:00.000000Z\", \"montant_devis\": 515000, \"date_reception\": \"2026-08-04T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--651\"}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(191,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',118,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(192,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',119,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(193,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',120,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Bernabé\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(194,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',121,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Kouassi\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(195,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',122,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Bamba\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(196,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',123,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"SIFCA\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(197,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',124,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(198,'default','created','App\\Domain\\Operations\\Models\\Devis','created',44,NULL,NULL,'{\"attributes\": {\"client\": \"Bolloré Transport\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-08T00:00:00.000000Z\", \"montant_devis\": 280000, \"date_reception\": \"2026-08-06T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--798\"}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(199,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',125,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bernabé\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(200,'default','created','App\\Domain\\Operations\\Models\\Devis','created',45,NULL,NULL,'{\"attributes\": {\"client\": \"Bernabé\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-08T00:00:00.000000Z\", \"montant_devis\": 750000, \"date_reception\": \"2026-08-06T00:00:00.000000Z\", \"montant_valide\": 585000, \"n_fiche_reception\": \"FR--195\"}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(201,'default','created','App\\Domain\\Operations\\Models\\Facture','created',25,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-09T00:00:00.000000Z\", \"type\": \"HT\", \"client\": \"Bernabé\", \"montant\": 585000, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--5319\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(202,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',126,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Bolloré Transport\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(203,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',127,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"SODECI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(204,'default','created','App\\Domain\\Operations\\Models\\Devis','created',46,NULL,NULL,'{\"attributes\": {\"client\": \"SODECI\", \"statut\": \"En attente\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 2, \"date_emission\": \"2026-08-07T00:00:00.000000Z\", \"montant_devis\": 518000, \"date_reception\": \"2026-08-07T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--509\"}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(205,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',128,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(206,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',129,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Bolloré Transport\", \"passage\": false, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(207,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',130,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 2, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(208,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',131,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"SIFCA\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"N\'Gattakro\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(209,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',132,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"NSIA Assurances\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(210,'default','created','App\\Domain\\Operations\\Models\\Devis','created',47,NULL,NULL,'{\"attributes\": {\"client\": \"NSIA Assurances\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-11T00:00:00.000000Z\", \"montant_devis\": 83000, \"date_reception\": \"2026-08-10T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--761\"}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(211,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',133,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Diallo\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Transmise\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(212,'default','created','App\\Domain\\Operations\\Models\\Devis','created',48,NULL,NULL,'{\"attributes\": {\"client\": \"M. Diallo\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-13T00:00:00.000000Z\", \"montant_devis\": 561000, \"date_reception\": \"2026-08-11T00:00:00.000000Z\", \"montant_valide\": 431970, \"n_fiche_reception\": \"FR--294\"}}',NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(213,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',134,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"SIFCA\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(214,'default','created','App\\Domain\\Operations\\Models\\Devis','created',49,NULL,NULL,'{\"attributes\": {\"client\": \"SIFCA\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-14T00:00:00.000000Z\", \"montant_devis\": 605000, \"date_reception\": \"2026-08-11T00:00:00.000000Z\", \"montant_valide\": 453750, \"n_fiche_reception\": \"FR--764\"}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(215,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',135,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. N\'Dri\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(216,'default','created','App\\Domain\\Operations\\Models\\Devis','created',50,NULL,NULL,'{\"attributes\": {\"client\": \"M. N\'Dri\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-15T00:00:00.000000Z\", \"montant_devis\": 54000, \"date_reception\": \"2026-08-12T00:00:00.000000Z\", \"montant_valide\": 42660, \"n_fiche_reception\": \"FR--769\"}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(217,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',136,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Koné\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Transmise\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(218,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',137,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Refusée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(219,'default','created','App\\Domain\\Operations\\Models\\Devis','created',51,NULL,NULL,'{\"attributes\": {\"client\": \"Groupe Alliances\", \"statut\": \"Refusé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-13T00:00:00.000000Z\", \"montant_devis\": 86000, \"date_reception\": \"2026-08-12T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--351\"}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(220,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',138,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"M. Diallo\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": false}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(221,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',139,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Groupe Alliances\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Transmise\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(222,'default','created','App\\Domain\\Operations\\Models\\Devis','created',52,NULL,NULL,'{\"attributes\": {\"client\": \"Groupe Alliances\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-15T00:00:00.000000Z\", \"montant_devis\": 636000, \"date_reception\": \"2026-08-13T00:00:00.000000Z\", \"montant_valide\": 496080, \"n_fiche_reception\": \"FR--487\"}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(223,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',140,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(224,'default','created','App\\Domain\\Operations\\Models\\Devis','created',53,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Aka\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-15T00:00:00.000000Z\", \"montant_devis\": 232000, \"date_reception\": \"2026-08-13T00:00:00.000000Z\", \"montant_valide\": 192560, \"n_fiche_reception\": \"FR--677\"}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(225,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',141,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Mme Bamba\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Bouaké centre\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(226,'default','created','App\\Domain\\Operations\\Models\\Devis','created',54,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Bamba\", \"statut\": \"En attente\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-13T00:00:00.000000Z\", \"montant_devis\": 718000, \"date_reception\": \"2026-08-13T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--997\"}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(227,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',142,NULL,NULL,'{\"attributes\": {\"moyen\": \"Mail\", \"client\": \"Nestlé CI\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(228,'default','created','App\\Domain\\Operations\\Models\\Devis','created',55,NULL,NULL,'{\"attributes\": {\"client\": \"Nestlé CI\", \"statut\": \"Validé\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-13T00:00:00.000000Z\", \"montant_devis\": 213000, \"date_reception\": \"2026-08-13T00:00:00.000000Z\", \"montant_valide\": 198090, \"n_fiche_reception\": \"FR--785\"}}',NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(229,'default','created','App\\Domain\\Operations\\Models\\Facture','created',26,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-13T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Nestlé CI\", \"montant\": 198090, \"activite\": \"Mécanique\", \"n_facture\": \"FAC--9445\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(230,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',143,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"M. Ouattara\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(231,'default','created','App\\Domain\\Operations\\Models\\Devis','created',56,NULL,NULL,'{\"attributes\": {\"client\": \"M. Ouattara\", \"statut\": \"En attente\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-12T00:00:00.000000Z\", \"montant_devis\": 724000, \"date_reception\": \"2026-08-12T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--313\"}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(232,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',144,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Sokoura\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(233,'default','created','App\\Domain\\Operations\\Models\\Devis','created',57,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Aka\", \"statut\": \"Refusé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-12T00:00:00.000000Z\", \"montant_devis\": 309000, \"date_reception\": \"2026-08-12T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--779\"}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(234,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',145,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"M. Kouassi\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Koko\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(235,'default','created','App\\Domain\\Operations\\Models\\Devis','created',58,NULL,NULL,'{\"attributes\": {\"client\": \"M. Kouassi\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-12T00:00:00.000000Z\", \"montant_devis\": 139000, \"date_reception\": \"2026-08-12T00:00:00.000000Z\", \"montant_valide\": 133440, \"n_fiche_reception\": \"FR--321\"}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(236,'default','created','App\\Domain\\Operations\\Models\\Facture','created',27,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-12T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"M. Kouassi\", \"montant\": 133440, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--6142\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(237,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',146,NULL,NULL,'{\"attributes\": {\"moyen\": \"Téléphone\", \"client\": \"CFAO Motors\", \"passage\": true, \"activite\": \"Mécanique\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Zone industrielle\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(238,'default','created','App\\Domain\\Operations\\Models\\Devis','created',59,NULL,NULL,'{\"attributes\": {\"client\": \"CFAO Motors\", \"statut\": \"En attente\", \"activite\": \"Mécanique\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-11T00:00:00.000000Z\", \"montant_devis\": 608000, \"date_reception\": \"2026-08-11T00:00:00.000000Z\", \"montant_valide\": null, \"n_fiche_reception\": \"FR--453\"}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(239,'default','created','App\\Domain\\Operations\\Models\\Prospection','created',147,NULL,NULL,'{\"attributes\": {\"moyen\": \"RDV\", \"client\": \"Mme Aka\", \"passage\": true, \"activite\": \"Sinistre\", \"date_devis\": null, \"motif_refus\": null, \"date_passage\": null, \"localisation\": \"Air France\", \"observations\": null, \"commercial_id\": 1, \"statut_validation\": \"Validée\", \"devis_apres_passage\": true}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(240,'default','created','App\\Domain\\Operations\\Models\\Devis','created',60,NULL,NULL,'{\"attributes\": {\"client\": \"Mme Aka\", \"statut\": \"Validé\", \"activite\": \"Sinistre\", \"observations\": null, \"commercial_id\": 1, \"date_emission\": \"2026-08-11T00:00:00.000000Z\", \"montant_devis\": 527000, \"date_reception\": \"2026-08-11T00:00:00.000000Z\", \"montant_valide\": 474300, \"n_fiche_reception\": \"FR--155\"}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(241,'default','created','App\\Domain\\Operations\\Models\\Facture','created',28,NULL,NULL,'{\"attributes\": {\"date\": \"2026-08-11T00:00:00.000000Z\", \"type\": \"FNE\", \"client\": \"Mme Aka\", \"montant\": 474300, \"activite\": \"Sinistre\", \"n_facture\": \"FAC--8317\", \"observations\": null, \"commercial_id\": 1}}',NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(242,'default','created','App\\Models\\User','created',6,NULL,NULL,'{\"attributes\": {\"name\": \"Support Plateforme\", \"email\": \"support@plateforme.local\", \"est_actif\": true, \"entreprise_id\": null}}',NULL,'2026-08-13 14:18:41','2026-08-13 14:18:41');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('gestion-de-sites-cache-57ed36a31cc95f86ca4750ae54a90399','i:1;',1786631076),('gestion-de-sites-cache-57ed36a31cc95f86ca4750ae54a90399:timer','i:1786631076;',1786631076);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `charges`
--

DROP TABLE IF EXISTS `charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `charges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `type_operation` enum('Charges','Transfert','Décaissement DG') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Charges',
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `moyen` enum('Espèces','Mobile Money','Chèque','Virement','Autres') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Espèces',
  `montant` bigint unsigned NOT NULL,
  `tiers` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `cree_par` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `charges_site_id_foreign` (`site_id`),
  KEY `charges_cree_par_foreign` (`cree_par`),
  KEY `charges_entreprise_id_site_id_date_index` (`entreprise_id`,`site_id`,`date`),
  CONSTRAINT `charges_cree_par_foreign` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `charges_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `charges`
--

LOCK TABLES `charges` WRITE;
/*!40000 ALTER TABLE `charges` DISABLE KEYS */;
INSERT INTO `charges` VALUES (1,1,1,'2026-06-15','Charges','Autres décaissements','Virement',49418,'Entretien local',NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:36'),(2,1,1,'2026-06-16','Charges','Autres décaissements','Virement',49418,'Divers',NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:36'),(3,1,1,'2026-06-18','Charges','Salaires & personnel','Espèces',204858,'Personnel',NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:36'),(4,1,1,'2026-06-19','Charges','Achats pièces','Espèces',232712,'Garage import',NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:36'),(5,1,1,'2026-06-20','Charges','Achats pièces','Espèces',287520,'Fournisseur pièces auto',NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:36'),(6,1,1,'2026-06-22','Charges','Achats pièces','Virement',288419,'Garage import',NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:36'),(7,1,1,'2026-06-23','Charges','Salaires & personnel','Virement',398934,'Personnel',NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:36'),(8,1,1,'2026-06-24','Charges','Salaires & personnel','Espèces',346821,'Personnel',NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:36'),(9,1,1,'2026-06-25','Charges','Fonctionnement','Virement',42230,'Carburant véhicules service',NULL,NULL,'2026-08-13 14:18:19','2026-08-13 14:18:36'),(10,1,1,'2026-06-26','Charges','Fonctionnement','Espèces',57504,'Électricité',NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:36'),(11,1,1,'2026-06-29','Charges','Fonctionnement','Virement',77271,'Électricité',NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:36'),(12,1,1,'2026-06-30','Charges','Achats pièces','Virement',85358,'Garage import',NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:36'),(13,1,1,'2026-07-01','Charges','Achats pièces','Virement',211148,'Fournisseur pièces auto',NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:36'),(14,1,1,'2026-07-02','Charges','Achats pièces','Espèces',286622,'Fournisseur pièces auto',NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:36'),(15,1,1,'2026-07-04','Charges','Autres décaissements','Espèces',38636,'Entretien local',NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:36'),(16,1,1,'2026-07-06','Charges','Autres décaissements','Virement',48519,'Entretien local',NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:36'),(17,1,1,'2026-07-07','Charges','Salaires & personnel','Espèces',215640,'Personnel',NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:36'),(18,1,1,'2026-07-08','Charges','Salaires & personnel','Virement',148253,'Personnel',NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:36'),(19,1,1,'2026-07-09','Charges','Salaires & personnel','Espèces',345923,'Personnel',NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:36'),(20,1,1,'2026-07-10','Charges','Autres décaissements','Virement',45824,'Fournitures bureau',NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:36'),(21,1,1,'2026-07-11','Charges','Salaires & personnel','Virement',221930,'Personnel',NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:36'),(22,1,1,'2026-07-13','Charges','Achats pièces','Virement',166223,'Garage import',NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:36'),(23,1,1,'2026-07-15','Charges','Autres décaissements','Virement',11681,'Entretien local',NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:36'),(24,1,1,'2026-07-16','Charges','Autres décaissements','Virement',26057,'Entretien local',NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:36'),(25,1,1,'2026-07-17','Charges','Autres décaissements','Virement',44027,'Divers',NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:36'),(26,1,1,'2026-07-18','Charges','Fonctionnement','Virement',53012,'Loyer',NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:36'),(27,1,1,'2026-07-21','Charges','Fonctionnement','Virement',61098,'Eau',NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:36'),(28,1,1,'2026-07-22','Charges','Achats pièces','Espèces',170715,'Fournisseur pièces auto',NULL,NULL,'2026-08-13 14:18:27','2026-08-13 14:18:36'),(29,1,1,'2026-07-23','Charges','Autres décaissements','Espèces',54809,'Divers',NULL,NULL,'2026-08-13 14:18:29','2026-08-13 14:18:36'),(30,1,1,'2026-07-24','Charges','Autres décaissements','Virement',61997,'Divers',NULL,NULL,'2026-08-13 14:18:30','2026-08-13 14:18:36'),(31,1,1,'2026-07-25','Charges','Salaires & personnel','Virement',251580,'Personnel',NULL,NULL,'2026-08-13 14:18:31','2026-08-13 14:18:36'),(32,1,1,'2026-07-27','Charges','Achats pièces','Virement',125790,'Garage import',NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:36'),(33,1,1,'2026-07-28','Charges','Fonctionnement','Espèces',40433,'Carburant véhicules service',NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:36'),(34,1,1,'2026-07-29','Charges','Achats pièces','Espèces',80865,'Garage import',NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:36'),(35,1,1,'2026-07-31','Charges','Salaires & personnel','Virement',176106,'Personnel',NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:36'),(36,1,1,'2026-08-03','Charges','Autres décaissements','Virement',10782,'Entretien local',NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:36'),(37,1,1,'2026-08-04','Charges','Salaires & personnel','Espèces',300099,'Personnel',NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:36'),(38,1,1,'2026-08-05','Charges','Salaires & personnel','Espèces',242595,'Personnel',NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:36'),(39,1,1,'2026-08-06','Charges','Fonctionnement','Virement',51215,'Internet',NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:36'),(40,1,1,'2026-08-07','Charges','Salaires & personnel','Virement',365690,'Personnel',NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:36'),(41,1,1,'2026-08-08','Charges','Fonctionnement','Espèces',64692,'Loyer',NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:36'),(42,1,1,'2026-08-10','Charges','Fonctionnement','Espèces',54809,'Internet',NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:36'),(43,1,1,'2026-08-11','Charges','Autres décaissements','Espèces',35940,'Fournitures bureau',NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:36'),(44,1,1,'2026-08-12','Charges','Autres décaissements','Virement',46722,'Divers',NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:36'),(45,1,1,'2026-08-13','Charges','Autres décaissements','Virement',31448,'Divers',NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:36'),(46,1,1,'2026-08-13','Transfert','Autres décaissements','Virement',47621,'Transfert inter-sites',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(47,1,1,'2026-08-13','Décaissement DG','Autres décaissements','Virement',41331,'Direction Générale',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(48,1,1,'2026-08-12','Transfert','Autres décaissements','Virement',69185,'Transfert inter-sites',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(49,1,1,'2026-08-12','Décaissement DG','Autres décaissements','Virement',38636,'Direction Générale',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(50,1,1,'2026-08-11','Transfert','Autres décaissements','Virement',31448,'Transfert inter-sites',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(51,1,1,'2026-08-11','Décaissement DG','Autres décaissements','Virement',66489,'Direction Générale',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36');
/*!40000 ALTER TABLE `charges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commerciaux`
--

DROP TABLE IF EXISTS `commerciaux`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerciaux` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activite` enum('Mécanique','Sinistre','Mécanique/Sinistre') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objectif_mecanique` bigint unsigned NOT NULL DEFAULT '0',
  `objectif_sinistre` bigint unsigned NOT NULL DEFAULT '0',
  `objectif_mensuel` bigint unsigned NOT NULL DEFAULT '0',
  `statut` enum('Actif','Inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Actif',
  `est_spontane` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commerciaux_entreprise_id_numero_unique` (`entreprise_id`,`numero`),
  KEY `commerciaux_site_id_foreign` (`site_id`),
  KEY `commerciaux_user_id_foreign` (`user_id`),
  KEY `commerciaux_entreprise_id_site_id_statut_index` (`entreprise_id`,`site_id`,`statut`),
  CONSTRAINT `commerciaux_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerciaux_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commerciaux_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commerciaux`
--

LOCK TABLES `commerciaux` WRITE;
/*!40000 ALTER TABLE `commerciaux` DISABLE KEYS */;
INSERT INTO `commerciaux` VALUES (1,1,1,4,'C-0001','Koffi Yao','Mécanique/Sinistre',14000000,6000000,20000000,'Actif',0,'2026-08-13 14:18:12','2026-08-13 14:18:12'),(2,1,1,NULL,'SP-MEC','Client spontané',NULL,0,0,0,'Actif',1,'2026-08-13 14:18:12','2026-08-13 14:18:12'),(3,1,2,NULL,'SP-SIN','Client spontané',NULL,0,0,0,'Actif',1,'2026-08-13 14:18:12','2026-08-13 14:18:12');
/*!40000 ALTER TABLE `commerciaux` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compteurs_documents`
--

DROP TABLE IF EXISTS `compteurs_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compteurs_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `type` enum('pro','dev','fac','com','nfa') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dernier_numero` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `compteurs_documents_entreprise_id_type_unique` (`entreprise_id`,`type`),
  CONSTRAINT `compteurs_documents_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compteurs_documents`
--

LOCK TABLES `compteurs_documents` WRITE;
/*!40000 ALTER TABLE `compteurs_documents` DISABLE KEYS */;
INSERT INTO `compteurs_documents` VALUES (1,1,'com',1,'2026-08-13 14:18:13','2026-08-13 14:18:13'),(2,1,'pro',147,'2026-08-13 14:18:13','2026-08-13 14:18:36'),(3,1,'dev',60,'2026-08-13 14:18:14','2026-08-13 14:18:36'),(4,1,'fac',28,'2026-08-13 14:18:14','2026-08-13 14:18:36');
/*!40000 ALTER TABLE `compteurs_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_participants`
--

DROP TABLE IF EXISTS `conversation_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `lu_le` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_participants_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_participants_user_id_index` (`user_id`),
  CONSTRAINT `conversation_participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_participants`
--

LOCK TABLES `conversation_participants` WRITE;
/*!40000 ALTER TABLE `conversation_participants` DISABLE KEYS */;
INSERT INTO `conversation_participants` VALUES (1,1,2,'2026-08-13 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(2,1,3,'2026-08-13 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(3,2,2,'2026-08-13 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(4,2,3,NULL,'2026-08-13 14:18:37','2026-08-13 14:18:37'),(5,2,4,NULL,'2026-08-13 14:18:37','2026-08-13 14:18:37'),(6,3,4,'2026-08-13 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(7,3,3,'2026-08-13 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(8,4,2,'2026-08-13 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(9,4,1,'2026-08-13 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37');
/*!40000 ALTER TABLE `conversation_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned DEFAULT NULL,
  `sujet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cree_par` bigint unsigned DEFAULT NULL,
  `dernier_message_le` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_cree_par_foreign` (`cree_par`),
  KEY `conversations_entreprise_id_dernier_message_le_index` (`entreprise_id`,`dernier_message_le`),
  CONSTRAINT `conversations_cree_par_foreign` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
INSERT INTO `conversations` VALUES (1,1,'Objectifs du mois',2,'2026-08-09 15:00:36','2026-08-13 14:18:36','2026-08-13 14:18:37'),(2,1,'Rappel : clôture de fin de mois',2,'2026-08-11 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(3,1,NULL,4,'2026-08-12 15:40:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(4,NULL,'Question sur la facturation FNE',2,'2026-08-07 15:33:37','2026-08-13 14:18:37','2026-08-13 14:18:37');
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `devis`
--

DROP TABLE IF EXISTS `devis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `devis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `commercial_id` bigint unsigned NOT NULL,
  `prospection_id` bigint unsigned DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_fiche_reception` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_reception` date DEFAULT NULL,
  `date_emission` date NOT NULL,
  `client` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activite` enum('Mécanique','Sinistre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('En attente','Validé','Refusé') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'En attente',
  `montant_devis` bigint unsigned NOT NULL,
  `montant_valide` bigint unsigned DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `cree_par` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `devis_entreprise_id_numero_unique` (`entreprise_id`,`numero`),
  KEY `devis_site_id_foreign` (`site_id`),
  KEY `devis_commercial_id_foreign` (`commercial_id`),
  KEY `devis_prospection_id_foreign` (`prospection_id`),
  KEY `devis_cree_par_foreign` (`cree_par`),
  KEY `devis_entreprise_id_site_id_statut_date_emission_index` (`entreprise_id`,`site_id`,`statut`,`date_emission`),
  CONSTRAINT `devis_commercial_id_foreign` FOREIGN KEY (`commercial_id`) REFERENCES `commerciaux` (`id`) ON DELETE CASCADE,
  CONSTRAINT `devis_cree_par_foreign` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `devis_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `devis_prospection_id_foreign` FOREIGN KEY (`prospection_id`) REFERENCES `prospections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `devis_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `devis`
--

LOCK TABLES `devis` WRITE;
/*!40000 ALTER TABLE `devis` DISABLE KEYS */;
INSERT INTO `devis` VALUES (1,1,1,1,1,'D-0001','FR--499','2026-06-15','2026-06-15','M. Yao','Mécanique','Validé',782000,547400,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(2,1,1,1,3,'D-0002','FR--309','2026-06-15','2026-06-16','Groupe Alliances','Sinistre','Refusé',345000,NULL,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(3,1,1,1,6,'D-0003','FR--137','2026-06-16','2026-06-19','NSIA Assurances','Mécanique','Validé',86000,73100,NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(4,1,1,1,7,'D-0004','FR--651','2026-06-16','2026-06-17','M. Koné','Sinistre','Refusé',431000,NULL,NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(5,1,1,1,8,'D-0005','FR--388','2026-06-17','2026-06-17','M. Yao','Mécanique','Validé',808000,670640,NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(6,1,1,1,11,'D-0006','FR--935','2026-06-18','2026-06-21','M. N\'Dri','Sinistre','Validé',777000,606060,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(7,1,1,2,13,'D-0007','FR--864','2026-06-19','2026-06-22','Groupe Alliances','Mécanique','En attente',156000,NULL,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(8,1,1,2,16,'D-0008','FR--766','2026-06-20','2026-06-22','M. Yao','Sinistre','Refusé',652000,NULL,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(9,1,1,1,18,'D-0009','FR--711','2026-06-22','2026-06-25','Nestlé CI','Sinistre','Validé',665000,545300,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(10,1,1,1,25,'D-0010','FR--227','2026-06-25','2026-06-26','Mme Bamba','Sinistre','Refusé',202000,NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(11,1,1,1,27,'D-0011','FR--827','2026-06-25','2026-06-28','Bernabé','Mécanique','Validé',209000,198550,NULL,NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(12,1,1,1,29,'D-0012','FR--417','2026-06-26','2026-06-27','Mme Aka','Sinistre','Validé',147000,107310,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(13,1,1,1,30,'D-0013','FR--499','2026-06-27','2026-06-27','Orange CI','Sinistre','Validé',429000,300300,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(14,1,1,2,31,'D-0014','FR--651','2026-06-27','2026-06-28','M. Koné','Sinistre','Refusé',501000,NULL,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(15,1,1,1,33,'D-0015','FR--657','2026-06-27','2026-06-29','M. N\'Dri','Sinistre','Refusé',319000,NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(16,1,1,1,36,'D-0016','FR--739','2026-06-30','2026-07-01','M. Ouattara','Sinistre','Refusé',226000,NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(17,1,1,1,39,'D-0017','FR--199','2026-07-01','2026-07-04','SODECI','Mécanique','Validé',105000,93450,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(18,1,1,1,44,'D-0018','FR--520','2026-07-02','2026-07-02','SIFCA','Sinistre','Validé',417000,391980,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(19,1,1,1,46,'D-0019','FR--790','2026-07-03','2026-07-05','M. Ouattara','Sinistre','Validé',428000,415160,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(20,1,1,2,52,'D-0020','FR--594','2026-07-07','2026-07-09','CFAO Motors','Sinistre','Validé',87000,84390,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(21,1,1,1,57,'D-0021','FR--959','2026-07-09','2026-07-12','Groupe Alliances','Mécanique','Validé',259000,204610,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(22,1,1,1,61,'D-0022','FR--215','2026-07-10','2026-07-12','Bolloré Transport','Mécanique','Validé',663000,470730,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(23,1,1,1,62,'D-0023','FR--368','2026-07-11','2026-07-13','Bernabé','Sinistre','Validé',705000,514650,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(24,1,1,1,63,'D-0024','FR--289','2026-07-11','2026-07-13','Nestlé CI','Sinistre','Refusé',832000,NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(25,1,1,2,64,'D-0025','FR--841','2026-07-13','2026-07-15','Mme Aka','Sinistre','Validé',244000,178120,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(26,1,1,2,70,'D-0026','FR--624','2026-07-16','2026-07-16','M. Diallo','Sinistre','Refusé',609000,NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(27,1,1,2,72,'D-0027','FR--653','2026-07-16','2026-07-17','Orange CI','Mécanique','Validé',752000,564000,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(28,1,1,1,75,'D-0028','FR--733','2026-07-18','2026-07-20','Mme Traoré','Sinistre','En attente',321000,NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(29,1,1,1,80,'D-0029','FR--787','2026-07-20','2026-07-21','M. Diallo','Sinistre','Validé',207000,159390,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(30,1,1,1,81,'D-0030','FR--990','2026-07-21','2026-07-21','Bernabé','Mécanique','Validé',93000,74400,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(31,1,1,2,83,'D-0031','FR--136','2026-07-22','2026-07-25','Nestlé CI','Mécanique','Validé',486000,349920,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(32,1,1,2,85,'D-0032','FR--493','2026-07-23','2026-07-23','NSIA Assurances','Sinistre','En attente',810000,NULL,NULL,NULL,'2026-08-13 14:18:27','2026-08-13 14:18:27'),(33,1,1,2,95,'D-0033','FR--860','2026-07-25','2026-07-26','Bolloré Transport','Sinistre','Refusé',732000,NULL,NULL,NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(34,1,1,1,96,'D-0034','FR--482','2026-07-27','2026-07-27','Nestlé CI','Mécanique','Validé',366000,270840,NULL,NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(35,1,1,1,97,'D-0035','FR--527','2026-07-27','2026-07-30','M. N\'Dri','Mécanique','Refusé',717000,NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(36,1,1,1,99,'D-0036','FR--599','2026-07-27','2026-07-30','Nestlé CI','Mécanique','Refusé',386000,NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(37,1,1,2,103,'D-0037','FR--923','2026-07-28','2026-07-29','CIE','Sinistre','Validé',468000,453960,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(38,1,1,1,106,'D-0038','FR--851','2026-07-30','2026-08-02','CIE','Mécanique','Refusé',108000,NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(39,1,1,1,108,'D-0039','FR--855','2026-07-31','2026-08-01','Mme Aka','Sinistre','Validé',815000,725350,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(40,1,1,1,109,'D-0040','FR--273','2026-07-31','2026-08-01','Groupe Alliances','Mécanique','Validé',543000,401820,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(41,1,1,1,111,'D-0041','FR--123','2026-08-01','2026-08-01','M. N\'Dri','Mécanique','Validé',671000,570350,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(42,1,1,1,113,'D-0042','FR--470','2026-08-01','2026-08-03','SIFCA','Mécanique','Validé',217000,201810,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(43,1,1,1,117,'D-0043','FR--651','2026-08-04','2026-08-07','M. Kouassi','Mécanique','En attente',515000,NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(44,1,1,1,124,'D-0044','FR--798','2026-08-06','2026-08-08','Bolloré Transport','Sinistre','Refusé',280000,NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(45,1,1,1,125,'D-0045','FR--195','2026-08-06','2026-08-08','Bernabé','Mécanique','Validé',750000,585000,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(46,1,1,2,127,'D-0046','FR--509','2026-08-07','2026-08-07','SODECI','Mécanique','En attente',518000,NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(47,1,1,1,132,'D-0047','FR--761','2026-08-10','2026-08-11','NSIA Assurances','Sinistre','Refusé',83000,NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(48,1,1,1,133,'D-0048','FR--294','2026-08-11','2026-08-13','M. Diallo','Sinistre','Validé',561000,431970,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(49,1,1,1,134,'D-0049','FR--764','2026-08-11','2026-08-14','SIFCA','Sinistre','Validé',605000,453750,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(50,1,1,1,135,'D-0050','FR--769','2026-08-12','2026-08-15','M. N\'Dri','Sinistre','Validé',54000,42660,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(51,1,1,1,137,'D-0051','FR--351','2026-08-12','2026-08-13','Groupe Alliances','Mécanique','Refusé',86000,NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(52,1,1,1,139,'D-0052','FR--487','2026-08-13','2026-08-15','Groupe Alliances','Mécanique','Validé',636000,496080,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(53,1,1,1,140,'D-0053','FR--677','2026-08-13','2026-08-15','Mme Aka','Sinistre','Validé',232000,192560,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(54,1,1,1,141,'D-0054','FR--997','2026-08-13','2026-08-13','Mme Bamba','Sinistre','En attente',718000,NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(55,1,1,1,142,'D-0055','FR--785','2026-08-13','2026-08-13','Nestlé CI','Mécanique','Validé',213000,198090,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(56,1,1,1,143,'D-0056','FR--313','2026-08-12','2026-08-12','M. Ouattara','Sinistre','En attente',724000,NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(57,1,1,1,144,'D-0057','FR--779','2026-08-12','2026-08-12','Mme Aka','Sinistre','Refusé',309000,NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(58,1,1,1,145,'D-0058','FR--321','2026-08-12','2026-08-12','M. Kouassi','Sinistre','Validé',139000,133440,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(59,1,1,1,146,'D-0059','FR--453','2026-08-11','2026-08-11','CFAO Motors','Mécanique','En attente',608000,NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(60,1,1,1,147,'D-0060','FR--155','2026-08-11','2026-08-11','Mme Aka','Sinistre','Validé',527000,474300,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36');
/*!40000 ALTER TABLE `devis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donnees_libres`
--

DROP TABLE IF EXISTS `donnees_libres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donnees_libres` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `sujet_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sujet_id` bigint unsigned NOT NULL,
  `intitule` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text COLLATE utf8mb4_unicode_ci,
  `cree_par` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `donnees_libres_entreprise_id_foreign` (`entreprise_id`),
  KEY `donnees_libres_sujet_type_sujet_id_index` (`sujet_type`,`sujet_id`),
  KEY `donnees_libres_cree_par_foreign` (`cree_par`),
  CONSTRAINT `donnees_libres_cree_par_foreign` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `donnees_libres_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donnees_libres`
--

LOCK TABLES `donnees_libres` WRITE;
/*!40000 ALTER TABLE `donnees_libres` DISABLE KEYS */;
/*!40000 ALTER TABLE `donnees_libres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dossiers_notes`
--

DROP TABLE IF EXISTS `dossiers_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dossiers_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `couleur` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#2563EB',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dossiers_notes_entreprise_id_foreign` (`entreprise_id`),
  KEY `dossiers_notes_user_id_nom_index` (`user_id`,`nom`),
  CONSTRAINT `dossiers_notes_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dossiers_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dossiers_notes`
--

LOCK TABLES `dossiers_notes` WRITE;
/*!40000 ALTER TABLE `dossiers_notes` DISABLE KEYS */;
INSERT INTO `dossiers_notes` VALUES (1,1,4,'Suivi clients','#2563EB','2026-08-13 14:18:37','2026-08-13 14:18:37'),(2,1,4,'Relances','#D97706','2026-08-13 14:18:37','2026-08-13 14:18:37');
/*!40000 ALTER TABLE `dossiers_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `encaissements`
--

DROP TABLE IF EXISTS `encaissements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `encaissements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `facture_id` bigint unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `type` enum('Client','Appro','Autres') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Client',
  `moyen` enum('Espèces','Mobile Money','Chèque','Virement','Autres') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Espèces',
  `montant` bigint unsigned NOT NULL,
  `client` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autres_tiers` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cree_par` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `encaissements_site_id_foreign` (`site_id`),
  KEY `encaissements_facture_id_foreign` (`facture_id`),
  KEY `encaissements_cree_par_foreign` (`cree_par`),
  KEY `encaissements_entreprise_id_site_id_date_index` (`entreprise_id`,`site_id`,`date`),
  CONSTRAINT `encaissements_cree_par_foreign` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `encaissements_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `encaissements_facture_id_foreign` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `encaissements_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encaissements`
--

LOCK TABLES `encaissements` WRITE;
/*!40000 ALTER TABLE `encaissements` DISABLE KEYS */;
INSERT INTO `encaissements` VALUES (1,1,1,1,'2026-06-24','Client','Chèque',547400,'M. Yao',NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(2,1,1,2,'2026-06-24','Client','Espèces',73100,'NSIA Assurances',NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(3,1,1,3,'2026-06-25','Client','Virement',670640,'M. Yao',NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(4,1,1,4,'2026-06-28','Client','Mobile Money',606060,'M. N\'Dri',NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(5,1,1,5,'2026-07-01','Client','Virement',545300,'Nestlé CI',NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(6,1,1,6,'2026-07-02','Client','Espèces',198550,'Bernabé',NULL,NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(7,1,1,7,'2026-07-06','Client','Mobile Money',107310,'Mme Aka',NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(8,1,1,8,'2026-07-03','Client','Mobile Money',300300,'Orange CI',NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(9,1,1,9,'2026-07-12','Client','Espèces',93450,'SODECI',NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(10,1,1,10,'2026-07-09','Client','Espèces',391980,'SIFCA',NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(11,1,1,12,'2026-07-21','Client','Chèque',204610,'Groupe Alliances',NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(12,1,1,13,'2026-07-18','Client','Mobile Money',514650,'Bernabé',NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(13,1,1,15,'2026-07-21','Client','Virement',564000,'Orange CI',NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(14,1,1,16,'2026-07-27','Client','Virement',159390,'M. Diallo',NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(15,1,1,17,'2026-07-23','Client','Mobile Money',74400,'Bernabé',NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(16,1,1,18,'2026-07-28','Client','Mobile Money',349920,'Nestlé CI',NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(17,1,1,19,'2026-08-04','Client','Espèces',270840,'Nestlé CI',NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(18,1,1,20,'2026-08-05','Client','Mobile Money',453960,'CIE',NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(19,1,1,21,'2026-08-07','Client','Mobile Money',725350,'Mme Aka',NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(20,1,1,23,'2026-08-10','Client','Espèces',570350,'M. N\'Dri',NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(21,1,1,24,'2026-08-07','Client','Virement',201810,'SIFCA',NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(22,1,1,25,'2026-08-10','Client','Mobile Money',585000,'Bernabé',NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(23,1,1,26,'2026-08-13','Client','Virement',198090,'Nestlé CI',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(24,1,1,27,'2026-08-12','Client','Chèque',133440,'M. Kouassi',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(25,1,1,28,'2026-08-11','Client','Espèces',474300,'Mme Aka',NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36');
/*!40000 ALTER TABLE `encaissements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entreprises`
--

DROP TABLE IF EXISTS `entreprises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entreprises` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_entreprise` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_chemin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `couleur_ink` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#191B20',
  `couleur_paper` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#F4F3EF',
  `couleur_ligne` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#E2E0D8',
  `couleur_accent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#C8102E',
  `couleur_succes` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0E9F6E',
  `couleur_alerte` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#D97706',
  `couleur_info` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#2563EB',
  `plan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'starter',
  `est_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `gerant_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gerant_prenom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gerant_fonction` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Gérant',
  `gerant_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rccm` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncc` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regime_imposition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `centre_impots` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `compte_contribuable` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idu` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commune` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quartier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_cadastrale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proprietaire_local` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entreprises_slug_unique` (`slug`),
  UNIQUE KEY `entreprises_code_entreprise_unique` (`code_entreprise`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entreprises`
--

LOCK TABLES `entreprises` WRITE;
/*!40000 ALTER TABLE `entreprises` DISABLE KEYS */;
INSERT INTO `entreprises` VALUES (1,'L\'Artisan Automobile','artisan-automobile','ART-2026CI','public:logos/artisan-automobile.png','#191B20','#F4F3EF','#E2E0D8','#C8102E','#0E9F6E','#D97706','#2563EB','entreprise',1,'2026-08-13 14:18:06','2026-08-13 14:18:06','KOUASSI','Jean-Baptiste','Gérant','gerant@gmail.com','Zone industrielle de Yopougon, ABIDJAN, CÔTE D\'IVOIRE','+225 27 23 45 67 89','gerant@gmail.com','CI-ABJ-2018-B-14520','1745820 K','RNI — Régime Normal d\'Imposition','YOPOUGON INDUSTRIEL','1745820 K','CI-001-2026-A874512','YOPOUGON','Zone Industrielle','Section D, Parcelle 118','SCI LES ATELIERS DU LAGON');
/*!40000 ALTER TABLE `entreprises` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exercice_villes`
--

DROP TABLE IF EXISTS `exercice_villes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercice_villes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exercice_id` bigint unsigned NOT NULL,
  `ville_id` bigint unsigned NOT NULL,
  `statut` enum('Ouvert','Clos') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Ouvert',
  `cloture_le` timestamp NULL DEFAULT NULL,
  `cloture_par` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exercice_villes_exercice_id_ville_id_unique` (`exercice_id`,`ville_id`),
  KEY `exercice_villes_ville_id_foreign` (`ville_id`),
  KEY `exercice_villes_cloture_par_foreign` (`cloture_par`),
  CONSTRAINT `exercice_villes_cloture_par_foreign` FOREIGN KEY (`cloture_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exercice_villes_exercice_id_foreign` FOREIGN KEY (`exercice_id`) REFERENCES `exercices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exercice_villes_ville_id_foreign` FOREIGN KEY (`ville_id`) REFERENCES `villes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exercice_villes`
--

LOCK TABLES `exercice_villes` WRITE;
/*!40000 ALTER TABLE `exercice_villes` DISABLE KEYS */;
/*!40000 ALTER TABLE `exercice_villes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exercices`
--

DROP TABLE IF EXISTS `exercices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exercices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `annee` smallint unsigned NOT NULL,
  `statut` enum('Ouvert','Clos') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Ouvert',
  `cloture_le` timestamp NULL DEFAULT NULL,
  `est_defaut` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exercices_entreprise_id_annee_unique` (`entreprise_id`,`annee`),
  CONSTRAINT `exercices_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exercices`
--

LOCK TABLES `exercices` WRITE;
/*!40000 ALTER TABLE `exercices` DISABLE KEYS */;
INSERT INTO `exercices` VALUES (1,1,2026,'Ouvert',NULL,0,'2026-08-13 14:26:07','2026-08-13 14:26:07');
/*!40000 ALTER TABLE `exercices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `factures`
--

DROP TABLE IF EXISTS `factures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `factures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `devis_id` bigint unsigned DEFAULT NULL,
  `commercial_id` bigint unsigned NOT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `n_facture` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `client` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('FNE','HT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FNE',
  `activite` enum('Mécanique','Sinistre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` bigint unsigned NOT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `cree_par` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `factures_entreprise_id_numero_unique` (`entreprise_id`,`numero`),
  KEY `factures_site_id_foreign` (`site_id`),
  KEY `factures_devis_id_foreign` (`devis_id`),
  KEY `factures_commercial_id_foreign` (`commercial_id`),
  KEY `factures_cree_par_foreign` (`cree_par`),
  KEY `factures_entreprise_id_site_id_date_index` (`entreprise_id`,`site_id`,`date`),
  CONSTRAINT `factures_commercial_id_foreign` FOREIGN KEY (`commercial_id`) REFERENCES `commerciaux` (`id`) ON DELETE CASCADE,
  CONSTRAINT `factures_cree_par_foreign` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `factures_devis_id_foreign` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE SET NULL,
  CONSTRAINT `factures_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `factures_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factures`
--

LOCK TABLES `factures` WRITE;
/*!40000 ALTER TABLE `factures` DISABLE KEYS */;
INSERT INTO `factures` VALUES (1,1,1,1,1,'F-0001','FAC--5079','2026-06-20','M. Yao','HT','Mécanique',547400,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(2,1,1,3,1,'F-0002','FAC--9747','2026-06-24','NSIA Assurances','FNE','Mécanique',73100,NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(3,1,1,5,1,'F-0003','FAC--7171','2026-06-22','M. Yao','FNE','Mécanique',670640,NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(4,1,1,6,1,'F-0004','FAC--5249','2026-06-27','M. N\'Dri','FNE','Sinistre',606060,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(5,1,1,9,1,'F-0005','FAC--3454','2026-06-30','Nestlé CI','HT','Sinistre',545300,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(6,1,1,11,1,'F-0006','FAC--3025','2026-06-30','Bernabé','HT','Mécanique',198550,NULL,NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(7,1,1,12,1,'F-0007','FAC--5068','2026-07-02','Mme Aka','FNE','Sinistre',107310,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(8,1,1,13,1,'F-0008','FAC--7145','2026-06-29','Orange CI','FNE','Sinistre',300300,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(9,1,1,17,1,'F-0009','FAC--5969','2026-07-09','SODECI','FNE','Mécanique',93450,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(10,1,1,18,1,'F-0010','FAC--2361','2026-07-08','SIFCA','FNE','Sinistre',391980,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(11,1,1,19,1,'F-0011','FAC--9132','2026-07-08','M. Ouattara','FNE','Sinistre',415160,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(12,1,1,21,1,'F-0012','FAC--5197','2026-07-17','Groupe Alliances','FNE','Mécanique',204610,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(13,1,1,23,1,'F-0013','FAC--4115','2026-07-15','Bernabé','FNE','Sinistre',514650,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(14,1,1,25,2,'F-0014','FAC--1074','2026-07-17','Mme Aka','HT','Sinistre',178120,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(15,1,1,27,2,'F-0015','FAC--4061','2026-07-20','Orange CI','FNE','Mécanique',564000,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(16,1,1,29,1,'F-0016','FAC--5865','2026-07-27','M. Diallo','HT','Sinistre',159390,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(17,1,1,30,1,'F-0017','FAC--4723','2026-07-22','Bernabé','FNE','Mécanique',74400,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(18,1,1,31,2,'F-0018','FAC--6107','2026-07-26','Nestlé CI','HT','Mécanique',349920,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(19,1,1,34,1,'F-0019','FAC--3016','2026-08-02','Nestlé CI','FNE','Mécanique',270840,NULL,NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(20,1,1,37,2,'F-0020','FAC--1239','2026-08-02','CIE','FNE','Sinistre',453960,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(21,1,1,39,1,'F-0021','FAC--7054','2026-08-05','Mme Aka','HT','Sinistre',725350,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(22,1,1,40,1,'F-0022','FAC--6534','2026-08-07','Groupe Alliances','HT','Mécanique',401820,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(23,1,1,41,1,'F-0023','FAC--2791','2026-08-06','M. N\'Dri','FNE','Mécanique',570350,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(24,1,1,42,1,'F-0024','FAC--5744','2026-08-07','SIFCA','FNE','Mécanique',201810,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(25,1,1,45,1,'F-0025','FAC--5319','2026-08-09','Bernabé','HT','Mécanique',585000,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(26,1,1,55,1,'F-0026','FAC--9445','2026-08-13','Nestlé CI','FNE','Mécanique',198090,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(27,1,1,58,1,'F-0027','FAC--6142','2026-08-12','M. Kouassi','FNE','Sinistre',133440,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(28,1,1,60,1,'F-0028','FAC--8317','2026-08-11','Mme Aka','FNE','Sinistre',474300,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36');
/*!40000 ALTER TABLE `factures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
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
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `expediteur_id` bigint unsigned NOT NULL,
  `corps` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_expediteur_id_foreign` (`expediteur_id`),
  KEY `messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_expediteur_id_foreign` FOREIGN KEY (`expediteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,1,2,'Bonjour, où en est-on sur l\'objectif du site ce mois-ci ?','2026-08-09 14:18:36','2026-08-09 14:18:36'),(2,1,3,'Nous sommes à environ 80 % à dix jours de la fin, ça devrait passer.','2026-08-09 14:46:36','2026-08-09 14:46:36'),(3,1,2,'Très bien, tenez-moi au courant s\'il y a un point de blocage.','2026-08-09 15:00:36','2026-08-09 15:00:36'),(4,2,2,'Merci de transmettre toutes vos saisies en attente avant vendredi soir pour la clôture.','2026-08-11 14:18:37','2026-08-11 14:18:37'),(5,3,4,'Bonjour, un client demande un devis urgent pour demain matin, possible ?','2026-08-12 14:18:37','2026-08-12 14:18:37'),(6,3,3,'Oui, transmettez-moi les informations, je le prépare ce soir.','2026-08-12 15:40:37','2026-08-12 15:40:37'),(7,4,2,'Bonjour, un point sur la génération des factures FNE : est-ce automatique ?','2026-08-07 14:18:37','2026-08-07 14:18:37'),(8,4,1,'Bonjour, oui, le type de facture est choisi à la saisie, aucune action supplémentaire nécessaire.','2026-08-07 15:33:37','2026-08-07 15:33:37');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_01_02_000000_create_companies_table',1),(5,'2025_01_02_000001_create_villes_table',1),(6,'2025_01_02_000002_add_champs_entreprise_to_users_table',1),(7,'2025_01_02_000003_create_sites_table',1),(8,'2025_01_02_000004_create_commerciaux_table',1),(9,'2025_01_02_000005_create_prospections_table',1),(10,'2025_01_02_000006_create_devis_table',1),(11,'2025_01_02_000007_create_factures_table',1),(12,'2025_01_02_000008_create_encaissements_table',1),(13,'2025_01_02_000009_create_charges_table',1),(14,'2025_01_02_000010_create_saisies_journalieres_table',1),(15,'2025_01_02_000011_create_compteurs_documents_table',1),(16,'2025_01_02_000012_add_google_id_to_users_table',1),(17,'2025_01_02_000013_add_identification_to_entreprises_table',1),(18,'2025_01_02_000014_add_coordonnees_to_villes_table',1),(19,'2025_01_02_000015_add_saisie_commercial_to_prospections_table',1),(20,'2025_01_02_000016_create_referentiels_table',1),(21,'2025_01_02_000017_create_donnees_libres_table',1),(22,'2025_01_02_000018_add_photo_to_users_table',1),(23,'2025_01_02_000019_create_messagerie_tables',1),(24,'2025_01_02_000020_create_notes_tables',1),(25,'2025_01_02_000021_add_habilitations_to_users_table',1),(26,'2025_01_02_000022_create_abonnements_push_table',1),(27,'2025_01_02_000023_add_ville_site_to_users_table',1),(28,'2026_08_02_025109_add_two_factor_columns_to_users_table',1),(29,'2026_08_02_025109_create_permission_tables',1),(30,'2026_08_02_054749_create_activity_log_table',1),(31,'2026_08_02_054750_add_event_column_to_activity_log_table',1),(32,'2026_08_02_054751_add_batch_uuid_column_to_activity_log_table',1),(33,'2026_08_13_015709_rename_carrosserie_to_sinistre_in_activite_enums',1),(34,'2026_08_13_034405_add_dates_passage_devis_to_prospections_table',1),(35,'2026_08_13_075004_add_objectifs_par_activite_to_commerciaux_table',1),(36,'2026_08_13_080844_create_exercices_table',1),(37,'2026_08_13_125916_add_nfa_type_to_compteurs_documents_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `entreprise_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`entreprise_id`,`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  KEY `model_has_permissions_permission_id_foreign` (`permission_id`),
  KEY `model_has_permissions_team_foreign_key_index` (`entreprise_id`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `entreprise_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`entreprise_id`,`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  KEY `model_has_roles_role_id_foreign` (`role_id`),
  KEY `model_has_roles_team_foreign_key_index` (`entreprise_id`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1,0),(1,'App\\Models\\User',6,0),(2,'App\\Models\\User',2,1),(3,'App\\Models\\User',3,1),(4,'App\\Models\\User',4,1),(5,'App\\Models\\User',5,1);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `dossier_note_id` bigint unsigned DEFAULT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `corps` text COLLATE utf8mb4_unicode_ci,
  `est_epinglee` tinyint(1) NOT NULL DEFAULT '0',
  `rappel_le` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notes_entreprise_id_foreign` (`entreprise_id`),
  KEY `notes_dossier_note_id_foreign` (`dossier_note_id`),
  KEY `notes_user_id_dossier_note_id_index` (`user_id`,`dossier_note_id`),
  CONSTRAINT `notes_dossier_note_id_foreign` FOREIGN KEY (`dossier_note_id`) REFERENCES `dossiers_notes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notes_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES (1,1,4,1,'Garage Koffi — flotte véhicules','Intéressé par un contrat annuel entretien. Relancer après le devis sinistre.',1,NULL,'2026-08-13 14:18:38','2026-08-13 14:18:38'),(2,1,4,2,'Mme Traoré — devis en attente','Devis envoyé le 28, attend le retour assurance.',0,'2026-08-15','2026-08-13 14:18:38','2026-08-13 14:18:38'),(3,1,4,NULL,'Idée : carnet clients zone industrielle','Prospecter davantage la zone industrielle en début de semaine.',0,NULL,'2026-08-13 14:18:38','2026-08-13 14:18:38');
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications_app`
--

DROP TABLE IF EXISTS `notifications_app`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications_app` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `canal` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'systeme',
  `niveau` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `corps` text COLLATE utf8mb4_unicode_ci,
  `lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lu_le` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_app_user_id_lu_le_index` (`user_id`,`lu_le`),
  CONSTRAINT `notifications_app_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications_app`
--

LOCK TABLES `notifications_app` WRITE;
/*!40000 ALTER TABLE `notifications_app` DISABLE KEYS */;
INSERT INTO `notifications_app` VALUES (1,3,'gestion','alerte','3 prospection(s) à valider','Koffi Yao vient de transmettre 3 prospection(s).','http://127.0.0.1:8004/saisie-du-jour',NULL,'2026-08-13 14:18:37','2026-08-13 14:18:37'),(2,4,'gestion','succes','Prospection validée','Votre responsable a validé 2 prospection(s).','http://127.0.0.1:8004/mes-prospections','2026-08-13 11:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(3,4,'gestion','critique','Prospection refusée','Votre responsable a refusé une prospection. Motif : coordonnées client incomplètes.','http://127.0.0.1:8004/mes-prospections','2026-08-11 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(4,2,'systeme','info','Clôture mensuelle à venir','Pensez à valider toutes les saisies avant la fin du mois.',NULL,'2026-08-12 14:18:37','2026-08-13 14:18:37','2026-08-13 14:18:37'),(5,3,'systeme','alerte','Objectif du mois','Le site est à 80 % de l\'objectif à dix jours de l\'échéance.',NULL,NULL,'2026-08-13 14:18:37','2026-08-13 14:18:37');
/*!40000 ALTER TABLE `notifications_app` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pieces_jointes`
--

DROP TABLE IF EXISTS `pieces_jointes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pieces_jointes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint unsigned NOT NULL,
  `nom_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_mime` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pieces_jointes_message_id_foreign` (`message_id`),
  CONSTRAINT `pieces_jointes_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pieces_jointes`
--

LOCK TABLES `pieces_jointes` WRITE;
/*!40000 ALTER TABLE `pieces_jointes` DISABLE KEYS */;
/*!40000 ALTER TABLE `pieces_jointes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prospections`
--

DROP TABLE IF EXISTS `prospections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `commercial_id` bigint unsigned NOT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `client` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `localisation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moyen` enum('RDV','Téléphone','Mail') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RDV',
  `activite` enum('Mécanique','Sinistre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `passage` tinyint(1) NOT NULL DEFAULT '0',
  `date_passage` date DEFAULT NULL,
  `devis_apres_passage` tinyint(1) NOT NULL DEFAULT '0',
  `date_devis` date DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `statut_validation` enum('Brouillon','Transmise','Validée','Refusée') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Validée',
  `motif_refus` text COLLATE utf8mb4_unicode_ci,
  `transmise_le` timestamp NULL DEFAULT NULL,
  `cree_par` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prospections_entreprise_id_numero_unique` (`entreprise_id`,`numero`),
  KEY `prospections_site_id_foreign` (`site_id`),
  KEY `prospections_commercial_id_foreign` (`commercial_id`),
  KEY `prospections_cree_par_foreign` (`cree_par`),
  KEY `prospections_entreprise_id_site_id_date_index` (`entreprise_id`,`site_id`,`date`),
  CONSTRAINT `prospections_commercial_id_foreign` FOREIGN KEY (`commercial_id`) REFERENCES `commerciaux` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospections_cree_par_foreign` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospections_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospections_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prospections`
--

LOCK TABLES `prospections` WRITE;
/*!40000 ALTER TABLE `prospections` DISABLE KEYS */;
INSERT INTO `prospections` VALUES (1,1,1,1,'P-0001','2026-06-15','M. Yao','N\'Gattakro','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:13','2026-08-13 14:18:13'),(2,1,1,1,'P-0002','2026-06-15','CIE','N\'Gattakro','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(3,1,1,1,'P-0003','2026-06-15','Groupe Alliances','N\'Gattakro','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(4,1,1,1,'P-0004','2026-06-15','M. Koné','N\'Gattakro','RDV','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(5,1,1,2,'P-0005','2026-06-16','Mme Gnahoré','N\'Gattakro','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(6,1,1,1,'P-0006','2026-06-16','NSIA Assurances','Koko','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:14','2026-08-13 14:18:14'),(7,1,1,1,'P-0007','2026-06-16','M. Koné','Air France','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(8,1,1,1,'P-0008','2026-06-17','M. Yao','Zone industrielle','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:15','2026-08-13 14:18:15'),(9,1,1,1,'P-0009','2026-06-18','Bolloré Transport','Sokoura','Téléphone','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(10,1,1,1,'P-0010','2026-06-18','Mme Aka','Koko','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(11,1,1,1,'P-0011','2026-06-18','M. N\'Dri','Bouaké centre','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(12,1,1,1,'P-0012','2026-06-18','Nestlé CI','Koko','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(13,1,1,2,'P-0013','2026-06-19','Groupe Alliances','Koko','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:16','2026-08-13 14:18:16'),(14,1,1,1,'P-0014','2026-06-20','M. Kouassi','Zone industrielle','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(15,1,1,1,'P-0015','2026-06-20','Mme Aka','N\'Gattakro','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(16,1,1,2,'P-0016','2026-06-20','M. Yao','Zone industrielle','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(17,1,1,1,'P-0017','2026-06-20','Orange CI','Zone industrielle','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(18,1,1,1,'P-0018','2026-06-22','Nestlé CI','Koko','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:17','2026-08-13 14:18:17'),(19,1,1,1,'P-0019','2026-06-22','Groupe Alliances','Koko','Mail','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(20,1,1,2,'P-0020','2026-06-22','Mme Bamba','Zone industrielle','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(21,1,1,1,'P-0021','2026-06-23','Mme Traoré','Bouaké centre','RDV','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(22,1,1,1,'P-0022','2026-06-24','Groupe Alliances','Bouaké centre','RDV','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(23,1,1,1,'P-0023','2026-06-24','Mme Gnahoré','Bouaké centre','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(24,1,1,1,'P-0024','2026-06-24','M. Kouassi','Bouaké centre','Téléphone','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(25,1,1,1,'P-0025','2026-06-25','Mme Bamba','Bouaké centre','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(26,1,1,1,'P-0026','2026-06-25','NSIA Assurances','Bouaké centre','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:18','2026-08-13 14:18:18'),(27,1,1,1,'P-0027','2026-06-25','Bernabé','N\'Gattakro','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(28,1,1,1,'P-0028','2026-06-25','M. N\'Dri','Bouaké centre','RDV','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(29,1,1,1,'P-0029','2026-06-26','Mme Aka','N\'Gattakro','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:19','2026-08-13 14:18:19'),(30,1,1,1,'P-0030','2026-06-27','Orange CI','Zone industrielle','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(31,1,1,2,'P-0031','2026-06-27','M. Koné','Zone industrielle','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(32,1,1,2,'P-0032','2026-06-27','Mme Aka','Bouaké centre','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:20','2026-08-13 14:18:20'),(33,1,1,1,'P-0033','2026-06-27','M. N\'Dri','N\'Gattakro','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(34,1,1,1,'P-0034','2026-06-29','Mme Aka','Air France','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(35,1,1,2,'P-0035','2026-06-29','Bolloré Transport','Sokoura','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(36,1,1,1,'P-0036','2026-06-30','M. Ouattara','Zone industrielle','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(37,1,1,1,'P-0037','2026-06-30','M. Yao','Air France','Mail','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(38,1,1,1,'P-0038','2026-07-01','Mme Bamba','N\'Gattakro','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(39,1,1,1,'P-0039','2026-07-01','SODECI','Air France','RDV','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:21','2026-08-13 14:18:21'),(40,1,1,1,'P-0040','2026-07-01','Bernabé','Bouaké centre','RDV','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(41,1,1,1,'P-0041','2026-07-01','Orange CI','N\'Gattakro','Mail','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(42,1,1,1,'P-0042','2026-07-02','NSIA Assurances','Koko','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(43,1,1,1,'P-0043','2026-07-02','Mme Traoré','Zone industrielle','Téléphone','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(44,1,1,1,'P-0044','2026-07-02','SIFCA','N\'Gattakro','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(45,1,1,1,'P-0045','2026-07-02','M. N\'Dri','Zone industrielle','Téléphone','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:22','2026-08-13 14:18:22'),(46,1,1,1,'P-0046','2026-07-03','M. Ouattara','Zone industrielle','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(47,1,1,1,'P-0047','2026-07-04','Bolloré Transport','Sokoura','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(48,1,1,1,'P-0048','2026-07-04','Mme Bamba','Bouaké centre','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(49,1,1,2,'P-0049','2026-07-04','SIFCA','Sokoura','RDV','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(50,1,1,1,'P-0050','2026-07-06','Orange CI','Sokoura','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(51,1,1,2,'P-0051','2026-07-07','Bernabé','Sokoura','Mail','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(52,1,1,2,'P-0052','2026-07-07','CFAO Motors','Air France','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(53,1,1,1,'P-0053','2026-07-07','Orange CI','Sokoura','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(54,1,1,1,'P-0054','2026-07-07','Mme Traoré','Bouaké centre','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(55,1,1,1,'P-0055','2026-07-08','SIFCA','N\'Gattakro','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(56,1,1,1,'P-0056','2026-07-08','M. Kouassi','Sokoura','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(57,1,1,1,'P-0057','2026-07-09','Groupe Alliances','Koko','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(58,1,1,1,'P-0058','2026-07-09','SODECI','Koko','Téléphone','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(59,1,1,1,'P-0059','2026-07-09','Mme Gnahoré','Air France','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(60,1,1,1,'P-0060','2026-07-09','Nestlé CI','Bouaké centre','Téléphone','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:23','2026-08-13 14:18:23'),(61,1,1,1,'P-0061','2026-07-10','Bolloré Transport','Koko','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(62,1,1,1,'P-0062','2026-07-11','Bernabé','Koko','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(63,1,1,1,'P-0063','2026-07-11','Nestlé CI','N\'Gattakro','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(64,1,1,2,'P-0064','2026-07-13','Mme Aka','Koko','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(65,1,1,1,'P-0065','2026-07-13','CIE','Sokoura','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(66,1,1,1,'P-0066','2026-07-13','Nestlé CI','Sokoura','RDV','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(67,1,1,2,'P-0067','2026-07-14','M. Yao','Koko','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(68,1,1,1,'P-0068','2026-07-15','Mme Aka','Bouaké centre','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:24','2026-08-13 14:18:24'),(69,1,1,1,'P-0069','2026-07-15','SODECI','Sokoura','RDV','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(70,1,1,2,'P-0070','2026-07-16','M. Diallo','Koko','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(71,1,1,2,'P-0071','2026-07-16','NSIA Assurances','N\'Gattakro','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(72,1,1,2,'P-0072','2026-07-16','Orange CI','Air France','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(73,1,1,2,'P-0073','2026-07-17','M. Yao','Bouaké centre','Téléphone','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(74,1,1,1,'P-0074','2026-07-17','CFAO Motors','Zone industrielle','RDV','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(75,1,1,1,'P-0075','2026-07-18','Mme Traoré','Zone industrielle','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(76,1,1,2,'P-0076','2026-07-18','CIE','Zone industrielle','Mail','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(77,1,1,1,'P-0077','2026-07-18','M. N\'Dri','Air France','RDV','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(78,1,1,1,'P-0078','2026-07-20','NSIA Assurances','Bouaké centre','Mail','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(79,1,1,1,'P-0079','2026-07-20','Bolloré Transport','Koko','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:25','2026-08-13 14:18:25'),(80,1,1,1,'P-0080','2026-07-20','M. Diallo','Zone industrielle','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(81,1,1,1,'P-0081','2026-07-21','Bernabé','N\'Gattakro','RDV','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(82,1,1,1,'P-0082','2026-07-22','Mme Aka','Sokoura','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(83,1,1,2,'P-0083','2026-07-22','Nestlé CI','Sokoura','RDV','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:26','2026-08-13 14:18:26'),(84,1,1,1,'P-0084','2026-07-22','CIE','Koko','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:27','2026-08-13 14:18:27'),(85,1,1,2,'P-0085','2026-07-23','NSIA Assurances','Zone industrielle','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:27','2026-08-13 14:18:27'),(86,1,1,1,'P-0086','2026-07-23','SIFCA','Air France','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:27','2026-08-13 14:18:27'),(87,1,1,1,'P-0087','2026-07-23','Groupe Alliances','Bouaké centre','RDV','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:28','2026-08-13 14:18:28'),(88,1,1,1,'P-0088','2026-07-23','Mme Aka','N\'Gattakro','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:29','2026-08-13 14:18:29'),(89,1,1,1,'P-0089','2026-07-24','Nestlé CI','Bouaké centre','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:29','2026-08-13 14:18:29'),(90,1,1,1,'P-0090','2026-07-24','NSIA Assurances','N\'Gattakro','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:29','2026-08-13 14:18:29'),(91,1,1,2,'P-0091','2026-07-24','Orange CI','Air France','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:30','2026-08-13 14:18:30'),(92,1,1,1,'P-0092','2026-07-25','Bernabé','Koko','RDV','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:30','2026-08-13 14:18:30'),(93,1,1,1,'P-0093','2026-07-25','Orange CI','N\'Gattakro','Téléphone','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:30','2026-08-13 14:18:30'),(94,1,1,1,'P-0094','2026-07-25','Bolloré Transport','Koko','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(95,1,1,2,'P-0095','2026-07-25','Bolloré Transport','Air France','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(96,1,1,1,'P-0096','2026-07-27','Nestlé CI','Zone industrielle','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:31','2026-08-13 14:18:31'),(97,1,1,1,'P-0097','2026-07-27','M. N\'Dri','N\'Gattakro','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(98,1,1,2,'P-0098','2026-07-27','M. Yao','N\'Gattakro','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(99,1,1,1,'P-0099','2026-07-27','Nestlé CI','Air France','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(100,1,1,2,'P-0100','2026-07-28','M. Yao','Zone industrielle','RDV','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(101,1,1,2,'P-0101','2026-07-28','M. Ouattara','Sokoura','RDV','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(102,1,1,1,'P-0102','2026-07-28','M. Diallo','N\'Gattakro','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(103,1,1,2,'P-0103','2026-07-28','CIE','Air France','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(104,1,1,1,'P-0104','2026-07-29','SODECI','Bouaké centre','Mail','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(105,1,1,1,'P-0105','2026-07-29','Bolloré Transport','Zone industrielle','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:32','2026-08-13 14:18:32'),(106,1,1,1,'P-0106','2026-07-30','CIE','Zone industrielle','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(107,1,1,1,'P-0107','2026-07-31','CFAO Motors','N\'Gattakro','RDV','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(108,1,1,1,'P-0108','2026-07-31','Mme Aka','Air France','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(109,1,1,1,'P-0109','2026-07-31','Groupe Alliances','Air France','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(110,1,1,1,'P-0110','2026-07-31','Bolloré Transport','Sokoura','Téléphone','Sinistre',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(111,1,1,1,'P-0111','2026-08-01','M. N\'Dri','Zone industrielle','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(112,1,1,2,'P-0112','2026-08-01','Mme Gnahoré','Sokoura','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(113,1,1,1,'P-0113','2026-08-01','SIFCA','Air France','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(114,1,1,1,'P-0114','2026-08-01','Nestlé CI','Zone industrielle','Téléphone','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(115,1,1,2,'P-0115','2026-08-03','Bolloré Transport','Bouaké centre','Mail','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(116,1,1,1,'P-0116','2026-08-03','Bernabé','Sokoura','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(117,1,1,1,'P-0117','2026-08-04','M. Kouassi','Koko','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(118,1,1,1,'P-0118','2026-08-04','NSIA Assurances','Bouaké centre','RDV','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(119,1,1,1,'P-0119','2026-08-04','NSIA Assurances','N\'Gattakro','RDV','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:33','2026-08-13 14:18:33'),(120,1,1,1,'P-0120','2026-08-04','Bernabé','Koko','Téléphone','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(121,1,1,1,'P-0121','2026-08-05','M. Kouassi','Zone industrielle','Téléphone','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(122,1,1,1,'P-0122','2026-08-05','Mme Bamba','Sokoura','Téléphone','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(123,1,1,1,'P-0123','2026-08-05','SIFCA','Bouaké centre','RDV','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(124,1,1,1,'P-0124','2026-08-06','Bolloré Transport','Bouaké centre','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(125,1,1,1,'P-0125','2026-08-06','Bernabé','Air France','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(126,1,1,1,'P-0126','2026-08-06','Bolloré Transport','Zone industrielle','RDV','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(127,1,1,2,'P-0127','2026-08-07','SODECI','Sokoura','RDV','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(128,1,1,1,'P-0128','2026-08-08','Nestlé CI','N\'Gattakro','Téléphone','Mécanique',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(129,1,1,1,'P-0129','2026-08-10','Bolloré Transport','Air France','Mail','Mécanique',0,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(130,1,1,2,'P-0130','2026-08-10','Groupe Alliances','Zone industrielle','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(131,1,1,1,'P-0131','2026-08-10','SIFCA','N\'Gattakro','Téléphone','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(132,1,1,1,'P-0132','2026-08-10','NSIA Assurances','Sokoura','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(133,1,1,1,'P-0133','2026-08-11','M. Diallo','Zone industrielle','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Transmise',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(134,1,1,1,'P-0134','2026-08-11','SIFCA','Air France','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:34','2026-08-13 14:18:34'),(135,1,1,1,'P-0135','2026-08-12','M. N\'Dri','Sokoura','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(136,1,1,1,'P-0136','2026-08-12','M. Koné','Air France','Mail','Mécanique',1,NULL,0,NULL,NULL,'Transmise',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(137,1,1,1,'P-0137','2026-08-12','Groupe Alliances','Koko','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Refusée',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(138,1,1,1,'P-0138','2026-08-12','M. Diallo','Koko','Mail','Sinistre',1,NULL,0,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(139,1,1,1,'P-0139','2026-08-13','Groupe Alliances','Zone industrielle','RDV','Mécanique',1,NULL,1,NULL,NULL,'Transmise',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(140,1,1,1,'P-0140','2026-08-13','Mme Aka','Zone industrielle','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(141,1,1,1,'P-0141','2026-08-13','Mme Bamba','Bouaké centre','Mail','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(142,1,1,1,'P-0142','2026-08-13','Nestlé CI','Zone industrielle','Mail','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:35','2026-08-13 14:18:35'),(143,1,1,1,'P-0143','2026-08-12','M. Ouattara','Air France','Téléphone','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(144,1,1,1,'P-0144','2026-08-12','Mme Aka','Sokoura','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(145,1,1,1,'P-0145','2026-08-12','M. Kouassi','Koko','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(146,1,1,1,'P-0146','2026-08-11','CFAO Motors','Zone industrielle','Téléphone','Mécanique',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36'),(147,1,1,1,'P-0147','2026-08-11','Mme Aka','Air France','RDV','Sinistre',1,NULL,1,NULL,NULL,'Validée',NULL,NULL,NULL,'2026-08-13 14:18:36','2026-08-13 14:18:36');
/*!40000 ALTER TABLE `prospections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referentiels`
--

DROP TABLE IF EXISTS `referentiels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referentiels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rang` smallint unsigned NOT NULL DEFAULT '0',
  `est_actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referentiels_entreprise_id_type_valeur_unique` (`entreprise_id`,`type`,`valeur`),
  KEY `referentiels_entreprise_id_type_est_actif_index` (`entreprise_id`,`type`,`est_actif`),
  CONSTRAINT `referentiels_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referentiels`
--

LOCK TABLES `referentiels` WRITE;
/*!40000 ALTER TABLE `referentiels` DISABLE KEYS */;
/*!40000 ALTER TABLE `referentiels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_entreprise_id_name_guard_name_unique` (`entreprise_id`,`name`,`guard_name`),
  KEY `roles_team_foreign_key_index` (`entreprise_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,0,'super_admin','web','2026-08-13 14:18:03','2026-08-13 14:18:03'),(2,1,'gerant','web','2026-08-13 14:18:06','2026-08-13 14:18:06'),(3,1,'responsable_site','web','2026-08-13 14:18:06','2026-08-13 14:18:06'),(4,1,'commercial','web','2026-08-13 14:18:06','2026-08-13 14:18:06'),(5,1,'caissier','web','2026-08-13 14:18:06','2026-08-13 14:18:06');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saisies_journalieres`
--

DROP TABLE IF EXISTS `saisies_journalieres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saisies_journalieres` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `vehicules_sans_facture` int unsigned NOT NULL DEFAULT '0',
  `commentaire_prospects` text COLLATE utf8mb4_unicode_ci,
  `commentaire_devis` text COLLATE utf8mb4_unicode_ci,
  `commentaire_ca` text COLLATE utf8mb4_unicode_ci,
  `commentaire_tresorerie` text COLLATE utf8mb4_unicode_ci,
  `commentaire_charges` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saisies_journalieres_site_id_date_unique` (`site_id`,`date`),
  KEY `saisies_journalieres_entreprise_id_foreign` (`entreprise_id`),
  CONSTRAINT `saisies_journalieres_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saisies_journalieres_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saisies_journalieres`
--

LOCK TABLES `saisies_journalieres` WRITE;
/*!40000 ALTER TABLE `saisies_journalieres` DISABLE KEYS */;
/*!40000 ALTER TABLE `saisies_journalieres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('JglzpubCdG3pXkFKTpBwRKe21kOP1OahWBDpyiv4',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJCVmwzUkpHVzVPQ3Fxc28zZmV6Y2JTWm9xQ2t6VXcwbHp6N0tpWGtwIiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDA0XC9jb21tZXJjaWF1eCIsInJvdXRlIjoiY29tbWVyY2lhdXgifX0=',1786631170),('zPKdjP0VpkZBB1Ea9aCsjQwsESgBV00GDURqaWhR',NULL,'127.0.0.1','curl/8.16.0','eyJfdG9rZW4iOiJCMm1aTVFOUkVVWkRuWWhDUmdWTXE5NWJWNFM0VW5tZDcwT09tb1Z0IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MTIzXC9jb25uZXhpb24iLCJyb3V0ZSI6ImNvbm5leGlvbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1786628322);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sites`
--

DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `ville_id` bigint unsigned NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activite` enum('Mécanique','Sinistre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `responsable_id` bigint unsigned DEFAULT NULL,
  `est_actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sites_ville_id_activite_unique` (`ville_id`,`activite`),
  KEY `sites_responsable_id_foreign` (`responsable_id`),
  KEY `sites_entreprise_id_est_actif_index` (`entreprise_id`,`est_actif`),
  CONSTRAINT `sites_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sites_responsable_id_foreign` FOREIGN KEY (`responsable_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sites_ville_id_foreign` FOREIGN KEY (`ville_id`) REFERENCES `villes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sites`
--

LOCK TABLES `sites` WRITE;
/*!40000 ALTER TABLE `sites` DISABLE KEYS */;
INSERT INTO `sites` VALUES (1,1,1,'Abidjan — Mécanique','Mécanique',NULL,1,'2026-08-13 14:18:09','2026-08-13 14:18:09'),(2,1,1,'Abidjan — Sinistre','Sinistre',NULL,1,'2026-08-13 14:18:09','2026-08-13 14:18:09');
/*!40000 ALTER TABLE `sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned DEFAULT NULL,
  `ville_id` bigint unsigned DEFAULT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `cree_par_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_chemin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `est_actif` tinyint(1) NOT NULL DEFAULT '1',
  `est_fondateur` tinyint(1) NOT NULL DEFAULT '0',
  `habilitations` json DEFAULT NULL,
  `doit_changer_mot_de_passe` tinyint(1) NOT NULL DEFAULT '0',
  `derniere_connexion_le` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  KEY `users_entreprise_id_foreign` (`entreprise_id`),
  KEY `users_cree_par_id_foreign` (`cree_par_id`),
  KEY `users_ville_id_foreign` (`ville_id`),
  KEY `users_site_id_foreign` (`site_id`),
  CONSTRAINT `users_cree_par_id_foreign` FOREIGN KEY (`cree_par_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ville_id_foreign` FOREIGN KEY (`ville_id`) REFERENCES `villes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,NULL,NULL,NULL,'Super Admin','superadmin@plateforme.local',NULL,NULL,NULL,'2026-08-13 14:18:03','$2y$12$blZtrc5C8eHnGQcR8w5xneUKjYr0YKDFMDJxkQiEYHUBKe0tKYe/C',NULL,NULL,NULL,1,1,NULL,0,NULL,NULL,'2026-08-13 14:18:06','2026-08-13 14:18:06'),(2,1,NULL,NULL,NULL,'Jean-Baptiste Kouassi','gerant@gmail.com',NULL,NULL,NULL,'2026-08-13 14:18:06','$2y$12$b0nM293nGWAoaKAV5T8HHeSSpZJ92EMprtQCTpUdcIuMdCjCf66Q.',NULL,NULL,NULL,1,0,NULL,0,'2026-08-13 14:23:39',NULL,'2026-08-13 14:18:08','2026-08-13 14:23:39'),(3,1,NULL,NULL,NULL,'Marie-Claire Aya','responsable@gmail.com',NULL,NULL,NULL,'2026-08-13 14:18:08','$2y$12$a5plavtFlMeVFQGpN7QuC.XxaBl2QsO0WEN0.G5YHEWHzYu3zZuTW',NULL,NULL,NULL,1,0,NULL,0,NULL,NULL,'2026-08-13 14:18:09','2026-08-13 14:18:09'),(4,1,NULL,NULL,NULL,'Koffi Yao','commercial@gmail.com',NULL,NULL,NULL,'2026-08-13 14:18:09','$2y$12$TNV3aJTDZ53DvYrGKGtsI.I.IRy.tU6lAtqdJ/xCGEET/oEAqgpfu',NULL,NULL,NULL,1,0,NULL,0,NULL,NULL,'2026-08-13 14:18:12','2026-08-13 14:18:12'),(5,1,NULL,1,NULL,'Fatou Diabaté','caissier@gmail.com',NULL,NULL,NULL,'2026-08-13 14:18:12','$2y$12$GG8NdS1ROWkUN5Cw0CkLPepsbVBaNYyeg6S/guleX7yvVGVUdWIQi',NULL,NULL,NULL,1,0,NULL,0,NULL,NULL,'2026-08-13 14:18:13','2026-08-13 14:18:13'),(6,NULL,NULL,NULL,1,'Support Plateforme','support@plateforme.local',NULL,NULL,NULL,'2026-08-13 14:18:38','$2y$12$caqqjvpa8vPWEqOfXBebI.f5ofJqvxPR0/YRaITq8S12YYNZIsu4S',NULL,NULL,NULL,1,0,'[\"entreprises\", \"journal\"]',1,NULL,NULL,'2026-08-13 14:18:41','2026-08-13 14:18:41');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `villes`
--

DROP TABLE IF EXISTS `villes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `villes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entreprise_id` bigint unsigned NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commune` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `couleur` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#2563EB',
  `responsable_id` bigint unsigned DEFAULT NULL,
  `est_actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `villes_entreprise_id_code_unique` (`entreprise_id`,`code`),
  KEY `villes_responsable_id_foreign` (`responsable_id`),
  CONSTRAINT `villes_entreprise_id_foreign` FOREIGN KEY (`entreprise_id`) REFERENCES `entreprises` (`id`) ON DELETE CASCADE,
  CONSTRAINT `villes_responsable_id_foreign` FOREIGN KEY (`responsable_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `villes`
--

LOCK TABLES `villes` WRITE;
/*!40000 ALTER TABLE `villes` DISABLE KEYS */;
INSERT INTO `villes` VALUES (1,1,'ABJ','Abidjan','Yopougon','+225 27 23 45 67 90','Zone industrielle, Rue des Artisans','#2563EB',3,1,'2026-08-13 14:18:09','2026-08-13 14:18:09');
/*!40000 ALTER TABLE `villes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'gestionsites'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-13 14:26:17
