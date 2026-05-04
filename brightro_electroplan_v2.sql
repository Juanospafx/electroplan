-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 29-04-2026 a las 15:57:01
-- Versión del servidor: 10.6.24-MariaDB-cll-lve
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `brightro_electroplan_v2`
--
CREATE DATABASE IF NOT EXISTS `brightro_electroplan_v2` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `brightro_electroplan_v2`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `directory`
--

CREATE TABLE `directory` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `directory`
--

INSERT INTO `directory` (`project_id`, `user_id`, `assigned_at`) VALUES
(32, 2, '2026-04-29 13:57:51'),
(33, 2, '2026-04-29 15:06:10'),
(34, 2, '2026-04-29 15:30:09'),
(35, 2, '2026-04-29 15:53:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `field_report_attachments`
--

CREATE TABLE `field_report_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `field_report_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(191) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `storage_path` varchar(1024) NOT NULL,
  `public_url` varchar(1024) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `sub_folder_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `file_type` enum('pdf','image') NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `version_group_id` varchar(50) DEFAULT NULL,
  `version_number` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `files`
--

INSERT INTO `files` (`id`, `project_id`, `folder_id`, `sub_folder_id`, `filename`, `filepath`, `file_type`, `uploaded_by`, `uploaded_at`, `deleted_at`, `version_group_id`, `version_number`) VALUES
(87, 32, 453, NULL, 'RFI#22 Emergency Light Relocation Due to Conflict.pdf', 'uploads/1777485519_RFI22_Emergency_Light_Relocation_Due_to_Conflict.pdf', 'pdf', 2, '2026-04-29 13:58:39', NULL, 'vgroup_69f246cf60e93', 1),
(88, 32, 454, NULL, 'LAND25147813_REXORLBRTRNX13709930244913916200.pdf', 'uploads/1777485546_LAND25147813_REXORLBRTRNX13709930244913916200.pdf', 'pdf', 2, '2026-04-29 13:59:06', NULL, 'vgroup_69f246ea8bbbd', 1),
(89, 32, 458, NULL, 'Waiver 150245.pdf', 'uploads/1777485607_Waiver_150245.pdf', 'pdf', 2, '2026-04-29 14:00:07', NULL, 'vgroup_69f24727a5b4d', 1),
(90, 32, 459, NULL, 'Legacy of Leesburg Change Orders Updates 06-24-2024 with breakdown.pdf', 'uploads/1777485667_Legacy_of_Leesburg_Change_Orders_Updates_06-24-2024_with_breakdown.pdf', 'pdf', 2, '2026-04-29 14:01:07', NULL, 'vgroup_69f247636f725', 1),
(91, 32, 460, NULL, 'Costs Brightronix updated 07-18.xlsx', 'uploads/1777485688_Costs_Brightronix_updated_07-18.xlsx', '', 2, '2026-04-29 14:01:28', NULL, 'vgroup_69f24778c618d', 1),
(92, 32, 461, NULL, 'Cabana Building Plans.pdf', 'uploads/1777485860_Cabana_Building_Plans.pdf', 'pdf', 2, '2026-04-29 14:04:21', NULL, 'vgroup_69f2482454c75', 1),
(93, 32, 461, NULL, 'E3_ ELECTRICAL LIGHTING PLAN Rev.0 (1).pdf', 'uploads/1777485888_E3__ELECTRICAL_LIGHTING_PLAN_Rev.0_1.pdf', 'pdf', 2, '2026-04-29 14:04:48', NULL, 'vgroup_69f2484051997', 1),
(94, 32, 461, NULL, 'takeoff.Leesburg_legacy_plans_Social_Hall_Building_pdf_14_Page_14.pdf', 'uploads/1777485941_takeoff.Leesburg_legacy_plans_Social_Hall_Building_pdf_14_Page_14.pdf', 'pdf', 2, '2026-04-29 14:05:41', NULL, 'vgroup_69f24875c19c9', 1),
(95, 32, 461, NULL, 'takeoff.Leesburg_legacy_plans_Fitness_Building_pdf_13_Page_13.pdf', 'uploads/1777485983_takeoff.Leesburg_legacy_plans_Fitness_Building_pdf_13_Page_13.pdf', 'pdf', 2, '2026-04-29 14:06:23', NULL, 'vgroup_69f2489fd389c', 1),
(96, 32, 461, NULL, 'Leesburg legacy plans Fitness Building.pdf', 'uploads/1777486229_Leesburg_legacy_plans_Fitness_Building.pdf', 'pdf', 2, '2026-04-29 14:10:32', NULL, 'vgroup_69f24995a93d3', 1),
(97, 32, 461, NULL, 'Activity Building Plans.pdf', 'uploads/1777486396_Activity_Building_Plans.pdf', 'pdf', 2, '2026-04-29 14:13:18', NULL, 'vgroup_69f24a3c4e42c', 1),
(98, 32, 461, NULL, 'Fitness Building Plans.pdf', 'uploads/1777486536_Fitness_Building_Plans.pdf', 'pdf', 2, '2026-04-29 14:15:38', NULL, 'vgroup_69f24ac8beac1', 1),
(99, 32, 461, NULL, 'takeoff.Leesburg_legacy_plans_activity_Building_2_pdf_14_Page_14.pdf', 'uploads/1777486587_takeoff.Leesburg_legacy_plans_activity_Building_2_pdf_14_Page_14.pdf', 'pdf', 2, '2026-04-29 14:16:27', NULL, 'vgroup_69f24afbe415f', 1),
(100, 32, 461, NULL, 'Leesburg legacy plans Cabana Building.pdf', 'uploads/1777486763_Leesburg_legacy_plans_Cabana_Building.pdf', 'pdf', 2, '2026-04-29 14:19:25', NULL, 'vgroup_69f24babbe030', 1),
(101, 32, 461, NULL, 'Leesburg legacy plans Social Hall Building 2.pdf', 'uploads/1777486997_Leesburg_legacy_plans_Social_Hall_Building_2.pdf', 'pdf', 2, '2026-04-29 14:23:20', NULL, 'vgroup_69f24c95f2eb0', 1),
(102, 32, 461, NULL, 'Leesburg legacy plans Social Hall Building.pdf', 'uploads/1777487196_Leesburg_legacy_plans_Social_Hall_Building.pdf', 'pdf', 2, '2026-04-29 14:26:38', NULL, 'vgroup_69f24d5c4c678', 1),
(103, 32, 461, NULL, 'FK0hupb3q8cn8bS7YjIAM4Uy 2.pdf', 'uploads/1777487241_FK0hupb3q8cn8bS7YjIAM4Uy_2.pdf', 'pdf', 2, '2026-04-29 14:27:21', NULL, 'vgroup_69f24d89ad714', 1),
(104, 32, 461, NULL, '3 way switch connection social hall legacy of leesburg.pdf', 'uploads/1777487271_3_way_switch_connection_social_hall_legacy_of_leesburg.pdf', 'pdf', 2, '2026-04-29 14:27:51', NULL, 'vgroup_69f24da76a74b', 1),
(105, 32, 461, NULL, 'takeoff.Leesburg_legacy_plans_Social_Hall_Building_2_pdf_14_Page_14.pdf', 'uploads/1777487316_takeoff.Leesburg_legacy_plans_Social_Hall_Building_2_pdf_14_Page_14.pdf', 'pdf', 2, '2026-04-29 14:28:36', NULL, 'vgroup_69f24dd42ed9f', 1),
(106, 32, 461, NULL, 'takeoff.Leesburg_legacy_plans_Fitness_Building_pdf_14_Page_14.pdf', 'uploads/1777487351_takeoff.Leesburg_legacy_plans_Fitness_Building_pdf_14_Page_14.pdf', 'pdf', 2, '2026-04-29 14:29:11', NULL, 'vgroup_69f24df79ed52', 1),
(107, 32, 461, NULL, 'takeoff.Leesburg_legacy_plans_Cabana_Building_pdf_12_Page_12.pdf', 'uploads/1777487391_takeoff.Leesburg_legacy_plans_Cabana_Building_pdf_12_Page_12.pdf', 'pdf', 2, '2026-04-29 14:29:51', NULL, 'vgroup_69f24e1faca22', 1),
(108, 32, 461, NULL, 'takeoff.Leesburg_legacy_plans_Fitness_Building_pdf_5_Page_5.pdf', 'uploads/1777487481_takeoff.Leesburg_legacy_plans_Fitness_Building_pdf_5_Page_5.pdf', 'pdf', 2, '2026-04-29 14:31:21', NULL, 'vgroup_69f24e792ceb5', 1),
(109, 32, 463, NULL, 'LEGACY OF LEESBURG Shipping Report 07-15-2025.pdf', 'uploads/1777487513_LEGACY_OF_LEESBURG_Shipping_Report_07-15-2025.pdf', 'pdf', 2, '2026-04-29 14:31:53', NULL, 'vgroup_69f24e99b87df', 1),
(110, 32, 463, NULL, 'LEGACY OF LEESBURG Shipping Report 06-30-2025.pdf', 'uploads/1777487525_LEGACY_OF_LEESBURG_Shipping_Report_06-30-2025.pdf', 'pdf', 2, '2026-04-29 14:32:05', NULL, 'vgroup_69f24ea546c94', 1),
(111, 32, 464, NULL, 'Change Order 8 Activity Building Legacy of Leesburg.jpg', 'uploads/1777487551_Change_Order_8_Activity_Building_Legacy_of_Leesburg.jpg', '', 2, '2026-04-29 14:32:31', NULL, 'vgroup_69f24ebf0c0cf', 1),
(112, 32, 464, NULL, 'to fix legacy of leesburg.jpeg', 'uploads/1777487566_to_fix_legacy_of_leesburg.jpeg', '', 2, '2026-04-29 14:32:46', NULL, 'vgroup_69f24ece35a55', 1),
(113, 32, 464, NULL, 'boxes needed legacy of leesburg.jpg', 'uploads/1777487579_boxes_needed_legacy_of_leesburg.jpg', '', 2, '2026-04-29 14:32:59', NULL, 'vgroup_69f24edb00750', 1),
(114, 32, 465, NULL, 'Legacy Of Leesburg Change Order 1 -Activity Building- Circuits Relocation (Underground relocation).pdf', 'uploads/1777487609_Legacy_Of_Leesburg_Change_Order_1_-Activity_Building-_Circuits_Relocation_Underground_relocation.pdf', 'pdf', 2, '2026-04-29 14:33:29', NULL, 'vgroup_69f24ef9783fc', 1),
(115, 32, 466, NULL, 'Appliances Legacy of leesburg.pdf', 'uploads/1777487704_Appliances_Legacy_of_leesburg.pdf', 'pdf', 2, '2026-04-29 14:35:04', NULL, 'vgroup_69f24f582a000', 1),
(116, 32, 466, NULL, '8042-3TW-5CCT_Specsheet-2-1 Legacy alternative fixture for social building.pdf', 'uploads/1777487928_8042-3TW-5CCT_Specsheet-2-1_Legacy_alternative_fixture_for_social_building.pdf', 'pdf', 2, '2026-04-29 14:38:49', NULL, 'vgroup_69f25038b6646', 1),
(117, 32, 466, NULL, 'LEGACY_OF_LEESBURG Lighting Submittal.pdf', 'uploads/1777488422_LEGACY_OF_LEESBURG_Lighting_Submittal.pdf', 'pdf', 2, '2026-04-29 14:47:08', NULL, 'vgroup_69f25226f28cd', 1),
(118, 32, 466, NULL, '26 50 00-1 Lighting-GC, DBSS, PSA Review (1).pdf', 'uploads/1777488478_26_50_00-1_Lighting-GC_DBSS_PSA_Review_1.pdf', 'pdf', 2, '2026-04-29 14:48:04', NULL, 'vgroup_69f2525ee9f56', 1),
(119, 32, 466, NULL, 'LEGACY_OF_LEESBURG Lighting Submittal (1).pdf', 'uploads/1777488531_LEGACY_OF_LEESBURG_Lighting_Submittal_1.pdf', 'pdf', 2, '2026-04-29 14:48:57', NULL, 'vgroup_69f25293cd83d', 1),
(120, 32, 466, NULL, 'LEGACY_OF_LEESBURG Shane Mitchell.pdf', 'uploads/1777488586_LEGACY_OF_LEESBURG_Shane_Mitchell.pdf', 'pdf', 2, '2026-04-29 14:49:51', NULL, 'vgroup_69f252ca5bc45', 1),
(121, 32, 466, NULL, 'lbr6.pdf', 'uploads/1777488607_lbr6.pdf', 'pdf', 2, '2026-04-29 14:50:07', NULL, 'vgroup_69f252df1e2fd', 1),
(122, 32, 466, NULL, 'ss-F-1023-NPCWC.pdf', 'uploads/1777488629_ss-F-1023-NPCWC.pdf', 'pdf', 2, '2026-04-29 14:50:31', NULL, 'vgroup_69f252f5b45e7', 1),
(123, 32, 466, NULL, '26 50 00-1 Lighting-GC, DBSS, PSA Review.pdf', 'uploads/1777488701_26_50_00-1_Lighting-GC_DBSS_PSA_Review.pdf', 'pdf', 2, '2026-04-29 14:51:46', NULL, 'vgroup_69f2533d2f0d2', 1),
(124, 32, 466, NULL, '26 50 00-1 Lighting-GC Review.pdf', 'uploads/1777488758_26_50_00-1_Lighting-GC_Review.pdf', 'pdf', 2, '2026-04-29 14:52:44', NULL, 'vgroup_69f25376b4c61', 1),
(125, 32, 466, NULL, 'IS-Ratchet-ProSli-WM.pdf', 'uploads/1777488781_IS-Ratchet-ProSli-WM.pdf', 'pdf', 2, '2026-04-29 14:53:01', NULL, 'vgroup_69f2538d13e72', 1),
(126, 32, 466, NULL, '26 51 00-1 - Social Hall Wall Sconce_GC, PSA Review.pdf', 'uploads/1777488795_26_51_00-1_-_Social_Hall_Wall_Sconce_GC_PSA_Review.pdf', 'pdf', 2, '2026-04-29 14:53:15', NULL, 'vgroup_69f2539b2c71a', 1),
(127, 32, 466, NULL, 'ss-WC-7.pdf', 'uploads/1777488805_ss-WC-7.pdf', 'pdf', 2, '2026-04-29 14:53:25', NULL, 'vgroup_69f253a5e2b42', 1),
(128, 32, 466, NULL, 'Ceiling Fans signed.pdf', 'uploads/1777488825_Ceiling_Fans_signed.pdf', 'pdf', 2, '2026-04-29 14:53:47', NULL, 'vgroup_69f253b99b05f', 1),
(129, 32, 466, NULL, 'Ceiling Fan ss-F-1024.pdf', 'uploads/1777488838_Ceiling_Fan_ss-F-1024.pdf', 'pdf', 2, '2026-04-29 14:53:58', NULL, 'vgroup_69f253c6cb854', 1),
(130, 32, 466, NULL, 'ss-F-1024.pdf', 'uploads/1777488850_ss-F-1024.pdf', 'pdf', 2, '2026-04-29 14:54:10', NULL, 'vgroup_69f253d203f0f', 1),
(131, 32, 467, NULL, 'Legacy of Leesburg Change Orders 06-24-2024.pdf', 'uploads/1777488871_Legacy_of_Leesburg_Change_Orders_06-24-2024.pdf', 'pdf', 2, '2026-04-29 14:54:31', NULL, 'vgroup_69f253e7986b4', 1),
(132, 32, 467, NULL, 'Change Order Wall Sconces Social Hall Legacy of Leesburg.pdf', 'uploads/1777488882_Change_Order_Wall_Sconces_Social_Hall_Legacy_of_Leesburg.pdf', 'pdf', 2, '2026-04-29 14:54:42', NULL, 'vgroup_69f253f26f23d', 1),
(133, 32, 467, NULL, 'Change Orders Legacy of leesburg 06-19.pdf', 'uploads/1777488893_Change_Orders_Legacy_of_leesburg_06-19.pdf', 'pdf', 2, '2026-04-29 14:54:53', NULL, 'vgroup_69f253fd41c25', 1),
(134, 32, 467, NULL, 'Temporary and replacement 200A Breaker for MDP Panel Legacy of Leesburg 06-17-2025.pdf', 'uploads/1777488904_Temporary_and_replacement_200A_Breaker_for_MDP_Panel_Legacy_of_Leesburg_06-17-2025.pdf', 'pdf', 2, '2026-04-29 14:55:04', NULL, 'vgroup_69f2540820ac2', 1),
(135, 32, 467, NULL, 'Legacy of Leesburg General Bid 04-01  and plans markings.pdf', 'uploads/1777488922_Legacy_of_Leesburg_General_Bid_04-01__and_plans_markings.pdf', 'pdf', 2, '2026-04-29 14:55:23', NULL, 'vgroup_69f2541a660b7', 1),
(136, 32, 467, NULL, 'Legacy Of Leesburg Change Order 3  Social Hall 4 HVAC Conduits and Additional Fans.pdf', 'uploads/1777488934_Legacy_Of_Leesburg_Change_Order_3__Social_Hall_4_HVAC_Conduits_and_Additional_Fans.pdf', 'pdf', 2, '2026-04-29 14:55:34', NULL, 'vgroup_69f25426cf489', 1),
(137, 32, 467, NULL, 'Legacy Of Leesburg Change Order 2 Wall Sconce Dishwasher oven and 3 way switches.pdf', 'uploads/1777488945_Legacy_Of_Leesburg_Change_Order_2_Wall_Sconce_Dishwasher_oven_and_3_way_switches.pdf', 'pdf', 2, '2026-04-29 14:55:45', NULL, 'vgroup_69f25431bcfaa', 1),
(138, 32, 467, NULL, 'Legacy of Leesburg Change Orders Updates 06-24-2024 with breakdown (1).pdf', 'uploads/1777488958_Legacy_of_Leesburg_Change_Orders_Updates_06-24-2024_with_breakdown_1.pdf', 'pdf', 2, '2026-04-29 14:55:58', NULL, 'vgroup_69f2543e24a28', 1),
(139, 32, 467, NULL, 'Legacy of Leesburg Change Orders 06-23-2024.pdf', 'uploads/1777488969_Legacy_of_Leesburg_Change_Orders_06-23-2024.pdf', 'pdf', 2, '2026-04-29 14:56:09', NULL, 'vgroup_69f254499ad72', 1),
(140, 32, 469, NULL, 'Legacy of leesburg NOC Certified Copies.pdf', 'uploads/1777489003_Legacy_of_leesburg_NOC_Certified_Copies.pdf', 'pdf', 2, '2026-04-29 14:56:43', NULL, 'vgroup_69f2546b8306d', 1),
(141, 33, 470, NULL, 'BoM Lake Carter Bldg 3.xlsx', 'uploads/1777489591_BoM_Lake_Carter_Bldg_3.xlsx', '', 2, '2026-04-29 15:06:31', NULL, 'vgroup_69f256b7ebe40', 1),
(142, 33, 470, NULL, '0002_Conduit Runs_JW2(Sheet1) lake Carter.csv', 'uploads/1777489602_0002_Conduit_Runs_JW2Sheet1_lake_Carter.csv', '', 2, '2026-04-29 15:06:42', NULL, 'vgroup_69f256c27c115', 1),
(143, 33, 470, NULL, 'bom Lake Carter bldg 7 8 11 12.xlsx', 'uploads/1777489613_bom_Lake_Carter_bldg_7_8_11_12.xlsx', '', 2, '2026-04-29 15:06:53', NULL, 'vgroup_69f256cd1a21f', 1),
(144, 33, 470, NULL, 'BoM_Lake_Carter_Exchange_Bldgs_7_8_11_12-ApopkaFL.xlsx', 'uploads/1777489623_BoM_Lake_Carter_Exchange_Bldgs_7_8_11_12-ApopkaFL.xlsx', '', 2, '2026-04-29 15:07:03', NULL, 'vgroup_69f256d79b45a', 1),
(145, 33, 472, NULL, 'Commitment - Brightronix LLC - Electrical.pdf', 'uploads/1777489655_Commitment_-_Brightronix_LLC_-_Electrical.pdf', 'pdf', 2, '2026-04-29 15:07:35', NULL, 'vgroup_69f256f70be49', 1),
(146, 33, 472, NULL, 'Electric SOW.pdf', 'uploads/1777489665_Electric_SOW.pdf', 'pdf', 2, '2026-04-29 15:07:45', NULL, 'vgroup_69f25701bf8c3', 1),
(147, 33, 472, NULL, 'Electric SOW Lake Carter.pdf', 'uploads/1777489676_Electric_SOW_Lake_Carter.pdf', 'pdf', 2, '2026-04-29 15:07:56', NULL, 'vgroup_69f2570c7f93e', 1),
(148, 33, 472, NULL, '240211 - LC-B11-ELEC Permit_12-17-24.pdf', 'uploads/1777489690_240211_-_LC-B11-ELEC_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:08:10', NULL, 'vgroup_69f2571a1993c', 1),
(149, 33, 474, NULL, 'V30G0108X5K8_0001_Customer_Bill_Of_Material_8_LAKE_CARTER_EX_1.DOCX', 'uploads/1777489721_V30G0108X5K8_0001_Customer_Bill_Of_Material_8_LAKE_CARTER_EX_1.DOCX', '', 2, '2026-04-29 15:08:41', NULL, 'vgroup_69f2573949dbc', 1),
(150, 33, 475, NULL, 'Quote 2369781 - BRIGHTRONIX LLC - Lake Carter Bldg 3.pdf', 'uploads/1777489742_Quote_2369781_-_BRIGHTRONIX_LLC_-_Lake_Carter_Bldg_3.pdf', 'pdf', 2, '2026-04-29 15:09:02', NULL, 'vgroup_69f2574e51cb3', 1),
(151, 33, 476, NULL, 'Electrical Bid Lake Carter Building 5 Brightronix.pdf', 'uploads/1777489764_Electrical_Bid_Lake_Carter_Building_5_Brightronix.pdf', 'pdf', 2, '2026-04-29 15:09:24', NULL, 'vgroup_69f25764eab85', 1),
(152, 33, 476, NULL, 'Electrical Bid Lake Carter Building 4 Brightronix.pdf', 'uploads/1777489775_Electrical_Bid_Lake_Carter_Building_4_Brightronix.pdf', 'pdf', 2, '2026-04-29 15:09:35', NULL, 'vgroup_69f2576fb9aea', 1),
(153, 33, 476, NULL, 'Electrical Bid Brightronix Lake Carter Building 1.pdf', 'uploads/1777489786_Electrical_Bid_Brightronix_Lake_Carter_Building_1.pdf', 'pdf', 2, '2026-04-29 15:09:46', NULL, 'vgroup_69f2577ac5458', 1),
(154, 33, 476, NULL, 'Electrical Bid Lake Carter Building 8.pdf', 'uploads/1777489797_Electrical_Bid_Lake_Carter_Building_8.pdf', 'pdf', 2, '2026-04-29 15:09:57', NULL, 'vgroup_69f2578593251', 1),
(155, 33, 476, NULL, 'bid.562949954299958.1616342.docx', 'uploads/1777489808_bid.562949954299958.1616342.docx', '', 2, '2026-04-29 15:10:08', NULL, 'vgroup_69f257903d747', 1),
(156, 33, 476, NULL, 'Electrical Bid Lake Carter Building 3.pdf', 'uploads/1777489820_Electrical_Bid_Lake_Carter_Building_3.pdf', 'pdf', 2, '2026-04-29 15:10:20', NULL, 'vgroup_69f2579c03ee0', 1),
(157, 33, 476, NULL, 'bid.562949954299958.1616342.pdf', 'uploads/1777489831_bid.562949954299958.1616342.pdf', 'pdf', 2, '2026-04-29 15:10:31', NULL, 'vgroup_69f257a727038', 1),
(158, 33, 476, NULL, 'Lake Carter Alberto.pdf', 'uploads/1777489842_Lake_Carter_Alberto.pdf', 'pdf', 2, '2026-04-29 15:10:42', NULL, 'vgroup_69f257b2d521e', 1),
(159, 33, 476, NULL, 'Advent health ocala change order new receptacles and data.pdf', 'uploads/1777489853_Advent_health_ocala_change_order_new_receptacles_and_data.pdf', 'pdf', 2, '2026-04-29 15:10:53', NULL, 'vgroup_69f257bda6ab4', 1),
(160, 33, 476, NULL, 'Electrical Bid Lake Carter Building 12.pdf', 'uploads/1777489864_Electrical_Bid_Lake_Carter_Building_12.pdf', 'pdf', 2, '2026-04-29 15:11:04', NULL, 'vgroup_69f257c8738cd', 1),
(161, 33, 476, NULL, 'Electrical Bid Lake Carter Building 7.pdf', 'uploads/1777489875_Electrical_Bid_Lake_Carter_Building_7.pdf', 'pdf', 2, '2026-04-29 15:11:15', NULL, 'vgroup_69f257d34d0ed', 1),
(162, 33, 476, NULL, 'Lake Carter Schmid.pdf', 'uploads/1777489886_Lake_Carter_Schmid.pdf', 'pdf', 2, '2026-04-29 15:11:26', NULL, 'vgroup_69f257de86a55', 1),
(163, 33, 476, NULL, 'Electrical Bid Lake Carter Building 2 Brightronix.pdf', 'uploads/1777489897_Electrical_Bid_Lake_Carter_Building_2_Brightronix.pdf', 'pdf', 2, '2026-04-29 15:11:37', NULL, 'vgroup_69f257e9b4583', 1),
(164, 33, 476, NULL, 'Lake Carter Primary Site Lighting and Monument Signs Lift Station.pdf', 'uploads/1777489908_Lake_Carter_Primary_Site_Lighting_and_Monument_Signs_Lift_Station.pdf', 'pdf', 2, '2026-04-29 15:11:48', NULL, 'vgroup_69f257f4825ac', 1),
(165, 33, 476, NULL, 'Electrical Bid Lake Carter Building 11.pdf', 'uploads/1777489919_Electrical_Bid_Lake_Carter_Building_11.pdf', 'pdf', 2, '2026-04-29 15:11:59', NULL, 'vgroup_69f257ff764bf', 1),
(166, 33, 478, NULL, 'Confirmation Lake Carter Buildings - Estimating@brightronixllc.com - Brightronix LLC Mail.pdf', 'uploads/1777489952_Confirmation_Lake_Carter_Buildings_-_Estimatingbrightronixllc.com_-_Brightronix_LLC_Mail.pdf', 'pdf', 2, '2026-04-29 15:12:32', NULL, 'vgroup_69f25820d08fd', 1),
(167, 33, 480, NULL, 'email city of apopka lake carter buildings schmid.pdf', 'uploads/1777489984_email_city_of_apopka_lake_carter_buildings_schmid.pdf', 'pdf', 2, '2026-04-29 15:13:04', NULL, 'vgroup_69f258404a80c', 1),
(168, 33, 480, NULL, 'Brightronix Lake Carter Registration Confirmation Apopka.pdf', 'uploads/1777489995_Brightronix_Lake_Carter_Registration_Confirmation_Apopka.pdf', 'pdf', 2, '2026-04-29 15:13:15', NULL, 'vgroup_69f2584b41326', 1),
(169, 33, 481, NULL, '25-004 LCE BLDG#2 - Subcontractor Warranty - Brightronix.pdf', 'uploads/1777490017_25-004_LCE_BLDG2_-_Subcontractor_Warranty_-_Brightronix.pdf', 'pdf', 2, '2026-04-29 15:13:37', NULL, 'vgroup_69f25861dbdab', 1),
(170, 33, 481, NULL, 'Scan_20260317.pdf', 'uploads/1777490033_Scan_20260317.pdf', 'pdf', 2, '2026-04-29 15:13:53', NULL, 'vgroup_69f258717299e', 1),
(171, 33, 482, NULL, 'abl-led-oled-commercial-fixture-limited-warranty.pdf', 'uploads/1777490057_abl-led-oled-commercial-fixture-limited-warranty.pdf', 'pdf', 2, '2026-04-29 15:14:17', NULL, 'vgroup_69f2588940441', 1),
(172, 33, 482, NULL, 'Warranty.pdf', 'uploads/1777490071_Warranty.pdf', 'pdf', 2, '2026-04-29 15:14:31', NULL, 'vgroup_69f258972caf5', 1),
(173, 33, 482, NULL, 'abl-emergency-commercial-product-battery-warranty-and-power-supply.pdf', 'uploads/1777490083_abl-emergency-commercial-product-battery-warranty-and-power-supply.pdf', 'pdf', 2, '2026-04-29 15:14:43', NULL, 'vgroup_69f258a32a03e', 1),
(174, 33, 483, NULL, 'LAKE CARTER LIGHTING - BLDGS 2,3,5,6,9,10,11,12,& 13 - LTG.pdf', 'uploads/1777490115_LAKE_CARTER_LIGHTING_-_BLDGS_23569101112_13_-_LTG.pdf', 'pdf', 2, '2026-04-29 15:15:15', NULL, 'vgroup_69f258c35af8e', 1),
(175, 33, 484, NULL, '2WhatsApp Image 2026-04-03 at 9.12.34 AM.jpeg', 'uploads/1777490136_2WhatsApp_Image_2026-04-03_at_9.12.34_AM.jpeg', '', 2, '2026-04-29 15:15:36', NULL, 'vgroup_69f258d88eb02', 1),
(176, 33, 484, NULL, '1WhatsApp Image 2026-04-03 at 9.12.34 AM.jpeg', 'uploads/1777490147_1WhatsApp_Image_2026-04-03_at_9.12.34_AM.jpeg', '', 2, '2026-04-29 15:15:47', NULL, 'vgroup_69f258e350cd8', 1),
(177, 33, 484, NULL, 'WhatsApp Image 2026-04-03 at 9.12.34 AM.jpeg', 'uploads/1777490158_WhatsApp_Image_2026-04-03_at_9.12.34_AM.jpeg', '', 2, '2026-04-29 15:15:58', NULL, 'vgroup_69f258ee3a98d', 1),
(178, 33, 485, NULL, 'J07L0324X5K5-0000_LAKE_CARTER_BUILDING_9_SUB_20250416-010802_1.PDF', 'uploads/1777490184_J07L0324X5K5-0000_LAKE_CARTER_BUILDING_9_SUB_20250416-010802_1.PDF', 'pdf', 2, '2026-04-29 15:16:25', NULL, 'vgroup_69f25908a868d', 1),
(179, 33, 485, NULL, 'J07L0324X5K7-0000_LAKE_CARTER_BUILDING_13_SUB_20250416-01070_1.PDF', 'uploads/1777490201_J07L0324X5K7-0000_LAKE_CARTER_BUILDING_13_SUB_20250416-01070_1.PDF', 'pdf', 2, '2026-04-29 15:16:41', NULL, 'vgroup_69f259191fec2', 1),
(180, 33, 485, NULL, 'Letter confirmation buildings lake carter from schmid.pdf', 'uploads/1777490212_Letter_confirmation_buildings_lake_carter_from_schmid.pdf', 'pdf', 2, '2026-04-29 15:16:52', NULL, 'vgroup_69f25924741e5', 1),
(181, 33, 485, NULL, 'Lake Carter Lighting Submittals - CEG Review_Aproved as Noted.pdf', 'uploads/1777490256_Lake_Carter_Lighting_Submittals_-_CEG_Review_Aproved_as_Noted.pdf', 'pdf', 2, '2026-04-29 15:17:41', NULL, 'vgroup_69f25950476e9', 1),
(182, 33, 485, NULL, 'Lake Carter Lighting Submittals - CEG Review_Aproved as Noted (1).pdf', 'uploads/1777490305_Lake_Carter_Lighting_Submittals_-_CEG_Review_Aproved_as_Noted_1.pdf', 'pdf', 2, '2026-04-29 15:18:30', NULL, 'vgroup_69f259814b11b', 1),
(183, 33, 485, NULL, 'J07L0324X5K6-0000_LAKE_CARTER_BUILDING_10_SUB_20250416-01073_1.PDF', 'uploads/1777490325_J07L0324X5K6-0000_LAKE_CARTER_BUILDING_10_SUB_20250416-01073_1.PDF', 'pdf', 2, '2026-04-29 15:18:46', NULL, 'vgroup_69f25995ef4c4', 1),
(184, 33, 485, NULL, 'V30G0108X5K4-0003_LAKE_CARTER_EXCHANGE_BLDG_11_SUB_20250416-_1.PDF', 'uploads/1777490343_V30G0108X5K4-0003_LAKE_CARTER_EXCHANGE_BLDG_11_SUB_20250416-_1.PDF', 'pdf', 2, '2026-04-29 15:19:03', NULL, 'vgroup_69f259a703c83', 1),
(185, 33, 485, NULL, 'Lake Carter Lighting Submittals - CEG Review.pdf', 'uploads/1777490367_Lake_Carter_Lighting_Submittals_-_CEG_Review.pdf', 'pdf', 2, '2026-04-29 15:19:29', NULL, 'vgroup_69f259bfc17cd', 1),
(186, 33, 485, NULL, 'V30G0108X5K5-0003_LAKE_CARTER_EXCHANGE_BLDG_12_SUB_20250416-_1.PDF', 'uploads/1777490386_V30G0108X5K5-0003_LAKE_CARTER_EXCHANGE_BLDG_12_SUB_20250416-_1.PDF', 'pdf', 2, '2026-04-29 15:19:47', NULL, 'vgroup_69f259d2822dd', 1),
(187, 33, 485, NULL, 'J07L0214X5K3-0000_LAKE_CARTER_BUILDING_5_SUB_20250416-010905_1.PDF', 'uploads/1777490402_J07L0214X5K3-0000_LAKE_CARTER_BUILDING_5_SUB_20250416-010905_1.PDF', 'pdf', 2, '2026-04-29 15:20:03', NULL, 'vgroup_69f259e28b2ab', 1),
(188, 33, 485, NULL, 'V30G0108X5K8-0001_LAKE_CARTER_EXCHANGE_BLDG_3_SUB_20250416-0_1.PDF', 'uploads/1777490418_V30G0108X5K8-0001_LAKE_CARTER_EXCHANGE_BLDG_3_SUB_20250416-0_1.PDF', 'pdf', 2, '2026-04-29 15:20:19', NULL, 'vgroup_69f259f2a59de', 1),
(189, 33, 485, NULL, 'Lake Carter Lighting Submittals.pdf', 'uploads/1777490441_Lake_Carter_Lighting_Submittals.pdf', 'pdf', 2, '2026-04-29 15:20:43', NULL, 'vgroup_69f25a09a4b02', 1),
(190, 33, 485, NULL, 'J07L0324X5K4-0000_LAKE_CARTER_BUILDING_6_SUB_20250416-010836_1.PDF', 'uploads/1777490460_J07L0324X5K4-0000_LAKE_CARTER_BUILDING_6_SUB_20250416-010836_1.PDF', 'pdf', 2, '2026-04-29 15:21:00', NULL, 'vgroup_69f25a1c07cf3', 1),
(191, 33, 485, NULL, 'Lake-Carter-Lighting-Submittals---CEG-Review.pdf', 'uploads/1777490484_Lake-Carter-Lighting-Submittals---CEG-Review.pdf', 'pdf', 2, '2026-04-29 15:21:26', NULL, 'vgroup_69f25a34c9583', 1),
(192, 33, 487, NULL, 'Lake Carter Bldg 2.zip', 'uploads/1777490570_Lake_Carter_Bldg_2.zip', '', 2, '2026-04-29 15:22:56', NULL, 'vgroup_69f25a8abb462', 1),
(193, 33, 487, NULL, '1700 W KEENE RD PRINT UPDATED.pdf', 'uploads/1777490587_1700_W_KEENE_RD_PRINT_UPDATED.pdf', 'pdf', 2, '2026-04-29 15:23:07', NULL, 'vgroup_69f25a9b5e24c', 1),
(194, 33, 487, NULL, 'lake carter bldg 3 riser.pdf', 'uploads/1777490599_lake_carter_bldg_3_riser.pdf', 'pdf', 2, '2026-04-29 15:23:19', NULL, 'vgroup_69f25aa7b730a', 1),
(195, 33, 487, NULL, 'REV Final_Lake Carter Exchange - FULL SET_1-28-25.pdf', 'uploads/1777490614_REV_Final_Lake_Carter_Exchange_-_FULL_SET_1-28-25.pdf', 'pdf', 2, '2026-04-29 15:23:35', NULL, 'vgroup_69f25ab6c1131', 1),
(196, 33, 487, NULL, 'Lake carter apopka bldg 3.zip', 'uploads/1777490683_Lake_carter_apopka_bldg_3.zip', '', 2, '2026-04-29 15:24:48', NULL, 'vgroup_69f25afb62b6b', 1),
(197, 33, 487, NULL, '0002_LCE_JW2_Electrical Site Plan_Rev00_240217.pdf', 'uploads/1777490699_0002_LCE_JW2_Electrical_Site_Plan_Rev00_240217.pdf', 'pdf', 2, '2026-04-29 15:24:59', NULL, 'vgroup_69f25b0b6189e', 1),
(198, 33, 487, NULL, '0002_Civil Sheets.pdf', 'uploads/1777490716_0002_Civil_Sheets.pdf', 'pdf', 2, '2026-04-29 15:25:17', NULL, 'vgroup_69f25b1c93bcc', 1),
(199, 33, 488, NULL, '240211 - LC-B7-MECH Permit_12-11-24 SEALED.pdf', 'uploads/1777490739_240211_-_LC-B7-MECH_Permit_12-11-24_SEALED.pdf', 'pdf', 2, '2026-04-29 15:25:39', NULL, 'vgroup_69f25b332d4aa', 1),
(200, 33, 488, NULL, '240211 - LC-B7-ELEC Permit_12-11-24 SEALED.pdf', 'uploads/1777490751_240211_-_LC-B7-ELEC_Permit_12-11-24_SEALED.pdf', 'pdf', 2, '2026-04-29 15:25:51', NULL, 'vgroup_69f25b3ff2028', 1),
(201, 33, 488, NULL, 'ARCH 2024-12-17_PSA_LCE_Building-7_S S.pdf', 'uploads/1777490790_ARCH_2024-12-17_PSA_LCE_Building-7_S_S.pdf', 'pdf', 2, '2026-04-29 15:26:34', NULL, 'vgroup_69f25b66de24e', 1),
(202, 33, 488, NULL, '240211 - LC-B7-PLBG PERMIT_12-11-24 SEALED.pdf', 'uploads/1777490807_240211_-_LC-B7-PLBG_PERMIT_12-11-24_SEALED.pdf', 'pdf', 2, '2026-04-29 15:26:47', NULL, 'vgroup_69f25b778802d', 1),
(203, 33, 488, NULL, 'Structural Lake Carter Building 7-12-11-2024.pdf', 'uploads/1777490820_Structural_Lake_Carter_Building_7-12-11-2024.pdf', 'pdf', 2, '2026-04-29 15:27:00', NULL, 'vgroup_69f25b84c6555', 1),
(204, 33, 488, NULL, 'Lake Carter Bldg 4 5.zip', 'uploads/1777490950_Lake_Carter_Bldg_4_5.zip', '', 2, '2026-04-29 15:29:15', NULL, 'vgroup_69f25c06ce651', 1),
(205, 33, 489, NULL, '240211 - LC-B8-PLBG Permit_12-17-24.pdf', 'uploads/1777490981_240211_-_LC-B8-PLBG_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:29:41', NULL, 'vgroup_69f25c25306bf', 1),
(206, 33, 489, NULL, '0002_Civil Sheets Lake Carter Site.pdf', 'uploads/1777490998_0002_Civil_Sheets_Lake_Carter_Site.pdf', 'pdf', 2, '2026-04-29 15:29:59', NULL, 'vgroup_69f25c367f668', 1),
(207, 33, 489, NULL, '240211 - LC-B8-ELEC Permit_12-17-24.pdf', 'uploads/1777491014_240211_-_LC-B8-ELEC_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:30:14', NULL, 'vgroup_69f25c4655f11', 1),
(208, 33, 489, NULL, '240211 - LC-B8-MECH Permit_12-17-24.pdf', 'uploads/1777491025_240211_-_LC-B8-MECH_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:30:25', NULL, 'vgroup_69f25c514d47d', 1),
(209, 33, 489, NULL, 'Lake carter apopka bldg 8 11 12.zip', 'uploads/1777491065_Lake_carter_apopka_bldg_8_11_12.zip', '', 2, '2026-04-29 15:31:10', NULL, 'vgroup_69f25c79c9c94', 1),
(210, 33, 489, NULL, 'Arch_2024-12-17_PSA_LCE_Building-8.pdf', 'uploads/1777491106_Arch_2024-12-17_PSA_LCE_Building-8.pdf', 'pdf', 2, '2026-04-29 15:31:50', NULL, 'vgroup_69f25ca2ca0a7', 1),
(211, 33, 489, NULL, 'Structural_Lake Carter Building 8-12-18-2024.pdf', 'uploads/1777491123_Structural_Lake_Carter_Building_8-12-18-2024.pdf', 'pdf', 2, '2026-04-29 15:32:03', NULL, 'vgroup_69f25cb3cd4c6', 1),
(212, 33, 496, NULL, '240211 - LC-B11-ELEC Permit_12-17-24.pdf', 'uploads/1777491147_240211_-_LC-B11-ELEC_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:32:27', NULL, 'vgroup_69f25ccbafa6f', 1),
(213, 33, 496, NULL, 'Structural_Lake Carter Building 11-12-18-2024.pdf', 'uploads/1777491160_Structural_Lake_Carter_Building_11-12-18-2024.pdf', 'pdf', 2, '2026-04-29 15:32:40', NULL, 'vgroup_69f25cd8d6356', 1),
(214, 33, 496, NULL, 'Lake Carter BLdg 7 8 11 12.zip', 'uploads/1777491289_Lake_Carter_BLdg_7_8_11_12.zip', '', 2, '2026-04-29 15:34:54', NULL, 'vgroup_69f25d5972a97', 1),
(215, 33, 496, NULL, 'Advent health ocala change order new receptacles and data.pdf', 'uploads/1777491307_Advent_health_ocala_change_order_new_receptacles_and_data.pdf', 'pdf', 2, '2026-04-29 15:35:07', NULL, 'vgroup_69f25d6bcfebe', 1),
(216, 33, 496, NULL, '240211 - LC-B11-PLBG Permit_12-17-24.pdf', 'uploads/1777491320_240211_-_LC-B11-PLBG_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:35:20', NULL, 'vgroup_69f25d784fe14', 1),
(217, 33, 496, NULL, 'ARCH_2024-12-17_PSA_LCE_Building-11.pdf', 'uploads/1777491356_ARCH_2024-12-17_PSA_LCE_Building-11.pdf', 'pdf', 2, '2026-04-29 15:36:00', NULL, 'vgroup_69f25d9c61ba2', 1),
(218, 33, 496, NULL, '240211 - LC-B11-MECH Permit_12-17-24.pdf', 'uploads/1777491371_240211_-_LC-B11-MECH_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:36:11', NULL, 'vgroup_69f25dab1dfb3', 1),
(219, 33, 498, NULL, '240211 - LC-B11-MECH Permit_12-17-24.pdf', 'uploads/1777491402_240211_-_LC-B11-MECH_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:36:42', NULL, 'vgroup_69f25dcacbab7', 1),
(220, 33, 498, NULL, '240211 - LC-B11-ELEC Permit_12-17-24.pdf', 'uploads/1777491416_240211_-_LC-B11-ELEC_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:36:56', NULL, 'vgroup_69f25dd86c525', 1),
(221, 33, 498, NULL, 'Structural_Lake Carter Building 11-12-18-2024.pdf', 'uploads/1777491429_Structural_Lake_Carter_Building_11-12-18-2024.pdf', 'pdf', 2, '2026-04-29 15:37:09', NULL, 'vgroup_69f25de55c87b', 1),
(222, 33, 498, NULL, 'ARCH_2024-12-17_PSA_LCE_Building-11.pdf', 'uploads/1777491467_ARCH_2024-12-17_PSA_LCE_Building-11.pdf', 'pdf', 2, '2026-04-29 15:37:51', NULL, 'vgroup_69f25e0b4321b', 1),
(223, 33, 498, NULL, '240211 - LC-B11-PLBG Permit_12-17-24.pdf', 'uploads/1777491483_240211_-_LC-B11-PLBG_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:38:03', NULL, 'vgroup_69f25e1ba399c', 1),
(224, 33, 499, NULL, 'lake carter bldg 5 riser.pdf', 'uploads/1777491506_lake_carter_bldg_5_riser.pdf', 'pdf', 2, '2026-04-29 15:38:26', NULL, 'vgroup_69f25e32661a3', 1),
(225, 33, 505, NULL, '240211 - LC-B2-ELEC Permit_01-14-25 - SEALED.pdf', 'uploads/1777491583_240211_-_LC-B2-ELEC_Permit_01-14-25_-_SEALED.pdf', 'pdf', 2, '2026-04-29 15:39:43', NULL, 'vgroup_69f25e7f17d1d', 1),
(226, 33, 505, NULL, '2025-02-04_PSA_LCE_Building-2.pdf', 'uploads/1777491645_2025-02-04_PSA_LCE_Building-2.pdf', 'pdf', 2, '2026-04-29 15:40:50', NULL, 'vgroup_69f25ebdaa82a', 1),
(227, 33, 505, NULL, 'Lake Carter B2 structural 2-1-14-2025_SEALED.pdf', 'uploads/1777491664_Lake_Carter_B2_structural_2-1-14-2025_SEALED.pdf', 'pdf', 2, '2026-04-29 15:41:04', NULL, 'vgroup_69f25ed0d6fc5', 1),
(228, 33, 505, NULL, '240211 - LC-B2-PLBG Permit_01-14-25 SEALED.pdf', 'uploads/1777491678_240211_-_LC-B2-PLBG_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:41:18', NULL, 'vgroup_69f25ede2de5d', 1),
(229, 33, 505, NULL, '240211 - LC-B2-MECH Permit_01-14-25 SEALED.pdf', 'uploads/1777491689_240211_-_LC-B2-MECH_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:41:29', NULL, 'vgroup_69f25ee9eb69c', 1),
(230, 33, 507, NULL, 'Lake Carter B5 Structural-01-14-2025_SEALED.pdf', 'uploads/1777491724_Lake_Carter_B5_Structural-01-14-2025_SEALED.pdf', 'pdf', 2, '2026-04-29 15:42:04', NULL, 'vgroup_69f25f0cac142', 1),
(231, 33, 507, NULL, '2025-02-04_PSArch_LCE_Building-5.pdf', 'uploads/1777491788_2025-02-04_PSArch_LCE_Building-5.pdf', 'pdf', 2, '2026-04-29 15:43:14', NULL, 'vgroup_69f25f4ce7ee6', 1),
(232, 33, 507, NULL, '240211 - LC-B5-MECH Permit_01-14-25 SEALED.pdf', 'uploads/1777491805_240211_-_LC-B5-MECH_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:43:25', NULL, 'vgroup_69f25f5dbe1d1', 1),
(233, 33, 507, NULL, '240211 - LC-B5-ELEC Permit_01-14-25 SEALED.pdf', 'uploads/1777491820_240211_-_LC-B5-ELEC_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:43:41', NULL, 'vgroup_69f25f6cc5b6f', 1),
(234, 33, 507, NULL, '240211 - LC-B5-PLBG Permit_01-14-25 SEALED.pdf', 'uploads/1777491834_240211_-_LC-B5-PLBG_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:43:54', NULL, 'vgroup_69f25f7acb2ac', 1),
(235, 33, 508, NULL, '240211 - LC-B4-ELEC Permit_01-14-25 SEALED.pdf', 'uploads/1777491859_240211_-_LC-B4-ELEC_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:44:19', NULL, 'vgroup_69f25f93ab860', 1),
(236, 33, 508, NULL, '240211 - LC-B4-MECH Permit_01-14-25 SEALED.pdf', 'uploads/1777491871_240211_-_LC-B4-MECH_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:44:31', NULL, 'vgroup_69f25f9f659c7', 1),
(237, 33, 508, NULL, '240211 - LC-B4-PLBG Permit_01-14-25 SEALED.pdf', 'uploads/1777491884_240211_-_LC-B4-PLBG_Permit_01-14-25_SEALED.pdf', 'pdf', 2, '2026-04-29 15:44:44', NULL, 'vgroup_69f25faccc3ae', 1),
(238, 33, 508, NULL, '2025-02-04_PSArch_LCE_Building-4.pdf', 'uploads/1777491948_2025-02-04_PSArch_LCE_Building-4.pdf', 'pdf', 2, '2026-04-29 15:45:54', NULL, 'vgroup_69f25fecd7649', 1),
(239, 33, 508, NULL, 'Lake Carter B4 Structural 4-01-14-2025_SEALED.pdf', 'uploads/1777491969_Lake_Carter_B4_Structural_4-01-14-2025_SEALED.pdf', 'pdf', 2, '2026-04-29 15:46:09', NULL, 'vgroup_69f26001375e0', 1),
(240, 33, 511, NULL, 'takeoff.0002_Civil_Sheets_pdf_2_Page_2 Lake Carter.pdf', 'uploads/1777492005_takeoff.0002_Civil_Sheets_pdf_2_Page_2_Lake_Carter.pdf', 'pdf', 2, '2026-04-29 15:46:46', NULL, 'vgroup_69f26025d24f8', 1),
(241, 33, 512, NULL, '2024-12-11_ARCH -PSA_LCE_Building-3 S S.pdf', 'uploads/1777492083_2024-12-11_ARCH_-PSA_LCE_Building-3_S_S.pdf', 'pdf', 2, '2026-04-29 15:48:08', NULL, 'vgroup_69f26073390e2', 1),
(242, 33, 512, NULL, 'Lake Carter Structural 3-12-10-2024 S S.pdf', 'uploads/1777492103_Lake_Carter_Structural_3-12-10-2024_S_S.pdf', 'pdf', 2, '2026-04-29 15:48:23', NULL, 'vgroup_69f260871be9c', 1),
(243, 33, 512, NULL, '240211 - LC-B3-MECH PERMIT_12-11-24 SEALED.pdf', 'uploads/1777492114_240211_-_LC-B3-MECH_PERMIT_12-11-24_SEALED.pdf', 'pdf', 2, '2026-04-29 15:48:34', NULL, 'vgroup_69f26092c9a14', 1),
(244, 33, 512, NULL, '240211 - LC-B3-PLBG PERMIT_12-12-24 SEALED.pdf', 'uploads/1777492128_240211_-_LC-B3-PLBG_PERMIT_12-12-24_SEALED.pdf', 'pdf', 2, '2026-04-29 15:48:48', NULL, 'vgroup_69f260a05484f', 1),
(245, 33, 512, NULL, '240211 - LC-B3-ELEC PERMIT_12-11-24 SEALED.pdf', 'uploads/1777492140_240211_-_LC-B3-ELEC_PERMIT_12-11-24_SEALED.pdf', 'pdf', 2, '2026-04-29 15:49:00', NULL, 'vgroup_69f260acdfae2', 1),
(246, 33, 513, NULL, '240211 - LC-B12-ELEC Permit_12-17-24.pdf', 'uploads/1777492164_240211_-_LC-B12-ELEC_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:49:24', NULL, 'vgroup_69f260c4c1f8d', 1),
(247, 33, 513, NULL, 'Structural_Lake Carter Building 12-12-18-2024.pdf', 'uploads/1777492177_Structural_Lake_Carter_Building_12-12-18-2024.pdf', 'pdf', 2, '2026-04-29 15:49:37', NULL, 'vgroup_69f260d1b5fe5', 1),
(248, 33, 513, NULL, '1_0_Electrical-Fixture-Submittal-(Bldg-2,-3,-5,-6,-9,-10,-11,-12-&-13)_response_attachments lake carter.zip', 'uploads/1777492223_1_0_Electrical-Fixture-Submittal-Bldg-2-3-5-6-9-10-11-12--13_response_attachments_lake_carter.zip', '', 2, '2026-04-29 15:50:28', NULL, 'vgroup_69f260ff7d29f', 1),
(249, 33, 513, NULL, 'ARCH_2024-12-17_PSA_LCE_Building-12.pdf', 'uploads/1777492265_ARCH_2024-12-17_PSA_LCE_Building-12.pdf', 'pdf', 2, '2026-04-29 15:51:09', NULL, 'vgroup_69f26129a16bb', 1),
(250, 33, 513, NULL, '240211 - LC-B12-MECH Permit_12-17-24.pdf', 'uploads/1777492280_240211_-_LC-B12-MECH_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:51:20', NULL, 'vgroup_69f261388e9b5', 1),
(251, 33, 513, NULL, '240211 - LC-B12-PLBG Permit_12-17-24.pdf', 'uploads/1777492292_240211_-_LC-B12-PLBG_Permit_12-17-24.pdf', 'pdf', 2, '2026-04-29 15:51:32', NULL, 'vgroup_69f26144a53ac', 1),
(252, 33, 513, NULL, '240211_-_LC-B1-ELEC_Permit_12-06-24.pdf', 'uploads/1777492305_240211_-_LC-B1-ELEC_Permit_12-06-24.pdf', 'pdf', 2, '2026-04-29 15:51:45', NULL, 'vgroup_69f261511d36d', 1),
(253, 35, 514, NULL, 'Pear Park Leesburg.pdf', 'uploads/1777492466_Pear_Park_Leesburg.pdf', 'pdf', 2, '2026-04-29 15:54:32', NULL, 'vgroup_69f261f2e910f', 1),
(254, 35, 514, NULL, 'Leesburg Selfstorage Buiding A (6) 3.17.25.pdf', 'uploads/1777492594_Leesburg_Selfstorage_Buiding_A_6_3.17.25.pdf', 'pdf', 2, '2026-04-29 15:56:39', NULL, 'vgroup_69f26272015f4', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `file_reports`
--

CREATE TABLE `file_reports` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `technician_name` varchar(100) DEFAULT NULL,
  `technician_role` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `annotations_json` longtext DEFAULT NULL,
  `attachments_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments_json`)),
  `report_pdf_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `depth` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `folders`
--

INSERT INTO `folders` (`id`, `project_id`, `name`, `created_at`, `deleted_at`, `parent_id`, `depth`) VALUES
(453, 32, 'Rfi', '2026-04-29 13:58:02', NULL, NULL, 0),
(454, 32, 'Quote From Provider', '2026-04-29 13:58:50', NULL, NULL, 0),
(455, 32, 'BOM', '2026-04-29 13:59:17', NULL, NULL, 0),
(456, 32, 'Invoices', '2026-04-29 13:59:27', NULL, NULL, 0),
(457, 32, 'Contract', '2026-04-29 13:59:38', NULL, NULL, 0),
(458, 32, 'Waivers', '2026-04-29 13:59:48', NULL, NULL, 0),
(459, 32, 'Change Order', '2026-04-29 14:00:18', NULL, NULL, 0),
(460, 32, 'Costs Brightronix', '2026-04-29 14:01:18', NULL, NULL, 0),
(461, 32, 'Plans', '2026-04-29 14:01:39', NULL, NULL, 0),
(462, 32, 'Flp', '2026-04-29 14:31:32', NULL, NULL, 0),
(463, 32, 'Shipping Report', '2026-04-29 14:31:42', NULL, NULL, 0),
(464, 32, 'Photos', '2026-04-29 14:32:16', NULL, NULL, 0),
(465, 32, 'Insurance', '2026-04-29 14:33:10', NULL, NULL, 0),
(466, 32, 'Submittals', '2026-04-29 14:33:40', NULL, NULL, 0),
(467, 32, 'Estimates', '2026-04-29 14:54:20', NULL, NULL, 0),
(468, 32, 'Policy', '2026-04-29 14:56:19', NULL, NULL, 0),
(469, 32, 'NOC', '2026-04-29 14:56:30', NULL, NULL, 0),
(470, 33, 'BOM', '2026-04-29 15:06:21', NULL, NULL, 0),
(471, 33, 'Insurance', '2026-04-29 15:07:14', NULL, NULL, 0),
(472, 33, 'Contract', '2026-04-29 15:07:24', NULL, NULL, 0),
(473, 33, 'Policy', '2026-04-29 15:08:20', NULL, NULL, 0),
(474, 33, 'Invoices', '2026-04-29 15:08:30', NULL, NULL, 0),
(475, 33, 'Quote From Provider', '2026-04-29 15:08:51', NULL, NULL, 0),
(476, 33, 'Estimates', '2026-04-29 15:09:13', NULL, NULL, 0),
(477, 33, 'Waivers', '2026-04-29 15:12:10', NULL, NULL, 0),
(478, 33, 'RFI', '2026-04-29 15:12:21', NULL, NULL, 0),
(479, 33, 'Fpl', '2026-04-29 15:12:43', NULL, NULL, 0),
(480, 33, 'Permissions', '2026-04-29 15:12:53', NULL, NULL, 0),
(481, 33, 'Warranty', '2026-04-29 15:13:27', NULL, NULL, 0),
(482, 33, 'Warranty Documents Rexel', '2026-04-29 15:14:06', NULL, 481, 1),
(483, 33, 'Shipping Report', '2026-04-29 15:15:04', NULL, NULL, 0),
(484, 33, 'Photos', '2026-04-29 15:15:25', NULL, NULL, 0),
(485, 33, 'Submittals', '2026-04-29 15:16:08', NULL, NULL, 0),
(486, 33, 'Change Order', '2026-04-29 15:21:37', NULL, NULL, 0),
(487, 33, 'Plans', '2026-04-29 15:21:47', NULL, NULL, 0),
(488, 33, 'Lake Carter Exchange Bldg 7', '2026-04-29 15:25:27', NULL, 487, 1),
(489, 33, 'Lake Carter Exchange Bldg 8', '2026-04-29 15:29:28', NULL, 487, 1),
(490, 34, 'BoM', '2026-04-29 15:30:09', NULL, NULL, 0),
(491, 34, 'Schedule of Values', '2026-04-29 15:30:09', NULL, NULL, 0),
(492, 34, 'RFI', '2026-04-29 15:30:09', NULL, NULL, 0),
(493, 34, 'Drawings', '2026-04-29 15:30:09', NULL, NULL, 0),
(494, 34, 'Photos', '2026-04-29 15:30:09', NULL, NULL, 0),
(495, 34, 'Panel Schedule', '2026-04-29 15:30:09', NULL, NULL, 0),
(496, 33, 'Lake Carter Exchange Bldg 11', '2026-04-29 15:32:14', NULL, 487, 1),
(497, 33, 'Drawings', '2026-04-29 15:36:21', NULL, 496, 2),
(498, 33, 'Lake Carter Exchange Bldg 11', '2026-04-29 15:36:31', NULL, 497, 3),
(499, 33, 'Lake Carter BLdg 7 8 11 12', '2026-04-29 15:38:13', NULL, 487, 1),
(500, 33, 'Drawings', '2026-04-29 15:38:36', NULL, 499, 2),
(501, 33, 'Lake carter apopka bldg7 8 11 12', '2026-04-29 15:38:47', NULL, 499, 2),
(502, 33, 'Drawings', '2026-04-29 15:38:57', NULL, 501, 3),
(503, 33, 'Lake Carter Bldg 4 5', '2026-04-29 15:39:07', NULL, 487, 1),
(504, 33, 'Lake Carter Bldg 2', '2026-04-29 15:39:17', NULL, 503, 2),
(505, 33, 'Drawings', '2026-04-29 15:39:28', NULL, 504, 3),
(506, 33, 'Drawings', '2026-04-29 15:41:40', NULL, 503, 2),
(507, 33, 'Bldg 5', '2026-04-29 15:41:50', NULL, 506, 3),
(508, 33, 'Bldg 4', '2026-04-29 15:44:05', NULL, 506, 3),
(509, 33, '1_0_Electrical-Fixture-Submittal-(Bldg-2,-3,-5,-6,-9,-10,-11,-12-&-13)_response_attachments lake car', '2026-04-29 15:46:19', NULL, 487, 1),
(510, 33, 'Lake Carter Exchange Bldg 11', '2026-04-29 15:46:30', NULL, 509, 2),
(511, 33, 'Lake carter apopka bldg 3', '2026-04-29 15:46:30', NULL, 487, 1),
(512, 33, 'Drawings', '2026-04-29 15:46:56', NULL, 511, 2),
(513, 33, 'Lake Carter Exchange Bldg 12', '2026-04-29 15:49:11', NULL, 487, 1),
(514, 35, 'Plans', '2026-04-29 15:53:36', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `folder_permissions`
--

CREATE TABLE `folder_permissions` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `granted_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `date_bid_sent` date DEFAULT NULL,
  `date_bid_awarded` date DEFAULT NULL,
  `date_started` date DEFAULT NULL,
  `date_finished` date DEFAULT NULL,
  `date_warranty_end` date DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `status` enum('Planning','Active','On Hold','Completed') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `address`, `contact_name`, `contact_phone`, `company_name`, `company_phone`, `company_address`, `date_bid_sent`, `date_bid_awarded`, `date_started`, `date_finished`, `date_warranty_end`, `notes`, `status`, `created_by`, `assigned_user_id`, `created_at`, `deleted_at`) VALUES
(32, 'Legacy Of Leesburg', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', 2, 2, '2026-04-29 13:57:51', NULL),
(33, 'Lake Carter', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', 2, 2, '2026-04-29 15:06:10', NULL),
(34, 'probe', '', '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, '', 'Active', 2, 2, '2026-04-29 15:30:09', '2026-04-29 15:30:30'),
(35, 'Leesburg Storage', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', 2, 2, '2026-04-29 15:53:26', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sub_folders`
--

CREATE TABLE `sub_folders` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_system_folder` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','technician','viewer') DEFAULT 'technician',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(2, 'admin', '$2y$10$8pCp/vJvBtX36CUHjKmEg.1j24SFYDkr83nIcMuBHTiORUF88ABU6', 'admin', '2026-01-12 13:31:11'),
(6, 'Guillermo', '$2y$10$gz31tc07T3waKGc4WrAA8OlTV1Pv7pywkHK7Q9NmkUn/CBoN0llk.', 'technician', '2026-01-13 12:11:55'),
(8, 'juan', '$2y$10$pnAjyB5ieDxcQPmb1TZ5Q.ge1lXxmUh2JiFKOOvpoLXNWPRMZhC3O', 'admin', '2026-01-15 13:00:38'),
(10, 'Maikol', '$2y$10$5tobPTBxoIcdxXqd8VqT1eBHistoRbLEGIdO.jDpVuYU2f68BDuqC', 'technician', '2026-02-20 17:57:09'),
(11, 'Henry', '$2y$10$FyCmiy0mnIMr87IrGMwAPOXVW99/CKHpPkdE.2yjvt2F6KFZq19kO', 'technician', '2026-02-20 18:02:39'),
(12, 'DavidC', '$2y$10$amS5oqbaobqaX.ChYZvWIuj50deRwVFn7J62cTC7UN1KoS7rdAKQy', 'technician', '2026-02-20 18:18:14'),
(13, 'Maikel', '$2y$10$toa5ccTUFCjBb4U62aUpJetULiwaqWllmhO8yEIrZIKOr1Kpl3RaK', 'technician', '2026-02-20 18:21:00'),
(14, 'JoseR', '$2y$10$7nUGTq9YsUpX87h8qm9OxukOpovgRq2leIEEfXdFvy8BDtMYxO2E.', 'technician', '2026-02-20 18:21:36'),
(15, 'pablo', '$2y$10$eEBKlNdzoKLkCgyTMnEVRe84F2CQheFWY8diPnIH/FPWg6vdEa.ei', 'viewer', '2026-04-20 20:02:55'),
(16, 'Isaac', '$2y$10$6Y4dCleneQ2Pv6lP..x.a.d3ZESIY1It.9LtL2LggMJrnOT2Httk2', 'admin', '2026-04-27 14:23:20');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `directory`
--
ALTER TABLE `directory`
  ADD PRIMARY KEY (`project_id`,`user_id`),
  ADD KEY `idx_directory_user` (`user_id`);

--
-- Indices de la tabla `field_report_attachments`
--
ALTER TABLE `field_report_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fra_report` (`field_report_id`),
  ADD KEY `idx_fra_created_at` (`created_at`);

--
-- Indices de la tabla `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_files_project_id` (`project_id`),
  ADD KEY `idx_files_folder_id` (`folder_id`),
  ADD KEY `idx_files_sub_folder_id` (`sub_folder_id`),
  ADD KEY `idx_files_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_deleted_files` (`deleted_at`),
  ADD KEY `idx_versions` (`version_group_id`);

--
-- Indices de la tabla `file_reports`
--
ALTER TABLE `file_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_user_id` (`user_id`),
  ADD KEY `idx_reports_active` (`file_id`,`is_deleted`);

--
-- Indices de la tabla `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_folders_project_id` (`project_id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_depth` (`depth`),
  ADD KEY `idx_folders_project_parent_name` (`project_id`,`parent_id`,`name`);

--
-- Indices de la tabla `folder_permissions`
--
ALTER TABLE `folder_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_folder_user` (`folder_id`,`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_folder_id` (`folder_id`),
  ADD KEY `fk_folder_permissions_granted_by` (`granted_by`);

--
-- Indices de la tabla `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_projects_created_by` (`created_by`),
  ADD KEY `idx_deleted_projects` (`deleted_at`),
  ADD KEY `idx_assigned_user_id` (`assigned_user_id`);

--
-- Indices de la tabla `sub_folders`
--
ALTER TABLE `sub_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subfolders_folder_id` (`folder_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `field_report_attachments`
--
ALTER TABLE `field_report_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=255;

--
-- AUTO_INCREMENT de la tabla `file_reports`
--
ALTER TABLE `file_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=515;

--
-- AUTO_INCREMENT de la tabla `folder_permissions`
--
ALTER TABLE `folder_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `sub_folders`
--
ALTER TABLE `sub_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `directory`
--
ALTER TABLE `directory`
  ADD CONSTRAINT `directory_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `directory_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `field_report_attachments`
--
ALTER TABLE `field_report_attachments`
  ADD CONSTRAINT `fk_fra_report` FOREIGN KEY (`field_report_id`) REFERENCES `file_reports` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_3` FOREIGN KEY (`sub_folder_id`) REFERENCES `sub_folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_4` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `file_reports`
--
ALTER TABLE `file_reports`
  ADD CONSTRAINT `file_reports_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_reports_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `folder_permissions`
--
ALTER TABLE `folder_permissions`
  ADD CONSTRAINT `fk_folder_permissions_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_folder_permissions_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_folder_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `sub_folders`
--
ALTER TABLE `sub_folders`
  ADD CONSTRAINT `sub_folders_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
