-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 02, 2025 at 12:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bst_manufacturing_system`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddUnitWeightColumn` ()   BEGIN
    DECLARE col_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO col_exists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbl_parts'
      AND COLUMN_NAME = 'UnitWeight';

    IF col_exists = 0 THEN
        ALTER TABLE tbl_parts
        ADD COLUMN `UnitWeight` DECIMAL(10,6) NULL DEFAULT NULL COMMENT 'وزن یک واحد از قطعه بر حسب کیلوگرم' AFTER `BaseUnitID`;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_absences`
--

CREATE TABLE `tbl_absences` (
  `AbsenceID` bigint(20) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `AbsenceDate` date NOT NULL,
  `Reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_assembly_log_entries`
--

CREATE TABLE `tbl_assembly_log_entries` (
  `AssemblyEntryID` int(11) NOT NULL,
  `AssemblyHeaderID` int(11) NOT NULL,
  `MachineID` int(11) NOT NULL,
  `Operator1ID` int(11) DEFAULT NULL,
  `Operator2ID` int(11) DEFAULT NULL,
  `StartTime` time DEFAULT NULL,
  `EndTime` time DEFAULT NULL,
  `PartID` int(11) NOT NULL,
  `ProductionKG` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_assembly_log_entries`
--

INSERT INTO `tbl_assembly_log_entries` (`AssemblyEntryID`, `AssemblyHeaderID`, `MachineID`, `Operator1ID`, `Operator2ID`, `StartTime`, `EndTime`, `PartID`, `ProductionKG`) VALUES
(1, 1, 22, 23, 14, '07:00:00', '07:30:00', 50, 3.14),
(2, 1, 22, 31, 23, '07:30:00', '12:30:00', 50, 34.20),
(3, 1, 23, 14, 34, '07:30:00', '18:30:00', 49, 79.13),
(4, 1, 20, 8, 20, '07:30:00', '17:30:00', 49, 76.00),
(5, 1, 26, 25, 24, '07:30:00', '08:50:00', 66, 21.80),
(7, 1, 26, 25, 24, '08:50:00', '18:30:00', 55, 108.40),
(9, 1, 25, 18, 37, '07:40:00', '15:30:00', 55, 94.00),
(10, 1, 21, 22, 10, '07:00:00', '08:40:00', 49, 9.20),
(12, 1, 17, 22, 15, '09:45:00', '12:59:00', 50, 14.52),
(13, 1, 21, 16, 10, '08:40:00', '18:30:00', 49, 78.75),
(14, 1, 17, 22, 11, '13:44:00', '15:20:00', 50, 9.32),
(15, 1, 17, 22, 37, '15:20:00', '17:30:00', 50, 13.30),
(16, 1, 17, 22, 20, '17:40:00', '18:30:00', 50, 4.80),
(17, 1, 25, 18, 21, '15:30:00', '17:30:00', 55, 2.50),
(18, 1, 26, 25, 24, '12:30:00', '14:40:00', 62, 18.30),
(19, 1, 22, 31, 23, '12:15:00', '18:30:00', 49, 45.00),
(20, 3, 23, 14, 20, '18:45:00', '20:00:00', 49, 9.14);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_assembly_log_header`
--

CREATE TABLE `tbl_assembly_log_header` (
  `AssemblyHeaderID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `AvailableTimeMinutes` int(11) DEFAULT NULL COMMENT 'زمان در دسترس روزانه (دقیقه)',
  `DailyProductionPlan` text DEFAULT NULL COMMENT 'برنامه تولید روزانه',
  `Description` text DEFAULT NULL COMMENT 'توضیحات کلی مربوط به روز'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_assembly_log_header`
--

INSERT INTO `tbl_assembly_log_header` (`AssemblyHeaderID`, `LogDate`, `AvailableTimeMinutes`, `DailyProductionPlan`, `Description`) VALUES
(1, '2025-10-21', 4800, '70000', 'توقفاتی وجود داشت'),
(2, '2025-10-23', NULL, NULL, NULL),
(3, '2025-10-25', 480, '', 'توقفاتی وجود داشت');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_bom_structure`
--

CREATE TABLE `tbl_bom_structure` (
  `BomID` int(11) NOT NULL,
  `ParentPartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (محصول نهایی یا مونتاژی)',
  `ChildPartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (قطعه منفصله یا زیرمجموعه)',
  `QuantityPerParent` decimal(10,3) NOT NULL DEFAULT 1.000 COMMENT 'تعداد مورد نیاز از فرزند برای ساخت 1 عدد والد',
  `RequiredStatusID` int(11) DEFAULT NULL COMMENT 'FK to tbl_part_statuses'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول ساختار محصول (BOM) - (قطعه به قطعه)';

--
-- Dumping data for table `tbl_bom_structure`
--

INSERT INTO `tbl_bom_structure` (`BomID`, `ParentPartID`, `ChildPartID`, `QuantityPerParent`, `RequiredStatusID`) VALUES
(2, 55, 13, 1.000, 1),
(6, 55, 38, 1.000, 7),
(7, 55, 40, 1.000, 7),
(8, 49, 7, 1.000, 2),
(9, 49, 35, 1.000, 2),
(10, 49, 42, 1.000, 2);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_break_times`
--

CREATE TABLE `tbl_break_times` (
  `BreakID` int(11) NOT NULL,
  `BreakName` varchar(100) NOT NULL COMMENT 'نام استراحت (ناهار، صبحانه و...)',
  `StartTime` time NOT NULL COMMENT 'زمان شروع استراحت',
  `EndTime` time NOT NULL COMMENT 'زمان پایان استراحت',
  `DepartmentID` int(11) DEFAULT NULL COMMENT 'اختیاری: برای کدام دپارتمان (NULL برای همه)',
  `IsActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'آیا این زمان استراحت فعال است؟'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول زمان‌های استراحت برنامه‌ریزی شده';

--
-- Dumping data for table `tbl_break_times`
--

INSERT INTO `tbl_break_times` (`BreakID`, `BreakName`, `StartTime`, `EndTime`, `DepartmentID`, `IsActive`) VALUES
(1, 'ناهار', '13:00:00', '13:40:00', NULL, 1),
(2, 'صبحانه', '09:30:00', '09:40:00', NULL, 1),
(3, 'عصرانه', '16:00:00', '16:10:00', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_chemicals`
--

CREATE TABLE `tbl_chemicals` (
  `ChemicalID` int(11) NOT NULL,
  `ChemicalName` varchar(150) NOT NULL,
  `ChemicalTypeID` int(11) NOT NULL,
  `UnitID` int(11) DEFAULT NULL COMMENT 'FK to tbl_units for default unit',
  `consumption_g_per_barrel` decimal(10,3) DEFAULT NULL COMMENT 'گرم مصرفی به ازای هر بارل',
  `consumption_g_per_kg` decimal(10,3) DEFAULT NULL COMMENT 'گرم مصرفی به ازای هر کیلوگرم آبکاری'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_chemicals`
--

INSERT INTO `tbl_chemicals` (`ChemicalID`, `ChemicalName`, `ChemicalTypeID`, `UnitID`, `consumption_g_per_barrel`, `consumption_g_per_kg`) VALUES
(1, 'سیانور سدیم', 1, 1, 150.000, 4.000),
(2, 'سود', 1, 1, 180.000, 5.000),
(3, 'روی', 1, 1, NULL, NULL),
(4, 'سولفور سدیم', 1, 1, NULL, NULL),
(5, 'براقی', 1, 3, NULL, NULL),
(6, 'آند', 1, NULL, NULL, NULL),
(7, 'فروکلین', 2, 3, NULL, NULL),
(8, 'جوهرنمک', 2, 3, NULL, NULL),
(9, 'کرومات', 5, 1, NULL, NULL),
(10, 'اسید نیتریک', 5, 3, NULL, NULL),
(11, 'pci', 2, 3, NULL, NULL),
(12, 'چربی گیر', 2, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_chemical_types`
--

CREATE TABLE `tbl_chemical_types` (
  `ChemicalTypeID` int(11) NOT NULL,
  `TypeName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_chemical_types`
--

INSERT INTO `tbl_chemical_types` (`ChemicalTypeID`, `TypeName`) VALUES
(1, 'افزودنی های وان آبکاری'),
(3, 'روغن'),
(4, 'سایر'),
(2, 'مواد شستشو'),
(5, 'پسیواسیون');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_contractors`
--

CREATE TABLE `tbl_contractors` (
  `ContractorID` int(11) NOT NULL,
  `ContractorName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_contractors`
--

INSERT INTO `tbl_contractors` (`ContractorID`, `ContractorName`) VALUES
(1, 'جدیدی'),
(2, 'بهلولی وایرکات'),
(3, 'کیانی');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_daily_man_hours`
--

CREATE TABLE `tbl_daily_man_hours` (
  `LogID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `DepartmentID` int(11) NOT NULL,
  `TotalManHours` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_daily_man_hours`
--

INSERT INTO `tbl_daily_man_hours` (`LogID`, `LogDate`, `DepartmentID`, `TotalManHours`) VALUES
(1, '2025-10-13', 1, 30.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_departments`
--

CREATE TABLE `tbl_departments` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_departments`
--

INSERT INTO `tbl_departments` (`DepartmentID`, `DepartmentName`) VALUES
(2, 'آبکاری'),
(8, 'اداری'),
(9, 'انبار ماده خام'),
(1, 'تولید'),
(3, 'مونتاژ'),
(4, 'پیچ سازی');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_downtimereasons`
--

CREATE TABLE `tbl_downtimereasons` (
  `ReasonID` int(11) NOT NULL,
  `ReasonDescription` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_downtimereasons`
--

INSERT INTO `tbl_downtimereasons` (`ReasonID`, `ReasonDescription`) VALUES
(1, 'خرابی دستگاه'),
(2, 'مواد ورودی'),
(3, 'خرابی قالب'),
(4, 'نبود اپراتور'),
(5, 'قطع برق و باد'),
(6, 'نداشتن برنامه تولید'),
(7, 'خرابی فیدر'),
(8, 'تعویض ورق یا قالب');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_downtime_log`
--

CREATE TABLE `tbl_downtime_log` (
  `LogID` bigint(20) NOT NULL,
  `LogDate` date NOT NULL,
  `MachineID` int(11) NOT NULL,
  `MoldID_AtTime` int(11) DEFAULT NULL,
  `ReasonID` int(11) NOT NULL,
  `DowntimeMinutes` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_employees`
--

CREATE TABLE `tbl_employees` (
  `EmployeeID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `JobTitle` varchar(150) DEFAULT NULL,
  `HireDate` date DEFAULT NULL,
  `Status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_employees`
--

INSERT INTO `tbl_employees` (`EmployeeID`, `name`, `DepartmentID`, `JobTitle`, `HireDate`, `Status`) VALUES
(1, 'سید احمد حسینی', 1, 'سر کارگر', '2018-10-20', 'Active'),
(3, 'مهدی رجبی', 3, 'تاسیسات', '0000-00-00', 'Active'),
(4, 'امید مرتضی', 1, 'تراشکار', '0000-00-00', 'Active'),
(5, 'حسین عزیزی', 3, 'مونتاژ کار', '2019-01-18', 'Active'),
(6, 'ایوب فیضی', 4, 'پیچ ساز', '2022-11-29', 'Active'),
(7, 'امیر شیخی', 8, 'مدیر فنی مهندسی', '2020-01-28', 'Active'),
(8, 'حمید فریدی راد', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(9, 'محمد رحیمی', 2, 'آبکار', '0000-00-00', 'Active'),
(10, 'رضا صادقی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(11, 'مهرداد مرادی', 3, 'مدیر مونتاژ', '0000-00-00', 'Active'),
(12, 'محمد دربان', 8, 'مدیر کیفیت', '0000-00-00', 'Active'),
(13, 'احسان حدادی', 8, 'برنامه ریز', '0000-00-00', 'Active'),
(14, 'حسن صادقی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(15, 'حسن ستاری', 3, 'انباردار', '0000-00-00', 'Active'),
(16, 'محسن امیدعلی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(17, 'امیر قناعتی', 1, 'قالبساز', '0000-00-00', 'Active'),
(18, 'حجت خدابخشی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(19, 'کریم صحبت لو', 1, 'قالب بند', '0000-00-00', 'Active'),
(20, 'بهراد ذبیحی', 2, 'آبکار', '0000-00-00', 'Active'),
(21, 'علی درستکار', 2, 'آبکار', '0000-00-00', 'Active'),
(22, 'علی ولیپور', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(23, 'رضا نوبخت', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(24, 'مرتضی سلطانی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(25, 'مهدی سلیمی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(26, 'یوسف محمدی', 1, 'مدیر تولید', '0000-00-00', 'Active'),
(27, 'هستی امیدعلی', 8, 'حسابدار', '0000-00-00', 'Active'),
(28, 'حسن کمربیگی', 1, 'پیچ ساز', '0000-00-00', 'Active'),
(29, 'محمدرضا حاتمی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(30, 'حمیدرضا خلیلی', 1, 'حسابدار', '0000-00-00', 'Active'),
(31, 'امیرحسین فهیمی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(32, 'عباس ناطقی', 1, 'پیچ ساز', '0000-00-00', 'Active'),
(33, 'امیرحسین محمدی', 1, 'پرسکار', '0000-00-00', 'Active'),
(34, 'محمد فرزین', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(35, 'ابوالفضل یاسینی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(36, 'سبحان یاسینی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(37, 'علیرضا یعقوبی', 3, 'مونتاژکار', '0000-00-00', 'Active'),
(38, 'محمدرضا وظیفه شناس', 3, 'بسته بند', '0000-00-00', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_engineering_changes`
--

CREATE TABLE `tbl_engineering_changes` (
  `ChangeID` int(11) NOT NULL,
  `ChangeDate` date NOT NULL,
  `ChangeType` enum('Mold','Process','Other') NOT NULL,
  `EntityID` int(11) DEFAULT NULL COMMENT 'Can be MoldID or ProcessID',
  `EntityNameCustom` varchar(255) DEFAULT NULL,
  `SparePartID` int(11) DEFAULT NULL,
  `CurrentSituation` text DEFAULT NULL,
  `ReasonForChange` text NOT NULL,
  `ChangesMade` text NOT NULL,
  `ApprovedByEmployeeID` int(11) DEFAULT NULL,
  `DocumentationLink` varchar(512) DEFAULT NULL COMMENT 'Link or path to the change documentation file'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_engineering_changes`
--

INSERT INTO `tbl_engineering_changes` (`ChangeID`, `ChangeDate`, `ChangeType`, `EntityID`, `EntityNameCustom`, `SparePartID`, `CurrentSituation`, `ReasonForChange`, `ChangesMade`, `ApprovedByEmployeeID`, `DocumentationLink`) VALUES
(2, '2025-10-18', 'Process', 1, NULL, NULL, 'بیب', 'بسشبس', 'بشبش', 5, 'documents/1760783461_.doc');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_engineering_change_feedback`
--

CREATE TABLE `tbl_engineering_change_feedback` (
  `FeedbackID` int(11) NOT NULL,
  `ChangeID` int(11) NOT NULL,
  `FeedbackDate` date NOT NULL,
  `FeedbackDescription` text NOT NULL,
  `FutureActions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_eng_spare_parts`
--

CREATE TABLE `tbl_eng_spare_parts` (
  `PartID` int(11) NOT NULL,
  `PartCode` varchar(50) NOT NULL,
  `PartName` varchar(150) NOT NULL,
  `MoldID` int(11) DEFAULT NULL,
  `ReorderPoint` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_eng_spare_parts`
--

INSERT INTO `tbl_eng_spare_parts` (`PartID`, `PartCode`, `PartName`, `MoldID`, `ReorderPoint`) VALUES
(1, '10203001', 'سنبه 1 قالب 3/4 اینچ', 2, 0),
(2, '10203002', 'سنبه ناخنی  قالب 3/4 اینچ\r\n', 2, 0),
(3, '10203003', 'سنبه سوراخ H قالب 3/4 اینچ\r\n', 2, 3),
(4, '10203004', 'سنبه برش وسط تسمه قالب 3/4 اینچ', 2, 4),
(5, '10203005', 'سنبه برش انتهای تسمه قالب 3/4 اینچ', 2, 2),
(6, '10203006', 'سنبه برش اخر تسمه H قالب 3/4 اینچ', 2, 2),
(7, '10203007', 'سنبه برش بین دو تسمه  سمت H قالب 3/4 اینچ', 2, 2),
(8, '10203008', 'سنبه برش پهن بین دو تسمه قالب 3/4 اینچ', 2, 2),
(9, '10203009', 'سنبه برش فرم قالب 3/4 اینچ', 2, 10),
(10, '10210001', 'سنبه پایلوت قالب 3/4 اینچ', 2, 4),
(11, '10206003', 'اینسرت سنبه 1  قالب 3/4 اینچ', 2, 1),
(12, '10206004', 'اینسرت  سنبه ناخنی قالب 3/4 اینچ', 2, 2),
(14, '10206011', 'لوبیایی سنبه فرم قالب 3/4 اینچ', 2, 6),
(15, '10303001', 'سنبه 1 قالب 1-1/4', 3, 4),
(16, '10303002', 'سنبه ناخنی  قالب 1-1/4', 3, 0),
(17, '10303003', 'سنبه سوراخ H قالب 1-1/4', 3, 2),
(18, '10303004', 'سنبه برش وسط تسمه  قالب 1-1/4', 3, 2),
(19, '10303005', 'سنبه برش انتهای تسمه قالب 1-1/4', 3, 2),
(20, '10303006', 'سنبه برش اخر تسمه H قالب 1-1/4', 3, 2),
(21, '10303007', 'سنبه برش بین دو تسمه  سمت H قالب 1-1/4', 3, 2),
(22, '10303008', 'سنبه برش پهن بین دو تسمه قالب 1-1/4', 3, 2),
(23, '10310001', 'پایلوت قالب 1-1/4', 3, 2),
(24, '10303010', 'سنبه برش فرم قالب 1-1/4', 3, 2),
(25, '10306014', 'اینزرت برش اول قالب 1-1/4', 3, 2),
(26, '10306004', 'اینزرت ناخنی قالب 1-1/4', 3, 2),
(27, '10403001', 'سنبه 1 قالب 1-3/4', 4, 2),
(28, '10403002', 'سنبه ناخنی  قالب 1-3/4', 4, 40),
(29, '10403003', 'سنبه برش وسط تسمه قالب 1-3/4', 4, 2),
(30, '10403004', 'سنبه سوراخ H قالب 1-3/4', 4, 2),
(31, '10403005', 'سنبه برش انتهای تسمه قالب 1-3/4', 4, 2),
(32, '10403006', 'سنبه برش اخر تسمه H قالب 1-3/4', 4, 2),
(33, '10403007', 'سنبه برش بین دو تسمه  سمت H قالب 1-3/4', 4, 2),
(34, '10403008', 'سنبه برش پهن بین دو تسمه قالب 1-3/4', 4, 2),
(35, '10403009', 'سنبه جدا کننده قطعه قالب 1-3/4', 4, 2),
(36, '10403010', 'سنبه گیوتین بر قالب 1-3/4', 4, 2),
(37, '10403011', 'سنبه انتهای اتسمه 1-1/2 قالب 1-3/4', 4, 2),
(38, '10406009', 'اینسرت سنبه برشهای پهن بین دو تسمه قالب 1-3/4', 4, 1),
(39, '10406010', 'اینسرت سنبه برشهای پهن بین دو تسمه قالب 1-3/4', 4, 1),
(40, '10406012', 'اینسرت کات اف انتهای قطعه  قالب 1-3/4', 4, 1),
(41, '10410001', 'پین پایلوت قالب 1-3/4', 4, 2),
(42, '10503001', 'سنبه 1 قالب جنرال', 5, 1),
(43, '10503002', 'سنبه دور بر 2 تکه قالب جنرال', 5, 2),
(44, '10506003', 'سنبه مارک قالب جنرال', 5, 2),
(45, '10503004', 'سنبه سوراخ H قالب جنرال', 5, 2),
(46, '10506002', ' اینسرت ماتریس قالب جنرال', 5, 0),
(47, '11703001', 'سنبه گریپر 1 قالب hb-0/5', 17, 4),
(48, '11703002', 'سنبه وسط بر قالب hb-0/5', 17, 2),
(49, '11703003', 'سنبه مثلثی بزرگ قالب hb-0/5', 17, 4),
(50, '11703004', 'سنبه ابرویی قالب hb-0/5', 17, 2),
(51, '11703006', 'سنبه ناخنی  قالب hb-0/5', 17, 2),
(52, '11703007', 'سنبه مثلثی کوچک قالب hb-0/5', 17, 4),
(53, '11703008', 'سنبه خم قالب hb-0/5', 17, 2),
(54, '11706007', 'اینسرت مثلثی کوچک قالب hb-0/5', 17, 1),
(55, '11706008', 'اینسرت سنبه خم قالب hb-0/5', 17, 2),
(56, '11706009', 'سکوی قرار قالب hb-0/5', 17, 1),
(57, '11706010', 'اینزرت ناخنی قالب hb-0/5', 17, 2),
(58, '10903002', 'سنبه قرار قالب hb-0/5', 17, 2),
(59, '11003001', 'سنبه L شکل قالب پلوسی', 10, 4),
(60, '11003003', 'سنبه H قالب پلوسی', 10, 2),
(61, '11003004', 'سنبه پایلوت قالب پلوسی', 10, 2),
(62, '11003006', 'سنبه 1.84 میل قالب پلوسی', 10, 2),
(63, '11006002', 'اینسرت برش اول قالب پلوسی', 10, 1),
(64, '11006007', 'اینسرت دوم قالب پلوسی', 10, 1),
(65, '11006003', 'سندان سنبه مربعی قالب پلوسی', 10, 1),
(66, '11203001', 'سنبه 1 قالب 5/8 اینچ', 12, 5),
(67, '11203003', 'سنبه سوراخ H قالب 5/8 اینچ', 12, 4),
(68, '11203004', 'سنبه برش وسط تسمه قالب 5/8 اینچ', 12, 4),
(69, '11203005', 'سنبه برش انتهای تسمه قالب 5/8 اینچ', 12, 4),
(70, '11203006', 'سنبه برش اخر تسمه H قالب 5/8 اینچ', 12, 2),
(71, '11203007', 'سنبه برش بین دو تسمه  سمت H قالب 5/8 اینچ', 12, 2),
(72, '11203008', 'سنبه برش پهن بین دو تسمه قالب 5/8 اینچ', 12, 2),
(73, '11203009', 'سنبه جدا کننده قطعه قالب 5/8 اینچ', 12, 0),
(74, '11203010', 'مارک قالب 5/8 اینچ', 12, 0),
(75, '11210001', 'سنبه پایلوت قالب 5/8 اینچ', 12, 4),
(76, '11206003', 'اینسرت سنبه ناخنی قالب 5/8 اینچ', 12, 2),
(77, '11206007', 'لوبیایی سنبه فرم قالب 5/8 اینچ', 12, 8),
(78, '11303001', 'سنبه 1 قالب 1  اینچ', 13, 0),
(79, '11303002', 'سنبه ناخنی  قالب 1  اینچ', 13, 27),
(80, '11303003', 'سنبه سوراخ H قالب 1  اینچ', 13, 0),
(81, '11303004', 'سنبه برش وسط تسمه قالب 1  اینچ', 13, 2),
(82, '11303005', 'سنبه برش انتهای تسمه قالب 1  اینچ', 13, 2),
(83, '11303006', 'سنبه برش اخر تسمه H قالب 1  اینچ', 13, 2),
(84, '11303007', 'سنبه برش بین دو تسمه  سمت H قالب 1  اینچ', 13, 2),
(85, '11303008', 'سنبه برش پهن بین دو تسمه قالب 1  اینچ', 13, 2),
(86, '11303009', 'سنبه جدا کننده قطعه قالب 1  اینچ', 13, 4),
(87, '11306006', 'لوبیایی سنبه فرم قالب 1  اینچ', 13, 6),
(88, '11310001', 'سنبه پایلوت 1 قالب 1  اینچ', 13, 2),
(89, '11310002', 'سنبه پایلوت 2 قالب 1  اینچ', 13, 2),
(90, '11403001', 'ساید کاتر قالب 2-1/2', 13, 2),
(91, '11403002', 'برش وسط قالب 2-1/2', 13, 2),
(92, '11403003', 'سنبه سوراخ H قالب 2-1/2', 13, 2),
(93, '11403004', 'سنبه برش تیز قالب 2-1/2', 13, 2),
(94, '11403005', 'سنبه ناخنی  قالب 2-1/2', 13, 2),
(95, '11403006', 'سنبه مثلثی قالب 2-1/2', 13, 2),
(96, '11403007', 'سنبه برش پهن بین دو تسمه  سمت H قالب 2-1/2', 13, 2),
(97, '11403008', 'سنبه برش انتهای تسمه قالب 2-1/2', 13, 2),
(98, '11403009', 'سنبه برش پهن انتهای قطعه قالب 2-1/2', 13, 2),
(99, '11403011', 'سنبه مارک قالب 2-1/2', 13, 2),
(100, '11406002', 'اینسرت ساید کاتر و وسط قالب 2-1/2', 13, 1),
(101, '11406003', 'اینزرت ساید کاتر و برش اچ قالب 2-1/2', 13, 1),
(102, '11406004', 'اینزرت ناخنی قالب 2-1/2', 13, 0),
(103, '11406005', 'اینزرت گیوتین بر قالب 2-1/2', 13, 2),
(104, '11406006', 'اینزرت برس انتهای تسمه قالب 2-1/2', 13, 0),
(105, '11406007', 'اینزرت پایلوت وسط بر قالب 2-1/2', 13, 0),
(106, '11406008', 'اینزرت پایلوت سنبه تیز قالب 2-1/2', 13, 0),
(107, '11406009', 'اینزرت سنبه پهن سمت اچ قالب 2-1/2', 13, 0),
(108, '11406010', 'اینزرت سنبه پهن سمت انتهای قطعه قالب 2-1/2', 13, 0),
(109, '11406011', 'اینزرت سنبه مارک قالب 2-1/2', 13, 0),
(110, '11410001', 'سنبه پایلوت 1 قالب 2-1/2', 13, 2),
(111, '11410002', 'سنبه پایلوت 2 قالب 2-1/2', 13, 2),
(112, '11410003', 'سنبه پایلوت 3 قالب 2-1/2', 13, 2),
(113, '11502002', 'گریپر سنبه خم قالب ha-0/45', 15, 1),
(114, '11503001', 'سنبه گریپر 1 قالب ha-0/45', 15, 2),
(115, '11503002', 'سنبه وسط بر قالب ha-0/45', 15, 2),
(116, '11503003', 'سنبه مثلثی بزرگ قالب ha-0/45', 15, 2),
(117, '11503004', 'سنبه گرد قالب ha-0/45', 15, 2),
(118, '11503005', 'سنبه کلید شکل قالب ha-0/45', 15, 2),
(119, '11503007', 'سنبه مثلثی کوچک قالب ha-0/45', 15, 4),
(120, '11503008', 'سنبه خم قالب ha-0/45', 15, 2),
(121, '11510001', 'پین پایلوت قالب ha-0/45', 15, 2),
(122, '11506007', 'اینسرت مثلثی کوچک قالب ha-0/45', 15, 1),
(123, '11506001-A', 'ماتریس - بازسازی قالب ha-0/45', 15, 0),
(124, '11506004', 'اینسرت سنبه خم قالب ha-0/45', 15, 2),
(125, '11506008', 'اینسرت برش اول قالب ha-0/45', 15, 0),
(126, '11506009', 'اینسرت مارک قالب ha-0/45', 15, 0),
(127, '11506010', 'اینسرت پایلوت قالب ha-0/45', 15, 0),
(128, '11803001', 'سنبه گریپر 1 قالب hb-0/75', 15, 2),
(129, '11803003', 'سنبه مثلثی بزرگ قالب hb-0/75', 15, 2),
(130, '12103002', 'سنبه ناخنی هلالی قالب 1016 r-b', 21, 2),
(131, '12103005', 'سنبه انتهای تسمه قالب 1016 r-b', 21, 2),
(132, '12103006', 'سنبه هلالی سر تسمه قالب 1016 r-b', 21, 2),
(133, '12103010', 'سنبه سوراخ روی اینزرت قالب 1016 r-b', 21, 2),
(134, '12103011', 'سنبه باریک سر تسمه قالب 1016 r-b', 21, 2),
(135, '12106003-C', 'اینزرت ناخنی قالب 1016 r-b', 21, 1),
(136, '12111001', 'مارک قالب 1016 r-b', 21, 0),
(137, '12211001', 'مارک قالب 1319 r-b', 21, 0),
(138, '122006003-B', 'اینزرت ناخنی قالب 1319 r-b', 22, 2),
(139, '12303013', 'سنبه ذوزنقه اینزرت قالب 1825 r-b', 23, 2),
(140, '12303012', 'سنبه مثلثی اینزرت قالب 1825 r-b', 23, 2),
(141, '12311001', 'مارک قالب 1825 r-b', 23, 0),
(142, '12403001', 'سنبه گریپر 1 قالب HA-0/65', 24, 2),
(143, '12403003', 'سنبه مثلثی بزرگ قالب HA-0/65', 24, 2),
(144, '12403008', 'سنبه فروبر لاغر قالب HA-0/65', 24, 2),
(145, '10204002', 'فرم بالا قالب 3/4 اینچ', 2, 1),
(146, '10206005', 'فرم ‍‍پایین قالب 3/4 اینچ', 2, 1),
(147, '11204002', 'فرم بالا قالب 5/8 اینچ', 12, 1),
(148, '11206005', 'فرم ‍‍پایین قالب 5/8 اینچ', 12, 1),
(149, '11304002', 'فرم بالا قالب 1  اینچ', 13, 1),
(150, '11306005', 'فرم ‍‍پایین قالب 1  اینچ', 13, 1),
(151, '11511001', 'مارک قالب ha-0/45', 15, 1),
(152, '10202001', 'سنبه گیر قالب 3/4 اینچ', 2, 0),
(153, '10202002', 'اینزرت سنبه گیر قالب 3/4 اینچ', 2, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_eng_spare_part_transactions`
--

CREATE TABLE `tbl_eng_spare_part_transactions` (
  `TransactionID` bigint(20) NOT NULL,
  `TransactionDate` datetime DEFAULT current_timestamp(),
  `PartID` int(11) NOT NULL,
  `MoldID` int(11) DEFAULT NULL,
  `Quantity` int(11) NOT NULL,
  `TransactionTypeID` int(11) NOT NULL,
  `SenderEmployeeID` int(11) DEFAULT NULL,
  `ReceiverEmployeeID` int(11) DEFAULT NULL,
  `UsageLocation` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `OrderID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_eng_spare_part_transactions`
--

INSERT INTO `tbl_eng_spare_part_transactions` (`TransactionID`, `TransactionDate`, `PartID`, `MoldID`, `Quantity`, `TransactionTypeID`, `SenderEmployeeID`, `ReceiverEmployeeID`, `UsageLocation`, `Description`, `OrderID`) VALUES
(8, '2025-10-15 00:00:00', 3, 3, 2, 1, NULL, 3, '', '', 3),
(9, '2025-10-15 00:00:00', 1, NULL, 5, 1, NULL, 3, '', '', 6),
(11, '2025-10-25 00:00:00', 37, 4, 6, 1, NULL, 7, '', '', 7);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_eng_tools`
--

CREATE TABLE `tbl_eng_tools` (
  `ToolID` int(11) NOT NULL,
  `ToolCode` varchar(50) NOT NULL,
  `ToolName` varchar(255) NOT NULL,
  `ToolTypeID` int(11) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_eng_tools`
--

INSERT INTO `tbl_eng_tools` (`ToolID`, `ToolCode`, `ToolName`, `ToolTypeID`, `DepartmentID`) VALUES
(1, 'T-4-1-1', 'سی بی ان استوانه ای بلند', 4, 1),
(2, 'T-4-1-2', 'سی بی ان استوانه ای دنباله 3', 4, 1),
(3, 'T-4-1-3', 'سی بی ان استوانه ای دنباله 6', 4, 1),
(4, 'T-4-1-4', 'سی بی ان مخروطی دنباله 3', 4, 1),
(5, 'T-4-1-5', 'سی بی ان مخروطی دنباله 6', 4, 1),
(6, 'T-3-1-1', 'الماس جوشی H1S', 3, 1),
(7, 'T-3-1-2', 'الماس جوشی M20', 3, 1),
(8, 'T-8-1-1', 'آهنربا نئودیمیم قطر 2 ضخامت 2 میلی متر', 8, 1),
(9, 'T-8-1-2', 'آهنربا نئودیمیم قطر 3 ضخامت 2 میلی متر', 8, 1),
(10, 'T-9-1-1', 'برقو 12 H7', 9, 1),
(11, 'T-28-9-1', 'بلوک ارتفاع 68', 28, 9),
(12, 'T-10-1-1', 'بوش راهنما قطر خارجی 30', 10, 1),
(13, 'T-29-1-1', 'پین قطر 10 ', 29, 1),
(14, 'T-2-4-1', ' پین پانچ دو سو  SW 7.74', 2, 4),
(15, 'T-2-4-2', ' پین پانچ دو سو SW 6.20', 2, 4),
(16, 'T-2-4-3', ' پین پانچ دو سو چهارسو SW 6.20', 2, 4),
(17, 'T-2-4-4', ' پین پانچ دو سو چهارسو SW 7.74 ', 2, 4),
(18, 'T-2-4-5', 'پین پانچ دو سو کوچک طلایی  بارسازی شده SW 6/20 ', 2, 4),
(19, 'T-11-9-1', 'تسمه 2379', 11, 9),
(20, 'T-12-9-1', 'تیغچه 25*25*200', 12, 9),
(21, 'T-12-9-2', 'تیغچه 3*20*200', 12, 9),
(22, 'T-12-9-3', 'تیغچه 6*200', 12, 9),
(23, 'T-13-1-1', ' سنسور چشمی', 13, 1),
(24, 'T-14-1-1', ' خشکه  مسی', 14, 1),
(25, 'T-15-1-1', ' الماس داخل زن کوچک', 15, 1),
(26, 'T-15-1-2', ' داخل زن 10', 15, 1),
(27, 'T-15-1-3', ' داخل زن به همراه 3 تیغچه', 15, 1),
(28, 'T-16-1-1', ' پیچ دنده کبریتی محور xy', 16, 1),
(29, 'T-17-1-1', 'سنباده 100', 5, 1),
(30, 'T-17-1-2', 'سنباده 150', 5, 1),
(31, 'T-17-1-3', 'سنباده 180', 5, 1),
(32, 'T-17-1-4', 'سنباده 360', 5, 1),
(33, 'T-17-1-5', 'سنباده 600', 5, 1),
(34, 'T-17-1-6', 'سنباده 800', 5, 1),
(35, 'T-18-1-1', ' سنگ انگشتی دنباله 6', 18, 1),
(36, 'T-18-1-2', ' سنگ انگشتی دنباله 8', 18, 1),
(37, 'T-19-1-1', ' سوهان الماس', 19, 1),
(38, 'T-1-1-1', ' فرز الماس قطر 4/6', 1, 1),
(39, 'T-1-1-2', ' فرز الماس قطر 5', 1, 1),
(40, 'T-1-1-3', ' فرز الماس قطر 6', 1, 1),
(41, 'T-1-1-4', ' فرز الماس قطر 8', 1, 1),
(42, 'T-1-1-5', ' فرز کارباید', 1, 1),
(43, 'T-1-1-6', ' فرز الماس قطر 10', 1, 1),
(44, 'T-1-1-7', ' فرز الماس قطر 12', 1, 1),
(45, 'T-1-1-8', ' فرز الماس قطر 16', 1, 1),
(46, 'T-1-1-9', ' فرز الماس قطر 2/5', 1, 1),
(47, 'T-1-1-10', ' فرز الماس قطر 3', 1, 1),
(48, 'T-1-1-11', ' فرز الماس قطر 3/5', 1, 1),
(49, 'T-1-1-12', ' فرز الماس قطر 4', 1, 1),
(50, 'T-1-1-13', ' فرز الماس قطر 5/5', 1, 1),
(51, 'T-1-1-14', ' فرز الماس قطر 7/5', 1, 1),
(52, 'T-6-1-1', ' فلاپ بزرگ', 6, 1),
(53, 'T-6-1-2', ' فلاپ کوچک', 6, 1),
(54, 'T-20-4-1', ' پوسته چکش اول کوچک', 20, 4),
(55, 'T-20-4-2', ' پوسته چکش دوم بزرگ', 20, 4),
(56, 'T-20-4-3', ' پوسته چکش دوم کوچک', 20, 4),
(57, 'T-20-4-4', ' پانچ بزرگ', 20, 4),
(58, 'T-20-4-5', ' پانچ شش گوشه sw 7/74', 20, 4),
(59, 'T-20-4-6', ' پانچ کوچک', 20, 4),
(60, 'T-20-4-7', ' پران قالب مادر 3/40 *80', 20, 4),
(61, 'T-20-4-8', ' پران قالب مادر 5/85 *80', 20, 4),
(62, 'T-20-4-9', ' پوسته چکش اول پیچ بزرگ', 20, 4),
(63, 'T-20-4-10', ' پوسته چکش دوم پرس پیچ', 20, 4),
(64, 'T-20-4-11', ' غلطک فیدر پرس پیچ کوچک', 20, 4),
(65, 'T-20-4-12', ' فک چونزو', 20, 4),
(66, 'T-20-4-13', ' فک رزوه بزرگ', 20, 4),
(67, 'T-20-4-14', ' فک گلویی کوچک', 20, 4),
(68, 'T-20-4-15', ' قالب مادر کوچک ( کارباید)', 20, 4),
(69, 'T-20-4-16', ' میل سختکاری شده 10', 20, 4),
(70, 'T-20-4-17', ' میل سختکاری شده 12', 20, 4),
(71, 'T-21-1-1', 'قلاویز m12', 21, 1),
(72, 'T-22-1-1', ' قیراط 8', 22, 1),
(73, 'T-23-1-1', ' کولت 3', 23, 1),
(74, 'T-23-1-2', ' کولت بزرگ', 23, 1),
(75, 'T-23-1-3', ' کولت کوچک', 23, 1),
(76, 'T-30-1-1', ' کولیس insize 15 cm', 30, 1),
(77, 'T-30-1-2', ' کولیس insize 20 cm', 30, 1),
(78, 'T-24-9-1', 'گرد 70*74', 24, 9),
(79, 'T-24-9-2', 'گرد 74*70', 24, 9),
(80, 'T-24-9-3', ' گرد بلوک سنبه', 24, 9),
(81, 'T-24-9-4', 'گرد 68*120', 24, 9),
(82, 'T-24-9-5', 'گرد قطر 120 *68', 24, 9),
(83, 'T-24-9-6', 'گرد گرمکار قطر 50 * 200', 24, 9),
(84, 'T-7-1-1', ' مته الماس 10', 7, 1),
(85, 'T-7-1-2', ' مته الماس قطر 10', 7, 1),
(86, 'T-7-1-3', ' مته الماس قطر 12', 7, 1),
(87, 'T-7-1-4', ' مته الماس قطر 5', 7, 1),
(88, 'T-7-1-5', ' مته الماس قطر 6/5', 7, 1),
(89, 'T-7-1-6', ' مته الماس قطر 7', 7, 1),
(90, 'T-7-1-7', ' مته الماس قطر 8', 7, 1),
(91, 'T-7-1-8', ' مته الماس', 7, 1),
(92, 'T-7-1-9', ' مته الماس 10', 7, 1),
(93, 'T-25-1-1', 'میل راهنما 12*100', 25, 1),
(94, 'T-25-1-2', 'میل راهنما 16*100', 25, 1),
(95, 'T-25-1-3', 'میل راهنما 24*100', 25, 1),
(96, 'T-26-4-1', 'میله قطر 10 کبالت', 26, 4),
(97, 'T-26-4-2', 'میله قطر 8 کبالت', 26, 4),
(98, 'T-26-4-3', 'میله کبالت قطر 10', 26, 4),
(99, 'T-26-4-4', ' میل قطر 10پنج درصد طول 200', 26, 4),
(100, 'T-26-4-5', ' میل گرد  8 سخت کاری', 26, 4),
(101, 'T-26-4-6', ' میله قطر 10 طول 200', 26, 4),
(102, 'T-11-9-2', 'تسمه 2410', 11, 9),
(103, 'T-31-3-1', 'پیچ انداز ریل محفظه', 31, 3),
(104, 'T-31-3-2', 'پیچ انداز ریل  پبچ', 31, 3),
(105, 'T-31-3-3', 'پیچ انداز سنبه ها', 31, 3),
(106, 'T-31-3-4', 'پیچ انداز سنبه گیر جلو', 31, 3),
(107, 'T-27-1-1', 'سنگ الماس قطر 9', 27, 1),
(108, 'T-27-1-2', 'سنگ الماس قطر 20', 27, 1),
(109, 'T-27-1-3', 'سنگ الماس قطر 20', 27, 1),
(110, 'T-28-9-2', 'بلوک 2510', 28, 9),
(111, 'T-28-9-3', 'بلوک 2379', 28, 9),
(112, 'T-20-4-18', ' فک رزوه کوچک', 20, 4);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_eng_tool_transactions`
--

CREATE TABLE `tbl_eng_tool_transactions` (
  `TransactionID` bigint(20) NOT NULL,
  `TransactionDate` datetime NOT NULL DEFAULT current_timestamp(),
  `ToolID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `TransactionTypeID` int(11) NOT NULL,
  `SenderEmployeeID` int(11) DEFAULT NULL,
  `ReceiverEmployeeID` int(11) DEFAULT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_eng_tool_types`
--

CREATE TABLE `tbl_eng_tool_types` (
  `ToolTypeID` int(11) NOT NULL,
  `TypeName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_eng_tool_types`
--

INSERT INTO `tbl_eng_tool_types` (`ToolTypeID`, `TypeName`) VALUES
(8, 'آهنربا'),
(3, 'الماس جوشی'),
(15, 'الماس داخل زن'),
(9, 'برقو'),
(28, 'بلوک'),
(10, 'بوش راهنما'),
(11, 'تسمه'),
(12, 'تیغچه'),
(14, 'خشکه'),
(16, 'دستگاه تراش'),
(5, 'سنباده'),
(27, 'سنگ الماس'),
(18, 'سنگ انگشتی'),
(19, 'سوهان'),
(4, 'سی بی ان'),
(1, 'فرز'),
(6, 'فلاپ'),
(20, 'قطعات پیچ سازی'),
(21, 'قلاویز'),
(22, 'قیرات'),
(7, 'مته'),
(25, 'میل راهنما'),
(26, 'میله'),
(29, 'پین'),
(2, 'پین پانچ'),
(31, 'پیچ انداز'),
(13, 'چشمی'),
(23, 'کولت'),
(30, 'کولیس'),
(24, 'گرد');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_family_status_compatibility`
--

CREATE TABLE `tbl_family_status_compatibility` (
  `FamilyID` int(11) NOT NULL COMMENT 'FK to tbl_part_families',
  `StatusID` int(11) NOT NULL COMMENT 'FK to tbl_part_statuses'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Links applicable statuses to part families';

--
-- Dumping data for table `tbl_family_status_compatibility`
--

INSERT INTO `tbl_family_status_compatibility` (`FamilyID`, `StatusID`) VALUES
(1, 1),
(1, 2),
(1, 6),
(1, 7),
(1, 17),
(1, 21),
(2, 2),
(2, 6),
(2, 17),
(2, 21),
(3, 2),
(3, 9),
(3, 11),
(3, 17),
(3, 19),
(3, 20),
(3, 21),
(3, 30),
(4, 7),
(6, 2),
(6, 7),
(6, 8),
(6, 17),
(6, 21),
(9, 2),
(9, 9),
(9, 11),
(9, 17),
(9, 19),
(9, 20),
(9, 21),
(9, 30),
(10, 6),
(10, 7),
(11, 1),
(11, 3),
(11, 4),
(11, 6),
(12, 1),
(12, 3),
(12, 4),
(12, 6);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_safety_stock`
--

CREATE TABLE `tbl_inventory_safety_stock` (
  `SafetyStockID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL COMMENT 'FK to tbl_parts - کدام قطعه',
  `StationID` int(11) NOT NULL COMMENT 'FK to tbl_stations - در کدام ایستگاه/انبار',
  `StatusID` int(11) DEFAULT NULL COMMENT 'FK to tbl_part_statuses - با کدام وضعیت',
  `SafetyStockValue` decimal(10,3) NOT NULL COMMENT 'مقدار موجودی اطمینان',
  `Unit` enum('KG','Carton') NOT NULL COMMENT 'واحد اندازه‌گیری (کیلوگرم یا کارتن)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول نقاط سفارش (موجودی اطمینان) برای قطعات و محصولات';

--
-- Dumping data for table `tbl_inventory_safety_stock`
--

INSERT INTO `tbl_inventory_safety_stock` (`SafetyStockID`, `PartID`, `StationID`, `StatusID`, `SafetyStockValue`, `Unit`) VALUES
(1, 55, 11, 11, 40.000, 'Carton'),
(2, 7, 8, 2, 150.000, 'KG');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_snapshots`
--

CREATE TABLE `tbl_inventory_snapshots` (
  `SnapshotID` int(11) NOT NULL,
  `SnapshotTimestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `RecordedByUserID` int(11) DEFAULT NULL,
  `FilterFamilyID` int(11) DEFAULT NULL,
  `FilterPartID` int(11) DEFAULT NULL,
  `FilterStatusID` int(11) DEFAULT NULL,
  `InventoryData` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON containing the inventory breakdown' CHECK (json_valid(`InventoryData`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores snapshots of warehouse inventory calculations.';

--
-- Dumping data for table `tbl_inventory_snapshots`
--

INSERT INTO `tbl_inventory_snapshots` (`SnapshotID`, `SnapshotTimestamp`, `RecordedByUserID`, `FilterFamilyID`, `FilterPartID`, `FilterStatusID`, `InventoryData`) VALUES
(6, '2025-10-29 16:32:23', 1, NULL, NULL, 11, '[{\"PartID\":55,\"PartName\":\"بست بزرگ 1-1/16\",\"StatusAfterID\":11,\"StatusName\":\"بسته بندی شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":0},{\"PartID\":58,\"PartName\":\"بست بزرگ 1-3/4\",\"StatusAfterID\":11,\"StatusName\":\"بسته بندی شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":8},{\"PartID\":50,\"PartName\":\"بست کوچک 1319\",\"StatusAfterID\":11,\"StatusName\":\"بسته بندی شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":5}]'),
(7, '2025-10-29 16:41:38', 1, NULL, NULL, NULL, '[{\"PartID\":55,\"PartName\":\"بست بزرگ 1-1/16\",\"StatusAfterID\":2,\"StatusName\":\"آبکاری شده\",\"CurrentBalanceKG\":130.2,\"CurrentBalanceCarton\":0},{\"PartID\":55,\"PartName\":\"بست بزرگ 1-1/16\",\"StatusAfterID\":30,\"StatusName\":\"ارسال شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":-16},{\"PartID\":55,\"PartName\":\"بست بزرگ 1-1/16\",\"StatusAfterID\":11,\"StatusName\":\"بسته بندی شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":0},{\"PartID\":58,\"PartName\":\"بست بزرگ 1-3/4\",\"StatusAfterID\":17,\"StatusName\":\"آبکاری نشده\",\"CurrentBalanceKG\":433,\"CurrentBalanceCarton\":0},{\"PartID\":58,\"PartName\":\"بست بزرگ 1-3/4\",\"StatusAfterID\":30,\"StatusName\":\"ارسال شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":-6},{\"PartID\":58,\"PartName\":\"بست بزرگ 1-3/4\",\"StatusAfterID\":11,\"StatusName\":\"بسته بندی شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":8},{\"PartID\":62,\"PartName\":\"بست بزرگ 2-1/2\",\"StatusAfterID\":30,\"StatusName\":\"ارسال شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":-5},{\"PartID\":64,\"PartName\":\"بست بزرگ 3\",\"StatusAfterID\":2,\"StatusName\":\"آبکاری شده\",\"CurrentBalanceKG\":40,\"CurrentBalanceCarton\":0},{\"PartID\":48,\"PartName\":\"بست کوچک 1\",\"StatusAfterID\":30,\"StatusName\":\"ارسال شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":-6},{\"PartID\":44,\"PartName\":\"بست کوچک 1/2\",\"StatusAfterID\":null,\"StatusName\":\"-- بدون وضعیت --\",\"CurrentBalanceKG\":-45,\"CurrentBalanceCarton\":0},{\"PartID\":44,\"PartName\":\"بست کوچک 1/2\",\"StatusAfterID\":30,\"StatusName\":\"ارسال شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":-6},{\"PartID\":49,\"PartName\":\"بست کوچک 1016\",\"StatusAfterID\":30,\"StatusName\":\"ارسال شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":-4},{\"PartID\":49,\"PartName\":\"بست کوچک 1016\",\"StatusAfterID\":9,\"StatusName\":\"مونتاژ شده\",\"CurrentBalanceKG\":87,\"CurrentBalanceCarton\":0},{\"PartID\":50,\"PartName\":\"بست کوچک 1319\",\"StatusAfterID\":11,\"StatusName\":\"بسته بندی شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":5},{\"PartID\":17,\"PartName\":\"تسمه بزرگ بدون دنده 1-7/8\",\"StatusAfterID\":3,\"StatusName\":\"برش خورده\",\"CurrentBalanceKG\":-45,\"CurrentBalanceCarton\":0},{\"PartID\":18,\"PartName\":\"تسمه بزرگ بدون دنده 2\",\"StatusAfterID\":4,\"StatusName\":\"دنده شده\",\"CurrentBalanceKG\":40,\"CurrentBalanceCarton\":0},{\"PartID\":15,\"PartName\":\"تسمه بزرگ دنده شده 1-1/2\",\"StatusAfterID\":4,\"StatusName\":\"دنده شده\",\"CurrentBalanceKG\":-48,\"CurrentBalanceCarton\":0},{\"PartID\":7,\"PartName\":\"تسمه کوچک 1016\",\"StatusAfterID\":null,\"StatusName\":\"-- بدون وضعیت --\",\"CurrentBalanceKG\":-45.2,\"CurrentBalanceCarton\":0},{\"PartID\":7,\"PartName\":\"تسمه کوچک 1016\",\"StatusAfterID\":2,\"StatusName\":\"آبکاری شده\",\"CurrentBalanceKG\":-20.7,\"CurrentBalanceCarton\":0},{\"PartID\":9,\"PartName\":\"تسمه کوچک 1825\",\"StatusAfterID\":1,\"StatusName\":\"تسمه رول شده\",\"CurrentBalanceKG\":0,\"CurrentBalanceCarton\":0},{\"PartID\":3,\"PartName\":\"تسمه کوچک 5/8\",\"StatusAfterID\":null,\"StatusName\":\"-- بدون وضعیت --\",\"CurrentBalanceKG\":-46,\"CurrentBalanceCarton\":0},{\"PartID\":43,\"PartName\":\"پیچ کوچک دو سو چهارسو\",\"StatusAfterID\":2,\"StatusName\":\"آبکاری شده\",\"CurrentBalanceKG\":200,\"CurrentBalanceCarton\":0}]');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_machines`
--

CREATE TABLE `tbl_machines` (
  `MachineID` int(11) NOT NULL,
  `MachineName` varchar(255) NOT NULL,
  `MachineType` varchar(100) DEFAULT NULL,
  `Status` varchar(50) DEFAULT 'Active',
  `strokes_per_minute` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_machines`
--

INSERT INTO `tbl_machines` (`MachineID`, `MachineName`, `MachineType`, `Status`, `strokes_per_minute`) VALUES
(1, 'پرس 25 تن 1', 'پرس', 'Active', 70),
(2, 'پرس 16 تن', 'پرس', 'Active', NULL),
(3, 'پرس 25 تن 2', 'پرس', 'Active', 62),
(4, 'پرس 25 تن 3', 'پرس', 'Active', 60),
(5, 'پرس 35 تن 1', 'پرس', 'Active', 55),
(6, 'پرس 35 تن 2', 'پرس', 'Active', 55),
(7, 'پرس 60 تن', 'پرس', 'Active', 50),
(8, 'پرس 40 تن', 'پرس', 'Active', NULL),
(10, 'رزوه بزرگ', 'پیچ سازی', 'Active', NULL),
(11, 'کله زن بزرگ', 'پیچ سازی', 'Active', NULL),
(12, 'کله زن کوچک 1', 'پیچ سازی', 'Active', NULL),
(13, 'کله زن کوچک 2', 'پیچ سازی', 'Active', NULL),
(14, 'رزوه چونزو', 'پیچ سازی', 'Active', NULL),
(15, 'رزوه کوچک', 'پیچ سازی', 'Active', NULL),
(17, 'مونتاژ  1', 'مونتاژ', 'Active', 16),
(18, 'مونتاژ 7', 'مونتاژ بزرگ', 'Active', 11),
(19, 'مونتاژ 3', 'مونتاژ', 'Active', 16),
(20, 'مونتاژ 5', 'مونتاژ', 'Active', 16),
(21, 'مونتاژ 6', 'مونتاژ', 'Active', 16),
(22, 'مونتاژ 8', 'مونتاژ', 'Active', 16),
(23, 'مونتاژ 9', 'مونتاژ', 'Active', 16),
(24, 'مونتاژ 10', 'مونتاژ', 'Active', 16),
(25, 'مونتاژ 2', 'مونتاژ بزرگ', 'Active', 11),
(26, 'مونتاژ 4', 'مونتاژ بزرگ', 'Active', 11),
(27, 'رول کن شماره 1', 'رول کن', 'Active', NULL),
(28, 'رول کن شماره 2', 'رول کن', 'Active', NULL),
(29, 'رول کن شماره 3', 'رول کن', 'Active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_machine_current_setup`
--

CREATE TABLE `tbl_machine_current_setup` (
  `MachineID` int(11) NOT NULL,
  `CurrentMoldID` int(11) DEFAULT NULL,
  `SetupTimestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_machine_current_setup`
--

INSERT INTO `tbl_machine_current_setup` (`MachineID`, `CurrentMoldID`, `SetupTimestamp`) VALUES
(1, 10, '2025-10-19 14:09:47'),
(2, 5, '2025-10-19 14:09:47'),
(3, 12, '2025-10-19 14:09:47'),
(4, 16, '2025-10-19 14:09:47'),
(5, 23, '2025-10-19 14:09:47'),
(6, 2, '2025-10-19 14:09:47'),
(7, 14, '2025-10-19 14:09:47'),
(8, 9, '2025-10-19 14:09:47');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_machine_producible_families`
--

CREATE TABLE `tbl_machine_producible_families` (
  `MachineID` int(11) NOT NULL,
  `FamilyID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_machine_producible_families`
--

INSERT INTO `tbl_machine_producible_families` (`MachineID`, `FamilyID`) VALUES
(17, 9),
(18, 3),
(19, 9),
(20, 9),
(21, 9),
(22, 9),
(23, 9),
(24, 9),
(25, 3),
(26, 3),
(27, 11),
(27, 12),
(28, 11),
(28, 12),
(29, 11),
(29, 12);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_actions`
--

CREATE TABLE `tbl_maintenance_actions` (
  `ActionID` int(11) NOT NULL,
  `ActionDescription` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_maintenance_actions`
--

INSERT INTO `tbl_maintenance_actions` (`ActionID`, `ActionDescription`) VALUES
(3, 'تعویض سنبه'),
(2, 'تعویض ماتریس'),
(1, 'سنک زنی ماتریس');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_breakdown_cause_links`
--

CREATE TABLE `tbl_maintenance_breakdown_cause_links` (
  `BreakdownTypeID` int(11) NOT NULL,
  `CauseID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_maintenance_breakdown_cause_links`
--

INSERT INTO `tbl_maintenance_breakdown_cause_links` (`BreakdownTypeID`, `CauseID`) VALUES
(1, 1),
(1, 2),
(1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_breakdown_types`
--

CREATE TABLE `tbl_maintenance_breakdown_types` (
  `BreakdownTypeID` int(11) NOT NULL,
  `Description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_maintenance_breakdown_types`
--

INSERT INTO `tbl_maintenance_breakdown_types` (`BreakdownTypeID`, `Description`) VALUES
(1, 'پلیسه قطعه');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_causes`
--

CREATE TABLE `tbl_maintenance_causes` (
  `CauseID` int(11) NOT NULL,
  `CauseDescription` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_maintenance_causes`
--

INSERT INTO `tbl_maintenance_causes` (`CauseID`, `CauseDescription`) VALUES
(1, 'تیراژ تولید'),
(10, 'خوردگی فرم'),
(3, 'شکستن سنبه'),
(9, 'شکستن فرم'),
(2, 'شکستن ماتریس');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_cause_action_links`
--

CREATE TABLE `tbl_maintenance_cause_action_links` (
  `CauseID` int(11) NOT NULL,
  `ActionID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_maintenance_cause_action_links`
--

INSERT INTO `tbl_maintenance_cause_action_links` (`CauseID`, `ActionID`) VALUES
(1, 1),
(1, 2),
(2, 1),
(3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_reports`
--

CREATE TABLE `tbl_maintenance_reports` (
  `ReportID` int(11) NOT NULL,
  `ReportDate` datetime NOT NULL,
  `MoldID` int(11) NOT NULL,
  `RestartDate` datetime DEFAULT NULL,
  `RepairDurationMinutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_report_entries`
--

CREATE TABLE `tbl_maintenance_report_entries` (
  `EntryID` bigint(20) NOT NULL,
  `ReportID` int(11) NOT NULL,
  `BreakdownTypeID` int(11) NOT NULL,
  `CauseID` int(11) NOT NULL,
  `ActionID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_misc_categories`
--

CREATE TABLE `tbl_misc_categories` (
  `CategoryID` int(11) NOT NULL,
  `CategoryName` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='دسته‌بندی مواد متفرقه (کارتن، لیبل، شیمیایی...)';

--
-- Dumping data for table `tbl_misc_categories`
--

INSERT INTO `tbl_misc_categories` (`CategoryID`, `CategoryName`) VALUES
(4, 'شیر برقی'),
(3, 'فیتینگ'),
(2, 'مواد شیمیایی'),
(1, 'کارتن');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_misc_inventory_transactions`
--

CREATE TABLE `tbl_misc_inventory_transactions` (
  `TransactionID` bigint(20) NOT NULL,
  `TransactionDate` datetime NOT NULL DEFAULT current_timestamp(),
  `ItemID` int(11) NOT NULL,
  `Quantity` decimal(12,3) NOT NULL COMMENT 'مقدار (مثبت برای ورود/افزایش, منفی برای خروج/کاهش)',
  `TransactionTypeID` int(11) NOT NULL COMMENT 'FK to tbl_transaction_types',
  `OperatorEmployeeID` int(11) DEFAULT NULL COMMENT 'FK to tbl_employees',
  `Description` text DEFAULT NULL,
  `CreatedByUserID` int(11) DEFAULT NULL COMMENT 'FK to tbl_users'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_misc_items`
--

CREATE TABLE `tbl_misc_items` (
  `ItemID` int(11) NOT NULL,
  `ItemName` varchar(255) NOT NULL,
  `CategoryID` int(11) NOT NULL,
  `Unit` varchar(50) NOT NULL COMMENT 'واحد اندازه‌گیری',
  `UnitID` int(11) NOT NULL COMMENT 'FK به جدول واحدهای اندازه‌گیری (tbl_units)',
  `SafetyStock` decimal(10,3) DEFAULT NULL COMMENT 'موجودی اطمینان (اختیاری)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تعریف مواد متفرقه (کارتن، لیبل، گریس و...)';

--
-- Dumping data for table `tbl_misc_items`
--

INSERT INTO `tbl_misc_items` (`ItemID`, `ItemName`, `CategoryID`, `Unit`, `UnitID`, `SafetyStock`) VALUES
(1, 'سیانور سدیم', 2, '', 1, 50.000),
(2, 'سود', 2, '', 1, 50.000);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_misc_item_categories`
--

CREATE TABLE `tbl_misc_item_categories` (
  `CategoryID` int(11) NOT NULL,
  `CategoryName` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_misc_transactions`
--

CREATE TABLE `tbl_misc_transactions` (
  `TransactionID` bigint(20) NOT NULL,
  `TransactionDate` datetime NOT NULL DEFAULT current_timestamp(),
  `ItemID` int(11) NOT NULL,
  `TransactionTypeID` int(11) NOT NULL COMMENT 'FK به جدول انواع تراکنش (ورود، خروج، کسر، اضافه)',
  `Quantity` decimal(10,3) NOT NULL COMMENT 'مقدار (مثبت برای ورود/اضافه، منفی برای خروج/کسر)',
  `OperatorEmployeeID` int(11) DEFAULT NULL COMMENT 'FK به جدول کارمندان (عامل)',
  `Description` text DEFAULT NULL COMMENT 'توضیحات (مثلاً برای انبارگردانی)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تراکنش‌های انبار مواد متفرقه';

--
-- Dumping data for table `tbl_misc_transactions`
--

INSERT INTO `tbl_misc_transactions` (`TransactionID`, `TransactionDate`, `ItemID`, `TransactionTypeID`, `Quantity`, `OperatorEmployeeID`, `Description`) VALUES
(1, '2025-10-29 15:07:30', 1, 4, 50.000, 17, ''),
(2, '2025-10-23 15:07:59', 2, 2, -25.000, 7, ''),
(3, '2025-10-23 15:09:08', 2, 4, 50.000, 35, ''),
(4, '2025-10-23 15:56:08', 2, 1, 10.000, 15, ''),
(5, '2025-10-23 15:56:27', 1, 1, 10.000, 35, ''),
(6, '2025-10-25 15:56:46', 2, 4, 50.000, 35, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_molds`
--

CREATE TABLE `tbl_molds` (
  `MoldID` int(11) NOT NULL,
  `MoldName` varchar(255) NOT NULL,
  `Status` varchar(50) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_molds`
--

INSERT INTO `tbl_molds` (`MoldID`, `MoldName`, `Status`) VALUES
(2, 'قالب 3/4', 'Active'),
(3, 'قالب 1-1/4', 'Active'),
(4, 'قالب 1-3/4', 'Active'),
(5, 'قالب جنرال', 'Active'),
(6, 'قالب براکت', 'Active'),
(7, 'قالب محفظه کوچک قدیمی', 'Inactive'),
(8, 'قالب محفظه بزرگ قدیمی', 'Inactive'),
(9, 'قالب دنده جنرال', 'Active'),
(10, 'قالب پلوسی', 'Active'),
(11, 'قالب 5/8-1', 'Active'),
(12, 'قالب 5/8-2', 'Active'),
(13, 'قالب 1', 'Active'),
(14, 'قالب 2-1/2', 'Active'),
(15, 'قالب HA-0.45-1', 'Active'),
(16, 'قالب HA-0.45-2', 'Active'),
(17, 'قالب HB-0.5', 'Active'),
(18, 'قالب HB-0.7', 'Active'),
(19, 'قالب دنده جنرال هلالی', 'Active'),
(20, 'قالب پلوسی جدید', 'Active'),
(21, 'قالب 1016', 'Active'),
(22, 'قالب 1319', 'Active'),
(23, 'قالب 1825', 'Active'),
(24, 'سر باطری', 'Active'),
(25, 'HA-0.65', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_mold_machine_compatibility`
--

CREATE TABLE `tbl_mold_machine_compatibility` (
  `MoldID` int(11) NOT NULL,
  `MachineID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_mold_machine_compatibility`
--

INSERT INTO `tbl_mold_machine_compatibility` (`MoldID`, `MachineID`) VALUES
(2, 5),
(2, 6),
(3, 7),
(4, 7),
(5, 2),
(9, 8),
(10, 1),
(10, 3),
(10, 4),
(11, 1),
(11, 3),
(12, 1),
(12, 3),
(13, 5),
(13, 6),
(14, 7),
(15, 3),
(15, 4),
(16, 3),
(16, 4),
(17, 5),
(17, 6),
(18, 5),
(18, 6),
(19, 8),
(21, 1),
(21, 3),
(22, 5),
(22, 6),
(23, 5),
(23, 6),
(24, 1),
(24, 3),
(24, 4);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_mold_producible_parts`
--

CREATE TABLE `tbl_mold_producible_parts` (
  `MoldID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_mold_producible_parts`
--

INSERT INTO `tbl_mold_producible_parts` (`MoldID`, `PartID`) VALUES
(2, 4),
(3, 13),
(3, 14),
(4, 15),
(4, 16),
(5, 17),
(5, 18),
(5, 19),
(5, 21),
(5, 22),
(5, 23),
(5, 24),
(5, 25),
(5, 26),
(5, 27),
(5, 28),
(5, 29),
(5, 30),
(5, 31),
(5, 32),
(5, 33),
(9, 17),
(9, 18),
(9, 19),
(9, 21),
(9, 22),
(9, 23),
(9, 24),
(9, 25),
(9, 26),
(9, 27),
(9, 28),
(9, 29),
(9, 30),
(9, 31),
(9, 32),
(9, 33),
(11, 2),
(11, 3),
(11, 7),
(12, 2),
(12, 3),
(12, 7),
(13, 5),
(13, 6),
(13, 9),
(14, 20),
(15, 34),
(15, 35),
(16, 34),
(16, 35),
(17, 37),
(17, 38),
(18, 39),
(21, 10),
(22, 11),
(23, 12),
(25, 36);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_order_statuses`
--

CREATE TABLE `tbl_order_statuses` (
  `OrderStatusID` int(11) NOT NULL,
  `StatusName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_order_statuses`
--

INSERT INTO `tbl_order_statuses` (`OrderStatusID`, `StatusName`) VALUES
(2, 'تکمیل شده'),
(1, 'در جریان'),
(3, 'لغو شده');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_packaging_configs`
--

CREATE TABLE `tbl_packaging_configs` (
  `PackageConfigID` int(11) NOT NULL,
  `SizeID` int(11) NOT NULL,
  `ContainedQuantity` int(11) NOT NULL COMMENT 'تعداد محصول در هر کارتن'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_packaging_configs`
--

INSERT INTO `tbl_packaging_configs` (`PackageConfigID`, `SizeID`, `ContainedQuantity`) VALUES
(5, 54, 600),
(7, 63, 600),
(8, 48, 2000);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_packaging_log_details`
--

CREATE TABLE `tbl_packaging_log_details` (
  `PackagingDetailID` int(11) NOT NULL,
  `PackagingHeaderID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL,
  `CartonsPackaged` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد کارتن بسته‌بندی شده'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Packaging details (carton count per part)';

--
-- Dumping data for table `tbl_packaging_log_details`
--

INSERT INTO `tbl_packaging_log_details` (`PackagingDetailID`, `PackagingHeaderID`, `PartID`, `CartonsPackaged`) VALUES
(3, 1, 55, 16),
(4, 2, 55, 4),
(5, 2, 57, 5),
(6, 2, 56, 9);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_packaging_log_header`
--

CREATE TABLE `tbl_packaging_log_header` (
  `PackagingHeaderID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `AvailableTimeMinutes` int(11) DEFAULT NULL COMMENT 'زمان در دسترس روزانه (دقیقه)',
  `Description` text DEFAULT NULL COMMENT 'توضیحات کلی مربوط به روز'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Header for daily packaging logs';

--
-- Dumping data for table `tbl_packaging_log_header`
--

INSERT INTO `tbl_packaging_log_header` (`PackagingHeaderID`, `LogDate`, `AvailableTimeMinutes`, `Description`) VALUES
(1, '2025-10-21', 480, ''),
(2, '2025-10-23', 480, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_packaging_log_shifts`
--

CREATE TABLE `tbl_packaging_log_shifts` (
  `ShiftID` int(11) NOT NULL,
  `PackagingHeaderID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `StartTime` time DEFAULT NULL,
  `EndTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Personnel shifts for packaging';

--
-- Dumping data for table `tbl_packaging_log_shifts`
--

INSERT INTO `tbl_packaging_log_shifts` (`ShiftID`, `PackagingHeaderID`, `EmployeeID`, `StartTime`, `EndTime`) VALUES
(2, 1, 3, '07:30:00', '18:30:00'),
(3, 2, 34, '07:48:00', '20:48:00');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pallet_types`
--

CREATE TABLE `tbl_pallet_types` (
  `PalletTypeID` int(11) NOT NULL,
  `PalletName` varchar(100) NOT NULL COMMENT 'نام یا شناسه نوع پالت',
  `PalletWeightKG` decimal(10,3) DEFAULT NULL COMMENT 'وزن پالت بر حسب کیلوگرم'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='انواع پالت‌های مورد استفاده';

--
-- Dumping data for table `tbl_pallet_types`
--

INSERT INTO `tbl_pallet_types` (`PalletTypeID`, `PalletName`, `PalletWeightKG`) VALUES
(1, 'سبد زرد', 2.600);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_parts`
--

CREATE TABLE `tbl_parts` (
  `PartID` int(11) NOT NULL,
  `PartCode` varchar(50) NOT NULL,
  `PartName` varchar(150) NOT NULL,
  `Description` text DEFAULT NULL,
  `FamilyID` int(11) DEFAULT NULL,
  `SizeID` int(11) DEFAULT NULL COMMENT 'FK to tbl_part_sizes',
  `BarrelWeight_Solo_KG` decimal(10,2) DEFAULT NULL COMMENT 'ظرفیت بارل (کیلوگرم) اگر قطعه تنها باشد'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_parts`
--

INSERT INTO `tbl_parts` (`PartID`, `PartCode`, `PartName`, `Description`, `FamilyID`, `SizeID`, `BarrelWeight_Solo_KG`) VALUES
(2, 'F1S1-1760262002', 'تسمه کوچک 1/2', '', 1, NULL, NULL),
(3, 'F1S2-1760262003', 'تسمه کوچک 5/8', '', 1, NULL, NULL),
(4, 'F1S3-1760262004', 'تسمه کوچک 3/4', '', 1, NULL, NULL),
(5, 'F1S4-1760262005', 'تسمه کوچک 7/8', '', 1, NULL, NULL),
(6, 'F1S5-1760262006', 'تسمه کوچک 1', '', 1, NULL, NULL),
(7, 'F1S6-1760262007', 'تسمه کوچک 1016', '', 1, NULL, NULL),
(8, 'F1S7-1760262008', 'تسمه کوچک 1319', '', 1, NULL, NULL),
(9, 'F1S8-1760262009', 'تسمه کوچک 1825', '', 1, NULL, NULL),
(10, 'F1S9-1760262010', 'تسمه کوچک 1016-هلالی', '', 1, NULL, NULL),
(11, 'F1S10-1760262011', 'تسمه کوچک 1319- هلالی', '', 1, NULL, NULL),
(12, 'F1S11-1760262012', 'تسمه کوچک 1825-هلالی', '', 1, NULL, NULL),
(13, 'F11S12-1760262013', 'تسمه بزرگ دنده شده 1-1/16', '', 11, NULL, NULL),
(14, 'F11S13-1760262014', 'تسمه بزرگ دنده شده 1-1/4', '', 11, NULL, NULL),
(15, 'F11S14-1760262015', 'تسمه بزرگ دنده شده 1-1/2', '', 11, NULL, NULL),
(16, 'F11S15-1760262016', 'تسمه بزرگ دنده شده 1-3/4', '', 11, NULL, NULL),
(17, 'F12S16-1760262017', 'تسمه بزرگ بدون دنده 1-7/8', '', 12, NULL, NULL),
(18, 'F12S17-1760262018', 'تسمه بزرگ بدون دنده 2', '', 12, NULL, NULL),
(19, 'F12S18-1760262019', 'تسمه بزرگ بدون دنده 2-1/4', '', 12, NULL, NULL),
(20, 'F11S19-1760262020', 'تسمه بزرگ دنده شده 2-1/2', '', 11, NULL, NULL),
(21, 'F12S20-1760262021', 'تسمه بزرگ بدون دنده 2-7/8', '', 12, NULL, NULL),
(22, 'F12S21-1760262022', 'تسمه بزرگ بدون دنده 3', '', 12, NULL, NULL),
(23, 'F12S22-1760262023', 'تسمه بزرگ بدون دنده 3-1/4', '', 12, NULL, NULL),
(24, 'F12S23-1760262024', 'تسمه بزرگ بدون دنده 3-3/4', '', 12, NULL, NULL),
(25, 'F12S24-1760262025', 'تسمه بزرگ بدون دنده 4', '', 12, NULL, NULL),
(26, 'F12S25-1760262026', 'تسمه بزرگ بدون دنده 4-5/16', '', 12, NULL, NULL),
(27, 'F12S26-1760262027', 'تسمه بزرگ بدون دنده 5', '', 12, NULL, NULL),
(28, 'F12S27-1760262028', 'تسمه بزرگ بدون دنده 5-1/2', '', 12, NULL, NULL),
(29, 'F12S28-1760262029', 'تسمه بزرگ بدون دنده 6', '', 12, NULL, NULL),
(30, 'F12S29-1760262030', 'تسمه بزرگ بدون دنده 6-1/2', '', 12, NULL, NULL),
(31, 'F12S30-1760262031', 'تسمه بزرگ بدون دنده 7', '', 12, NULL, NULL),
(32, 'F12S31-1760262032', 'تسمه بزرگ بدون دنده 8', '', 12, NULL, NULL),
(33, 'F12S32-1760262033', 'تسمه بزرگ بدون دنده 8-1/2', '', 12, NULL, NULL),
(34, 'F2S33-1760262034', 'محفظه کوچک پایه کوتاه 0/6', '', 2, NULL, NULL),
(35, 'F2S34-1760262035', 'محفظه کوچک پایه کوتاه 0/7', '', 2, NULL, NULL),
(36, 'F2S35-1760262036', 'محفظه کوچک پایه بلند 0/7', '', 2, NULL, NULL),
(37, 'F10S36-1760262037', 'محفظه بزرگ پایه کوتاه 0/8', '', 10, NULL, NULL),
(38, 'F10S37-1760262038', 'محفظه بزرگ پایه کوتاه 1', '', 10, NULL, NULL),
(39, 'F10S38-1760262039', 'محفظه بزرگ پایه بلند 1', '', 10, NULL, NULL),
(40, 'F4S39-1760262040', 'پیچ بزرگ دوسو', '', 4, NULL, NULL),
(41, 'F4S40-1760262041', 'پیچ بزرگ دو سو چهارسو', '', 4, NULL, NULL),
(42, 'F6S41-1760262042', 'پیچ کوچک دوسو', '', 6, NULL, NULL),
(43, 'F6S42-1760262043', 'پیچ کوچک دو سو چهارسو', '', 6, NULL, NULL),
(44, 'F9S43-1760262044', 'بست کوچک 1/2', '', 9, NULL, NULL),
(45, 'F9S44-1760262045', 'بست کوچک 5/8', '', 9, NULL, NULL),
(46, 'F9S45-1760262046', 'بست کوچک 3/4', '', 9, NULL, NULL),
(47, 'F9S46-1760262047', 'بست کوچک 7/8', '', 9, NULL, NULL),
(48, 'F9S47-1760262048', 'بست کوچک 1', '', 9, NULL, NULL),
(49, 'F9S48-1760262049', 'بست کوچک 1016', '', 9, NULL, NULL),
(50, 'F9S49-1760262050', 'بست کوچک 1319', '', 9, NULL, NULL),
(51, 'F9S50-1760262051', 'بست کوچک 1825', '', 9, NULL, NULL),
(52, 'F9S51-1760262052', 'بست کوچک 1016-هلالی', '', 9, NULL, NULL),
(53, 'F9S52-1760262053', 'بست کوچک 1319- هلالی', '', 9, NULL, NULL),
(54, 'F9S53-1760262054', 'بست کوچک 1825-هلالی', '', 9, NULL, NULL),
(55, 'F3S54-1760262055', 'بست بزرگ 1-1/16', '', 3, NULL, 50.00),
(56, 'F3S55-1760262056', 'بست بزرگ 1-1/4', '', 3, NULL, NULL),
(57, 'F3S56-1760262057', 'بست بزرگ 1-1/2', '', 3, NULL, NULL),
(58, 'F3S57-1760262058', 'بست بزرگ 1-3/4', '', 3, NULL, 38.00),
(59, 'F3S58-1760262059', 'بست بزرگ 1-7/8', '', 3, NULL, NULL),
(60, 'F3S59-1760262060', 'بست بزرگ 2', '', 3, NULL, NULL),
(61, 'F3S60-1760262061', 'بست بزرگ 2-1/4', '', 3, NULL, NULL),
(62, 'F3S61-1760262062', 'بست بزرگ 2-1/2', '', 3, NULL, NULL),
(63, 'F3S62-1760262063', 'بست بزرگ 2-7/8', '', 3, NULL, NULL),
(64, 'F3S63-1760262064', 'بست بزرگ 3', '', 3, NULL, NULL),
(65, 'F3S64-1760262065', 'بست بزرگ 3-1/4', '', 3, NULL, NULL),
(66, 'F3S65-1760262066', 'بست بزرگ 3-3/4', '', 3, NULL, NULL),
(67, 'F3S66-1760262067', 'بست بزرگ 4', '', 3, NULL, NULL),
(68, 'F3S67-1760262068', 'بست بزرگ 4-5/16', '', 3, NULL, NULL),
(69, 'F3S68-1760262069', 'بست بزرگ 5', '', 3, NULL, NULL),
(70, 'F3S69-1760262070', 'بست بزرگ 5-1/2', '', 3, NULL, NULL),
(71, 'F3S70-1760262071', 'بست بزرگ 6', '', 3, NULL, NULL),
(72, 'F3S71-1760262072', 'بست بزرگ 6-1/2', '', 3, NULL, NULL),
(73, 'F3S72-1760262073', 'بست بزرگ 7', '', 3, NULL, NULL),
(74, 'F3S73-1760262074', 'بست بزرگ 8', '', 3, NULL, NULL),
(75, 'F3S74-1760262075', 'بست بزرگ 8-1/2', '', 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_part_families`
--

CREATE TABLE `tbl_part_families` (
  `FamilyID` int(11) NOT NULL,
  `FamilyName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_part_families`
--

INSERT INTO `tbl_part_families` (`FamilyID`, `FamilyName`) VALUES
(3, 'بست بزرگ'),
(9, 'بست کوچک'),
(12, 'تسمه بزرگ بدون دنده'),
(11, 'تسمه بزرگ دنده شده'),
(1, 'تسمه کوچک'),
(10, 'محفظه بزرگ'),
(2, 'محفظه کوچک'),
(13, 'پیچ  نیمه ساخته'),
(4, 'پیچ بزرگ'),
(6, 'پیچ کوچک');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_part_plating_groups`
--

CREATE TABLE `tbl_part_plating_groups` (
  `PartID` int(11) NOT NULL COMMENT 'FK to tbl_parts',
  `GroupID` int(11) NOT NULL COMMENT 'FK to tbl_plating_process_groups'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='اتصال قطعات به گروه‌های فرآیند آبکاری';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_part_raw_materials`
--

CREATE TABLE `tbl_part_raw_materials` (
  `PartBomID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (قطعه‌ای که تولید می‌شود)',
  `RawMaterialItemID` int(11) NOT NULL COMMENT 'FK to tbl_raw_items (ماده اولیه مورد نیاز)',
  `QuantityGram` decimal(10,3) NOT NULL COMMENT 'مقدار ماده اولیه (گرم) برای 1 عدد قطعه'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول BOM مواد اولیه (قطعه به ماده اولیه)';

--
-- Dumping data for table `tbl_part_raw_materials`
--

INSERT INTO `tbl_part_raw_materials` (`PartBomID`, `PartID`, `RawMaterialItemID`, `QuantityGram`) VALUES
(1, 43, 2, 3.300),
(2, 35, 1, 1.700),
(3, 9, 1, 1.700),
(4, 40, 4, 7.500),
(8, 7, 5, 1.000),
(9, 13, 3, 9.000);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_part_sizes`
--

CREATE TABLE `tbl_part_sizes` (
  `SizeID` int(11) NOT NULL,
  `FamilyID` int(11) NOT NULL,
  `SizeName` varchar(50) NOT NULL,
  `PlatingMustBeMixed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'آیا این سایز باید حتما ترکیبی آبکاری شود؟'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_part_sizes`
--

INSERT INTO `tbl_part_sizes` (`SizeID`, `FamilyID`, `SizeName`, `PlatingMustBeMixed`) VALUES
(1, 1, '1/2', 0),
(2, 1, '5/8', 0),
(3, 1, '3/4', 0),
(4, 1, '7/8', 0),
(5, 1, '1', 0),
(6, 1, '1016', 0),
(7, 1, '1319', 0),
(8, 1, '1825', 0),
(9, 1, '1016-هلالی', 0),
(10, 1, '1319- هلالی', 0),
(11, 1, '1825-هلالی', 0),
(12, 11, '1-1/16', 0),
(13, 11, '1-1/4', 0),
(14, 11, '1-1/2', 0),
(15, 11, '1-3/4', 0),
(16, 12, '1-7/8', 0),
(17, 12, '2', 0),
(18, 12, '2-1/4', 0),
(19, 11, '2-1/2', 0),
(20, 12, '2-7/8', 0),
(21, 12, '3', 0),
(22, 12, '3-1/4', 0),
(23, 12, '3-3/4', 0),
(24, 12, '4', 0),
(25, 12, '4-5/16', 0),
(26, 12, '5', 0),
(27, 12, '5-1/2', 0),
(28, 12, '6', 0),
(29, 12, '6-1/2', 0),
(30, 12, '7', 0),
(31, 12, '8', 0),
(32, 12, '8-1/2', 0),
(33, 2, 'پایه کوتاه 0/6', 0),
(34, 2, 'پایه کوتاه 0/7', 0),
(35, 2, 'پایه بلند 0/7', 0),
(36, 10, 'پایه کوتاه 0/8', 0),
(37, 10, 'پایه کوتاه 1', 0),
(38, 10, 'پایه بلند 1', 0),
(39, 4, 'دوسو', 0),
(40, 4, 'دو سو چهارسو', 0),
(41, 6, 'دوسو', 0),
(42, 6, 'دو سو چهارسو', 0),
(43, 9, '1/2', 0),
(44, 9, '5/8', 0),
(45, 9, '3/4', 0),
(46, 9, '7/8', 0),
(47, 9, '1', 0),
(48, 9, '1016', 0),
(49, 9, '1319', 0),
(50, 9, '1825', 0),
(51, 9, '1016-هلالی', 0),
(52, 9, '1319- هلالی', 0),
(53, 9, '1825-هلالی', 0),
(54, 3, '1-1/16', 0),
(55, 3, '1-1/4', 0),
(56, 3, '1-1/2', 0),
(57, 3, '1-3/4', 0),
(58, 3, '1-7/8', 0),
(59, 3, '2', 0),
(60, 3, '2-1/4', 0),
(61, 3, '2-1/2', 0),
(62, 3, '2-7/8', 0),
(63, 3, '3', 0),
(64, 3, '3-1/4', 0),
(65, 3, '3-3/4', 0),
(66, 3, '4', 0),
(67, 3, '4-5/16', 0),
(68, 3, '5', 1),
(69, 3, '5-1/2', 1),
(70, 3, '6', 1),
(71, 3, '6-1/2', 1),
(72, 3, '7', 1),
(73, 3, '8', 1),
(74, 3, '8-1/2', 1),
(75, 13, 'کوچک دو سو کله زده شده', 0),
(76, 13, 'بزرگ دو سو کله زده شده', 0),
(77, 13, 'کوچک دو سو  چهار سو کله زده شده', 0),
(78, 13, 'بزرگ دو سو  چهار سو کله زده شده', 0),
(79, 13, 'کوچک دو سو گلویی شده', 0),
(80, 13, 'بزرگ دو سو گلویی شده', 0),
(81, 13, 'کوچک دو سو  چهار سو گلویی شده', 0),
(82, 13, 'بزرگ دو سو  چهار سو گلویی خورده', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_part_statuses`
--

CREATE TABLE `tbl_part_statuses` (
  `StatusID` int(11) NOT NULL,
  `StatusName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Central table for part statuses';

--
-- Dumping data for table `tbl_part_statuses`
--

INSERT INTO `tbl_part_statuses` (`StatusID`, `StatusName`) VALUES
(2, 'آبکاری شده'),
(21, 'آبکاری ضعیف'),
(17, 'آبکاری نشده'),
(30, 'ارسال شده'),
(3, 'برش خورده'),
(11, 'بسته بندی شده'),
(1, 'تسمه رول شده'),
(4, 'دنده شده'),
(8, 'رزوه شده'),
(19, 'سفت  شده'),
(20, 'سورفکتانت خورده'),
(7, 'شستشو شده'),
(9, 'مونتاژ شده'),
(6, 'پرسکاری شده');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_part_weights`
--

CREATE TABLE `tbl_part_weights` (
  `PartWeightID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL COMMENT 'FK to tbl_parts',
  `WeightGR` decimal(10,3) NOT NULL COMMENT 'وزن قطعه بر حسب گرم',
  `EffectiveFrom` date NOT NULL COMMENT 'تاریخ شروع اعتبار این وزن',
  `EffectiveTo` date DEFAULT NULL COMMENT 'تاریخ پایان اعتبار این وزن (NULL یعنی هنوز فعال است)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تاریخچه وزن قطعات';

--
-- Dumping data for table `tbl_part_weights`
--

INSERT INTO `tbl_part_weights` (`PartWeightID`, `PartID`, `WeightGR`, `EffectiveFrom`, `EffectiveTo`) VALUES
(1, 2, 2.080, '2024-01-01', NULL),
(2, 3, 2.080, '2024-01-01', NULL),
(3, 4, 2.300, '2024-01-01', NULL),
(4, 5, 3.200, '2024-01-01', NULL),
(5, 6, 3.200, '2024-01-01', NULL),
(6, 7, 2.080, '2024-01-01', NULL),
(7, 8, 2.300, '2024-01-01', NULL),
(8, 9, 3.200, '2024-01-01', NULL),
(9, 13, 5.000, '2024-01-01', NULL),
(10, 14, 5.600, '2024-01-01', NULL),
(11, 15, 7.300, '2024-01-01', NULL),
(12, 16, 8.200, '2024-01-01', NULL),
(13, 17, 9.500, '2024-01-01', NULL),
(14, 18, 10.400, '2024-01-01', NULL),
(15, 19, 11.600, '2024-01-01', NULL),
(16, 20, 11.900, '2024-01-01', NULL),
(17, 21, 14.700, '2024-01-01', NULL),
(18, 22, 15.700, '2024-01-01', NULL),
(19, 23, 16.800, '2024-01-01', NULL),
(20, 24, 18.800, '2024-01-01', NULL),
(21, 25, 26.500, '2024-01-01', NULL),
(22, 26, 22.200, '2024-01-01', NULL),
(23, 27, 27.500, '2024-01-01', NULL),
(24, 28, 29.900, '2024-01-01', NULL),
(25, 29, 32.600, '2024-01-01', NULL),
(26, 30, 36.200, '2024-01-01', NULL),
(27, 31, 37.400, '2024-01-01', NULL),
(28, 32, 46.300, '2024-01-01', NULL),
(29, 34, 1.200, '2024-01-01', NULL),
(30, 35, 1.400, '2024-01-01', NULL),
(31, 36, 1.400, '2024-01-01', NULL),
(32, 37, 3.300, '2024-01-01', NULL),
(33, 38, 4.100, '2024-01-01', NULL),
(34, 39, 4.100, '2024-01-01', NULL),
(35, 40, 7.300, '2024-01-01', NULL),
(36, 41, 7.300, '2024-01-01', NULL),
(37, 42, 3.300, '2024-01-01', NULL),
(38, 43, 3.300, '2024-01-01', NULL),
(39, 44, 5.600, '2024-01-01', NULL),
(40, 45, 5.600, '2024-01-01', NULL),
(41, 46, 5.900, '2024-01-01', NULL),
(42, 47, 6.800, '2024-01-01', NULL),
(43, 48, 6.800, '2024-01-01', NULL),
(44, 49, 5.600, '2024-01-01', NULL),
(45, 50, 5.900, '2024-01-01', NULL),
(46, 51, 6.800, '2024-01-01', NULL),
(47, 55, 15.600, '2024-01-01', NULL),
(48, 56, 16.200, '2024-01-01', NULL),
(49, 57, 17.900, '2024-01-01', NULL),
(50, 58, 18.800, '2024-01-01', NULL),
(51, 59, 20.100, '2024-01-01', NULL),
(52, 60, 21.000, '2024-01-01', NULL),
(53, 61, 22.200, '2024-01-01', NULL),
(54, 62, 23.300, '2024-01-01', NULL),
(55, 63, 25.300, '2024-01-01', NULL),
(56, 64, 25.500, '2024-01-01', NULL),
(57, 65, 28.100, '2024-01-01', NULL),
(58, 66, 31.400, '2024-01-01', NULL),
(59, 67, 37.100, '2024-01-01', NULL),
(60, 68, 32.800, '2024-01-01', NULL),
(61, 69, 38.100, '2024-01-01', NULL),
(62, 70, 40.500, '2024-01-01', NULL),
(63, 71, 43.200, '2024-01-01', NULL),
(64, 72, 46.800, '2024-01-01', NULL),
(65, 73, 48.000, '2024-01-01', NULL),
(66, 74, 56.900, '2024-01-01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_batch_compatibility`
--

CREATE TABLE `tbl_planning_batch_compatibility` (
  `CompatibilityID` int(11) NOT NULL,
  `PrimaryPartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (قطعه اصلی)',
  `CompatiblePartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (قطعه‌ای که میتواند اضافه شود)',
  `PrimaryPartWeight_KG` decimal(10,2) DEFAULT NULL COMMENT 'وزن قطعه اصلی در این ترکیب',
  `CompatiblePartWeight_KG` decimal(10,2) DEFAULT NULL COMMENT 'وزن قطعه سازگار در این ترکیب'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تعریف اینکه کدام قطعات می‌توانند با هم در یک بچ باشند';

--
-- Dumping data for table `tbl_planning_batch_compatibility`
--

INSERT INTO `tbl_planning_batch_compatibility` (`CompatibilityID`, `PrimaryPartID`, `CompatiblePartID`, `PrimaryPartWeight_KG`, `CompatiblePartWeight_KG`) VALUES
(9, 55, 69, NULL, NULL),
(10, 69, 55, NULL, NULL),
(11, 55, 70, NULL, NULL),
(12, 70, 55, NULL, NULL),
(13, 55, 71, NULL, NULL),
(14, 71, 55, NULL, NULL),
(15, 55, 72, NULL, NULL),
(16, 72, 55, NULL, NULL),
(17, 55, 73, NULL, NULL),
(18, 73, 55, NULL, NULL),
(19, 55, 74, NULL, NULL),
(20, 74, 55, NULL, NULL),
(21, 55, 75, NULL, NULL),
(22, 75, 55, NULL, NULL),
(23, 58, 74, 38.00, 2.00),
(24, 74, 58, 2.00, 38.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_capacity_override`
--

CREATE TABLE `tbl_planning_capacity_override` (
  `OverrideID` int(11) NOT NULL,
  `PlanningDate` date NOT NULL,
  `StationID` int(11) NOT NULL,
  `SuggestedCapacity` decimal(12,2) DEFAULT NULL COMMENT 'ظرفیت محاسبه شده توسط سیستم',
  `FinalCapacity` decimal(12,2) NOT NULL COMMENT 'ظرفیت نهایی تایید شده توسط برنامه‌ریز',
  `CapacityUnit` varchar(20) NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `LastUpdatedBy` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_mrp_results`
--

CREATE TABLE `tbl_planning_mrp_results` (
  `ResultID` int(11) NOT NULL,
  `RunID` int(11) NOT NULL,
  `ItemType` enum('محصول نهایی','قطعه','ماده اولیه') NOT NULL,
  `ItemID` varchar(50) NOT NULL COMMENT 'PartID or RawMaterialID',
  `ItemName` varchar(255) NOT NULL,
  `ItemStatusID` int(11) DEFAULT NULL,
  `GrossRequirement` decimal(12,2) NOT NULL,
  `AvailableSupply` decimal(12,2) NOT NULL,
  `NetRequirement` decimal(12,2) NOT NULL,
  `Unit` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_mrp_run`
--

CREATE TABLE `tbl_planning_mrp_run` (
  `RunID` int(11) NOT NULL,
  `RunDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `RunByUserID` int(11) DEFAULT NULL,
  `SelectedOrderIDs` text DEFAULT NULL,
  `Status` enum('Pending','Completed') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_part_to_group`
--

CREATE TABLE `tbl_planning_part_to_group` (
  `PartID` int(11) NOT NULL COMMENT 'FK to tbl_parts',
  `GroupID` int(11) NOT NULL COMMENT 'FK to tbl_planning_plating_groups'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='اتصال قطعات به گروه‌های آبکاری مربوطه';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_plating_groups`
--

CREATE TABLE `tbl_planning_plating_groups` (
  `GroupID` int(11) NOT NULL,
  `GroupName` varchar(255) NOT NULL COMMENT 'نام گروه (مثلا: روی-سیانوری، نیکل)',
  `SetupTimeMinutes` int(11) DEFAULT 0 COMMENT 'زمان لازم برای ستاپ/تغییر به این گروه (دقیقه)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='گروه‌های آبکاری برای مدیریت بچینگ و زمان ستاپ';

--
-- Dumping data for table `tbl_planning_plating_groups`
--

INSERT INTO `tbl_planning_plating_groups` (`GroupID`, `GroupName`, `SetupTimeMinutes`) VALUES
(1, 'تسمه 5/8', 240);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_station_capacity_rules`
--

CREATE TABLE `tbl_planning_station_capacity_rules` (
  `RuleID` int(11) NOT NULL,
  `StationID` int(11) NOT NULL,
  `MachineID` int(11) DEFAULT NULL,
  `PartID` int(11) DEFAULT NULL,
  `CalculationMethod` enum('FixedAmount','ManHours','OEE','PlatingManHours','AssemblySmall','AssemblyLarge','Rolling','Packaging','Gearing') NOT NULL,
  `StandardValue` decimal(10,2) DEFAULT NULL COMMENT 'مقدار ثابت (برای متد FixedAmount) یا مقدار پیش‌فرض',
  `FinalCapacity` decimal(12,2) DEFAULT NULL COMMENT 'ظرفیت نهایی تایید شده توسط کاربر',
  `CapacityUnit` varchar(20) NOT NULL COMMENT 'واحد ظرفیت (KG/Day, Pieces/Day, ManHours)',
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `tbl_planning_station_capacity_rules`
--

INSERT INTO `tbl_planning_station_capacity_rules` (`RuleID`, `StationID`, `MachineID`, `PartID`, `CalculationMethod`, `StandardValue`, `FinalCapacity`, `CapacityUnit`, `Notes`) VALUES
(1, 12, NULL, NULL, 'AssemblySmall', 70000.00, NULL, 'Pieces/Day', ''),
(3, 3, NULL, 55, 'Gearing', NULL, 30.00, 'KG/Day', NULL),
(4, 2, 2, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(5, 2, 1, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(6, 2, 3, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(7, 2, 4, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(8, 2, 5, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(9, 2, 6, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(10, 2, 8, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(11, 2, 7, NULL, 'OEE', 80.00, 480.00, 'Pieces/Day', NULL),
(12, 10, NULL, NULL, 'Packaging', NULL, 20.00, 'Cartons/Day', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_vibration_incompatibility`
--

CREATE TABLE `tbl_planning_vibration_incompatibility` (
  `PrimaryPartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (قطعه اصلی)',
  `IncompatiblePartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (قطعه ناسازگار)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `tbl_planning_vibration_incompatibility`
--

INSERT INTO `tbl_planning_vibration_incompatibility` (`PrimaryPartID`, `IncompatiblePartID`) VALUES
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(3, 2),
(3, 4),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 9),
(3, 10),
(3, 11),
(3, 12),
(4, 2),
(4, 3),
(4, 5),
(4, 6),
(4, 7),
(4, 8),
(4, 9),
(4, 10),
(4, 11),
(4, 12),
(5, 2),
(5, 3),
(5, 4),
(5, 6),
(5, 7),
(5, 8),
(5, 9),
(5, 10),
(5, 11),
(5, 12),
(6, 2),
(6, 3),
(6, 4),
(6, 5),
(6, 7),
(6, 8),
(6, 9),
(6, 10),
(6, 11),
(6, 12),
(7, 2),
(7, 3),
(7, 4),
(7, 5),
(7, 6),
(7, 8),
(7, 9),
(7, 10),
(7, 11),
(7, 12),
(8, 2),
(8, 3),
(8, 4),
(8, 5),
(8, 6),
(8, 7),
(8, 9),
(8, 10),
(8, 11),
(8, 12),
(9, 2),
(9, 3),
(9, 4),
(9, 5),
(9, 6),
(9, 7),
(9, 8),
(9, 10),
(9, 11),
(9, 12),
(10, 2),
(10, 3),
(10, 4),
(10, 5),
(10, 6),
(10, 7),
(10, 8),
(10, 9),
(10, 11),
(10, 12),
(11, 2),
(11, 3),
(11, 4),
(11, 5),
(11, 6),
(11, 7),
(11, 8),
(11, 9),
(11, 10),
(11, 12),
(12, 2),
(12, 3),
(12, 4),
(12, 5),
(12, 6),
(12, 7),
(12, 8),
(12, 9),
(12, 10),
(12, 11);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_planning_work_orders`
--

CREATE TABLE `tbl_planning_work_orders` (
  `WorkOrderID` int(11) NOT NULL,
  `RunID` int(11) NOT NULL,
  `StationID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL,
  `RequiredStatusID` int(11) NOT NULL COMMENT 'وضعیت مورد نیاز ورودی',
  `TargetStatusID` int(11) NOT NULL COMMENT 'وضعیت هدف خروجی',
  `Quantity` decimal(12,2) NOT NULL,
  `Unit` varchar(10) NOT NULL,
  `DueDate` date NOT NULL,
  `Priority` int(11) NOT NULL DEFAULT 10,
  `Status` enum('Generated','InProgress','Completed') NOT NULL DEFAULT 'Generated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_compatibility`
--

CREATE TABLE `tbl_plating_compatibility` (
  `PrimaryPartID` int(11) NOT NULL COMMENT 'FK to tbl_parts',
  `CompatiblePartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (قطعه سازگار)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='سازگاری قطعات برای بچینگ آبکاری';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_events_log`
--

CREATE TABLE `tbl_plating_events_log` (
  `EventID` int(11) NOT NULL,
  `EventDate` datetime NOT NULL DEFAULT current_timestamp(),
  `Description` text NOT NULL,
  `EmployeeID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_log_additions`
--

CREATE TABLE `tbl_plating_log_additions` (
  `AdditionID` int(11) NOT NULL,
  `PlatingHeaderID` int(11) NOT NULL,
  `VatID` int(11) DEFAULT NULL,
  `VatName` varchar(50) NOT NULL COMMENT 'نام وان مثل وان ۱',
  `ChemicalID` int(11) NOT NULL,
  `Quantity` decimal(10,3) NOT NULL,
  `Unit` varchar(20) NOT NULL COMMENT 'e.g., kg, liter, cc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_plating_log_additions`
--

INSERT INTO `tbl_plating_log_additions` (`AdditionID`, `PlatingHeaderID`, `VatID`, `VatName`, `ChemicalID`, `Quantity`, `Unit`) VALUES
(9, 10, 1, '', 1, 10.000, 'KG'),
(10, 10, 1, '', 3, 10.000, 'KG'),
(11, 10, 2, '', 1, 10.000, 'KG'),
(12, 10, 2, '', 3, 10.000, 'KG');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_log_details`
--

CREATE TABLE `tbl_plating_log_details` (
  `PlatingDetailID` int(11) NOT NULL,
  `PlatingHeaderID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL,
  `WashedKG` decimal(10,2) DEFAULT 0.00,
  `PlatedKG` decimal(10,2) DEFAULT 0.00,
  `ReworkedKG` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_plating_log_details`
--

INSERT INTO `tbl_plating_log_details` (`PlatingDetailID`, `PlatingHeaderID`, `PartID`, `WashedKG`, `PlatedKG`, `ReworkedKG`) VALUES
(16, 6, 8, 0.00, 114.00, 0.00),
(17, 6, 4, 0.00, 98.00, 0.00),
(18, 6, 35, 0.00, 306.00, 0.00),
(19, 6, 65, 0.00, 320.00, 0.00),
(31, 10, 7, 0.00, 40.00, 0.00),
(32, 10, 8, 0.00, 130.00, 0.00),
(33, 10, 66, 0.00, 78.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_log_header`
--

CREATE TABLE `tbl_plating_log_header` (
  `PlatingHeaderID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `NumberOfBarrels` int(11) DEFAULT NULL COMMENT 'تعداد بارل کار شده در روز',
  `Description` text DEFAULT NULL COMMENT 'توضیحات کلی روز'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_plating_log_header`
--

INSERT INTO `tbl_plating_log_header` (`PlatingHeaderID`, `LogDate`, `NumberOfBarrels`, `Description`) VALUES
(6, '2025-10-15', 24, ''),
(10, '2025-10-18', 10, 'شروع آبکاری از ساعت 11 به دلیل گرم نبودن چربی گیر');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_log_shifts`
--

CREATE TABLE `tbl_plating_log_shifts` (
  `ShiftID` int(11) NOT NULL,
  `PlatingHeaderID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `StartTime` time DEFAULT NULL,
  `EndTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_plating_log_shifts`
--

INSERT INTO `tbl_plating_log_shifts` (`ShiftID`, `PlatingHeaderID`, `EmployeeID`, `StartTime`, `EndTime`) VALUES
(7, 6, 20, '18:30:00', '21:00:00'),
(8, 6, 21, '07:30:00', '17:30:00'),
(9, 6, 9, '07:30:00', '21:30:00'),
(14, 10, 9, '09:20:00', '18:20:00'),
(15, 10, 21, '09:20:00', '18:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_process_groups`
--

CREATE TABLE `tbl_plating_process_groups` (
  `GroupID` int(11) NOT NULL,
  `GroupName` varchar(150) NOT NULL COMMENT 'نام گروه فرآیند (مثلا: روی-سیانوری براقی A)',
  `ChangeoverTimeMinutes` int(11) NOT NULL DEFAULT 0 COMMENT 'زمان تعویض (دقیقه) برای ستاپ این فرآیند'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='گروه‌های فرآیندی آبکاری برای کمپین';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_vats`
--

CREATE TABLE `tbl_plating_vats` (
  `VatID` int(11) NOT NULL,
  `VatName` varchar(50) NOT NULL COMMENT 'e.g., وان ۱',
  `VolumeLiters` int(11) DEFAULT NULL COMMENT 'حجم وان به لیتر',
  `IsActive` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_plating_vats`
--

INSERT INTO `tbl_plating_vats` (`VatID`, `VatName`, `VolumeLiters`, `IsActive`) VALUES
(1, 'وان ۱', 5000, 1),
(2, 'وان ۲', 5000, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_plating_vat_analysis`
--

CREATE TABLE `tbl_plating_vat_analysis` (
  `AnalysisID` int(11) NOT NULL,
  `AnalysisDate` date NOT NULL,
  `VatID` int(11) NOT NULL,
  `Cyanide_gL` decimal(6,2) DEFAULT NULL COMMENT 'سیانور (گرم بر لیتر)',
  `CausticSoda_gL` decimal(6,2) DEFAULT NULL COMMENT 'سود سوزآور (گرم بر لیتر)',
  `Zinc_gL` decimal(6,2) DEFAULT NULL COMMENT 'روی (گرم بر لیتر)',
  `AnalysisBy` varchar(100) DEFAULT NULL COMMENT 'نام آنالیز کننده',
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_plating_vat_analysis`
--

INSERT INTO `tbl_plating_vat_analysis` (`AnalysisID`, `AnalysisDate`, `VatID`, `Cyanide_gL`, `CausticSoda_gL`, `Zinc_gL`, `AnalysisBy`, `Notes`) VALUES
(2, '2025-09-21', 1, 45.00, 90.00, 25.00, '', ''),
(3, '2025-09-21', 2, 46.00, 85.00, 12.00, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_priorities`
--

CREATE TABLE `tbl_priorities` (
  `PriorityID` int(11) NOT NULL,
  `PriorityName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_priorities`
--

INSERT INTO `tbl_priorities` (`PriorityID`, `PriorityName`) VALUES
(4, 'بحرانی'),
(3, 'زیاد'),
(2, 'متوسط'),
(1, 'کم');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_processes`
--

CREATE TABLE `tbl_processes` (
  `ProcessID` int(11) NOT NULL,
  `ProcessName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_processes`
--

INSERT INTO `tbl_processes` (`ProcessID`, `ProcessName`) VALUES
(1, 'محلول نرم گننده');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_process_weight_changes`
--

CREATE TABLE `tbl_process_weight_changes` (
  `ProcessWeightID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL,
  `FromStationID` int(11) NOT NULL,
  `ToStationID` int(11) NOT NULL,
  `WeightChangePercent` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'درصد تغییر وزن (مثبت یا منفی)',
  `EffectiveFrom` date NOT NULL,
  `EffectiveTo` date DEFAULT NULL COMMENT 'NULL means currently active',
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تغییرات وزن اختصاصی قطعات در فرآیندهای خاص بر اساس تاریخ';

--
-- Dumping data for table `tbl_process_weight_changes`
--

INSERT INTO `tbl_process_weight_changes` (`ProcessWeightID`, `PartID`, `FromStationID`, `ToStationID`, `WeightChangePercent`, `EffectiveFrom`, `EffectiveTo`, `Notes`) VALUES
(1, 17, 3, 8, 3.00, '2022-10-23', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prod_daily_log_details`
--

CREATE TABLE `tbl_prod_daily_log_details` (
  `DetailID` int(11) NOT NULL,
  `HeaderID` int(11) NOT NULL,
  `MachineID` int(11) NOT NULL,
  `MoldID` int(11) DEFAULT NULL,
  `PartID` int(11) NOT NULL,
  `ProductionKG` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prod_daily_log_details`
--

INSERT INTO `tbl_prod_daily_log_details` (`DetailID`, `HeaderID`, `MachineID`, `MoldID`, `PartID`, `ProductionKG`) VALUES
(8, 4, 1, 11, 7, 70.00),
(9, 4, 2, 5, 25, 20.00),
(10, 4, 3, 15, 34, 30.00),
(11, 4, 4, 16, 35, 40.00),
(12, 4, 5, 13, 6, 40.00),
(13, 4, 6, 17, 38, 30.00),
(14, 4, 7, 3, 13, 40.00),
(15, 5, 1, 12, 2, 65.00),
(16, 5, 2, 5, 21, 63.00),
(17, 5, 3, 16, 2, 54.00),
(18, 5, 4, 11, 34, 45.00),
(19, 5, 5, 2, 38, 52.00),
(20, 5, 6, 2, 4, 60.00),
(21, 5, 7, 14, 16, 31.00),
(22, 6, 1, 11, 2, 30.00),
(23, 6, 2, 6, 20, 37.00),
(24, 6, 3, 12, 7, 55.00),
(25, 6, 4, 11, 3, 49.00),
(26, 6, 5, 13, 9, 59.00),
(27, 6, 6, 17, 6, 45.00),
(28, 6, 7, 4, 20, 43.00),
(29, 7, 1, 12, 2, 42.00),
(30, 7, 2, 7, 21, 49.00),
(31, 7, 3, 12, 34, 62.00),
(32, 7, 4, 11, 7, 57.00),
(33, 7, 5, 13, 9, 56.00),
(34, 7, 6, 18, 9, 47.00),
(35, 7, 7, 14, 14, 52.00),
(36, 8, 1, 12, 3, 47.00),
(37, 8, 2, 8, 23, 54.00),
(38, 8, 3, 11, 35, 62.00),
(39, 8, 4, 15, 7, 30.00),
(40, 8, 5, 13, 4, 37.00),
(41, 8, 6, 13, 9, 30.00),
(42, 8, 7, 3, 16, 60.00),
(43, 9, 1, 11, 2, 38.00),
(44, 9, 2, 9, 31, 58.00),
(45, 9, 3, 12, 34, 64.00),
(46, 9, 4, 12, 35, 52.00),
(47, 9, 5, 13, 38, 61.00),
(48, 9, 6, 17, 37, 64.00),
(49, 9, 7, 4, 13, 59.00),
(50, 10, 1, 11, 3, 53.00),
(51, 10, 2, 10, 26, 36.00),
(52, 10, 3, 11, 34, 60.00),
(53, 10, 4, 12, 7, 52.00),
(54, 10, 5, 13, 39, 31.00),
(55, 10, 6, 2, 37, 62.00),
(56, 10, 7, 3, 13, 36.00),
(57, 11, 1, 12, 7, 51.00),
(58, 11, 2, 11, 25, 33.00),
(59, 11, 3, 15, 2, 60.00),
(60, 11, 4, 11, 35, 31.00),
(61, 11, 5, 2, 9, 38.00),
(62, 11, 6, 2, 9, 30.00),
(63, 11, 7, 14, 20, 47.00),
(64, 12, 1, 12, 2, 55.00),
(65, 12, 2, 12, 32, 55.00),
(66, 12, 3, 15, 2, 37.00),
(67, 12, 4, 16, 3, 45.00),
(68, 12, 5, 13, 9, 47.00),
(69, 12, 6, 18, 39, 56.00),
(70, 12, 7, 14, 15, 54.00),
(71, 13, 1, 11, 7, 56.00),
(72, 13, 2, 13, 27, 50.00),
(73, 13, 3, 16, 7, 39.00),
(74, 13, 4, 15, 34, 49.00),
(75, 13, 5, 2, 38, 59.00),
(76, 13, 6, 13, 38, 58.00),
(77, 13, 7, 3, 14, 56.00),
(78, 14, 1, 11, 7, 57.00),
(79, 14, 2, 14, 29, 48.00),
(80, 14, 3, 15, 35, 63.00),
(81, 14, 4, 16, 2, 44.00),
(82, 14, 5, 17, 39, 40.00),
(83, 14, 6, 17, 38, 32.00),
(84, 14, 7, 14, 14, 42.00),
(85, 15, 1, 11, 3, 33.00),
(86, 15, 2, 15, 32, 55.00),
(87, 15, 3, 11, 3, 64.00),
(88, 15, 4, 16, 34, 42.00),
(89, 15, 5, 2, 6, 42.00),
(90, 15, 6, 18, 6, 41.00),
(91, 15, 7, 4, 14, 42.00),
(92, 16, 1, 11, 3, 34.00),
(93, 16, 2, 16, 21, 43.00),
(94, 16, 3, 15, 35, 54.00),
(95, 16, 4, 16, 2, 34.00),
(96, 16, 5, 2, 39, 60.00),
(97, 16, 6, 17, 39, 64.00),
(98, 16, 7, 14, 20, 46.00),
(99, 17, 1, 12, 7, 36.00),
(100, 17, 2, 17, 23, 51.00),
(101, 17, 3, 16, 2, 63.00),
(102, 17, 4, 11, 35, 36.00),
(103, 17, 5, 18, 37, 43.00),
(104, 17, 6, 17, 9, 43.00),
(105, 17, 7, 3, 14, 40.00),
(106, 18, 1, 11, 7, 51.00),
(107, 18, 2, 18, 25, 63.00),
(108, 18, 3, 12, 7, 31.00),
(109, 18, 4, 12, 3, 38.00),
(110, 18, 5, 13, 9, 31.00),
(111, 18, 6, 18, 9, 54.00),
(112, 18, 7, 3, 20, 35.00),
(113, 19, 1, 11, 3, 31.00),
(114, 19, 2, 19, 25, 46.00),
(115, 19, 3, 11, 2, 31.00),
(116, 19, 4, 16, 35, 37.00),
(117, 19, 5, 17, 4, 47.00),
(118, 19, 6, 2, 6, 60.00),
(119, 19, 7, 3, 20, 35.00),
(120, 20, 1, 12, 3, 51.00),
(121, 20, 2, 20, 28, 61.00),
(122, 20, 3, 11, 3, 45.00),
(123, 20, 4, 16, 35, 50.00),
(124, 20, 5, 18, 38, 63.00),
(125, 20, 6, 17, 39, 55.00),
(126, 20, 7, 4, 15, 62.00),
(127, 21, 1, 12, 7, 58.00),
(128, 21, 2, 21, 24, 32.00),
(129, 21, 3, 15, 7, 42.00),
(130, 21, 4, 16, 3, 46.00),
(131, 21, 5, 17, 39, 38.00),
(132, 21, 6, 17, 37, 50.00),
(133, 21, 7, 4, 15, 64.00),
(134, 22, 1, 11, 2, 36.00),
(135, 22, 2, 22, 26, 53.00),
(136, 22, 3, 16, 34, 36.00),
(137, 22, 4, 11, 7, 53.00),
(138, 22, 5, 18, 37, 37.00),
(139, 22, 6, 13, 6, 34.00),
(140, 22, 7, 4, 15, 57.00),
(141, 23, 1, 11, 7, 55.00),
(142, 23, 2, 23, 18, 53.00),
(143, 23, 3, 12, 2, 38.00),
(144, 23, 4, 12, 2, 42.00),
(145, 23, 5, 2, 6, 39.00),
(146, 23, 6, 13, 4, 32.00),
(147, 23, 7, 3, 13, 36.00),
(148, 24, 1, 12, 3, 44.00),
(149, 24, 2, 24, 28, 65.00),
(150, 24, 3, 11, 35, 55.00),
(151, 24, 4, 12, 35, 39.00),
(152, 24, 5, 18, 4, 49.00),
(153, 24, 6, 17, 9, 46.00),
(154, 24, 7, 14, 13, 34.00),
(155, 25, 1, 12, 2, 35.00),
(156, 25, 2, 25, 22, 49.00),
(157, 25, 3, 12, 7, 30.00),
(158, 25, 4, 15, 2, 40.00),
(159, 25, 5, 13, 37, 49.00),
(160, 25, 6, 17, 4, 58.00),
(161, 25, 7, 4, 14, 37.00),
(162, 26, 1, 11, 7, 41.00),
(164, 26, 3, 16, 2, 31.00),
(165, 26, 4, 12, 34, 56.00),
(166, 26, 5, 2, 37, 59.00),
(167, 26, 6, 17, 37, 35.00),
(168, 26, 7, 3, 14, 51.00),
(169, 27, 1, 12, 7, 57.00),
(171, 27, 3, 11, 3, 51.00),
(172, 27, 4, 15, 35, 32.00),
(173, 27, 5, 17, 9, 55.00),
(174, 27, 6, 2, 38, 52.00),
(175, 27, 7, 3, 14, 57.00),
(176, 28, 1, 12, 2, 42.00),
(178, 28, 3, 11, 2, 40.00),
(179, 28, 4, 11, 7, 30.00),
(180, 28, 5, 2, 38, 55.00),
(181, 28, 6, 18, 6, 32.00),
(182, 28, 7, 3, 13, 31.00),
(183, 29, 1, 11, 7, 47.00),
(185, 29, 3, 15, 34, 44.00),
(186, 29, 4, 15, 34, 51.00),
(187, 29, 5, 18, 4, 40.00),
(188, 29, 6, 13, 37, 37.00),
(189, 29, 7, 14, 20, 32.00),
(190, 30, 1, 12, 7, 37.00),
(192, 30, 3, 15, 35, 38.00),
(193, 30, 4, 12, 7, 36.00),
(194, 30, 5, 18, 9, 65.00),
(195, 30, 6, 17, 6, 52.00),
(196, 30, 7, 3, 14, 55.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prod_daily_log_header`
--

CREATE TABLE `tbl_prod_daily_log_header` (
  `HeaderID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `DepartmentID` int(11) NOT NULL,
  `MachineType` varchar(100) NOT NULL,
  `ManHours` decimal(10,2) NOT NULL,
  `AvailableTimeMinutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prod_daily_log_header`
--

INSERT INTO `tbl_prod_daily_log_header` (`HeaderID`, `LogDate`, `DepartmentID`, `MachineType`, `ManHours`, `AvailableTimeMinutes`) VALUES
(4, '2025-09-23', 1, 'پرس', 34.00, 600),
(5, '2025-09-24', 1, 'پرس', 38.00, 600),
(6, '2025-09-25', 1, 'پرس', 45.00, 600),
(7, '2025-09-26', 1, 'پرس', 32.00, 600),
(8, '2025-09-27', 1, 'پرس', 32.00, 600),
(9, '2025-09-28', 1, 'پرس', 33.00, 600),
(10, '2025-09-29', 1, 'پرس', 32.00, 600),
(11, '2025-09-30', 1, 'پرس', 36.00, 600),
(12, '2025-10-01', 1, 'پرس', 38.00, 600),
(13, '2025-10-02', 1, 'پرس', 32.00, 600),
(14, '2025-10-03', 1, 'پرس', 32.00, 600),
(15, '2025-10-04', 1, 'پرس', 32.00, 600),
(16, '2025-10-05', 1, 'پرس', 35.00, 600),
(17, '2025-10-06', 1, 'پرس', 37.00, 600),
(18, '2025-10-07', 1, 'پرس', 40.00, 600),
(19, '2025-10-08', 1, 'پرس', 31.00, 600),
(20, '2025-10-09', 1, 'پرس', 39.00, 600),
(21, '2025-10-10', 1, 'پرس', 35.00, 600),
(22, '2025-10-11', 1, 'پرس', 40.00, 600),
(23, '2025-10-12', 1, 'پرس', 40.00, 600),
(24, '2025-10-13', 1, 'پرس', 31.00, 600),
(25, '2025-10-14', 1, 'پرس', 31.00, 600),
(26, '2025-10-15', 1, 'پرس', 45.00, 600),
(27, '2025-10-16', 1, 'پرس', 32.00, 600),
(28, '2025-10-17', 1, 'پرس', 30.00, 600),
(29, '2025-10-18', 1, 'پرس', 32.00, 600),
(30, '2025-10-19', 1, 'پرس', 39.00, 600);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prod_downtime_details`
--

CREATE TABLE `tbl_prod_downtime_details` (
  `DetailID` int(11) NOT NULL,
  `HeaderID` int(11) NOT NULL,
  `MachineID` int(11) NOT NULL,
  `MoldID` int(11) NOT NULL,
  `ReasonID` int(11) NOT NULL,
  `Duration` int(11) NOT NULL COMMENT 'Downtime in minutes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prod_downtime_details`
--

INSERT INTO `tbl_prod_downtime_details` (`DetailID`, `HeaderID`, `MachineID`, `MoldID`, `ReasonID`, `Duration`) VALUES
(3, 2, 1, 11, 3, 300),
(4, 2, 2, 5, 4, 240),
(5, 2, 2, 5, 8, 300),
(6, 2, 3, 12, 3, 300),
(7, 2, 5, 2, 7, 180),
(8, 2, 6, 17, 6, 540),
(9, 2, 7, 14, 3, 540),
(10, 2, 8, 9, 4, 180),
(11, 3, 1, 11, 3, 480),
(12, 3, 2, 5, 4, 120),
(13, 3, 2, 5, 6, 240),
(14, 3, 3, 12, 1, 540),
(15, 3, 4, 15, 6, 540),
(16, 3, 5, 2, 3, 360),
(17, 3, 5, 2, 8, 120),
(18, 3, 6, 17, 6, 540),
(19, 3, 7, 14, 3, 540),
(20, 3, 8, 9, 6, 300),
(21, 4, 1, 11, 7, 180),
(22, 4, 2, 5, 4, 540),
(23, 4, 3, 12, 6, 420),
(24, 4, 3, 12, 8, 180),
(25, 4, 4, 16, 8, 120),
(26, 4, 4, 16, 2, 360),
(27, 4, 6, 2, 7, 300),
(28, 4, 6, 2, 3, 240),
(29, 4, 7, 14, 3, 540),
(30, 5, 1, 11, 3, 540),
(31, 5, 2, 5, 4, 180),
(32, 5, 4, 16, 2, 300),
(33, 5, 7, 14, 3, 540),
(34, 5, 8, 9, 4, 600),
(35, 6, 1, 11, 3, 300),
(36, 6, 2, 5, 4, 540),
(37, 6, 3, 24, 3, 180),
(38, 6, 4, 16, 2, 360),
(39, 6, 5, 23, 3, 240),
(40, 6, 6, 2, 3, 60),
(41, 6, 7, 14, 3, 540),
(42, 6, 8, 9, 6, 120),
(43, 7, 1, 11, 3, 540),
(44, 7, 2, 5, 4, 480),
(45, 7, 3, 12, 3, 540),
(46, 7, 4, 16, 2, 300),
(47, 7, 5, 23, 3, 540),
(48, 7, 6, 2, 3, 180),
(49, 7, 7, 14, 3, 540),
(50, 7, 8, 9, 4, 540),
(51, 8, 1, 11, 3, 540),
(52, 8, 2, 5, 6, 540),
(53, 8, 3, 12, 3, 300),
(54, 8, 5, 23, 8, 180),
(55, 8, 5, 23, 3, 360),
(56, 8, 6, 2, 3, 540),
(57, 8, 7, 14, 3, 540),
(58, 8, 8, 9, 4, 540),
(59, 9, 1, 24, 8, 240),
(60, 9, 2, 5, 6, 540),
(61, 9, 5, 23, 3, 540),
(62, 9, 6, 2, 3, 540),
(63, 9, 7, 14, 3, 540),
(64, 9, 8, 9, 4, 300),
(65, 10, 1, 10, 6, 60),
(66, 10, 2, 5, 6, 60),
(67, 10, 3, 12, 3, 240),
(68, 10, 4, 16, 3, 540),
(69, 10, 5, 23, 3, 540),
(70, 10, 6, 2, 6, 60),
(71, 10, 7, 14, 3, 540),
(72, 10, 8, 9, 6, 240),
(73, 11, 1, 10, 7, 120),
(74, 11, 2, 5, 8, 300),
(75, 11, 3, 12, 3, 300),
(76, 11, 4, 16, 3, 480),
(77, 11, 5, 23, 3, 480),
(78, 11, 6, 2, 3, 480),
(79, 11, 7, 14, 3, 480),
(80, 11, 8, 9, 6, 480),
(81, 12, 1, 10, 7, 180),
(82, 12, 1, 10, 3, 240),
(83, 12, 2, 5, 4, 180),
(84, 12, 2, 5, 6, 360),
(85, 12, 3, 12, 3, 300),
(86, 12, 4, 16, 3, 420),
(87, 12, 5, 23, 3, 300),
(88, 12, 6, 2, 3, 60),
(89, 12, 7, 14, 3, 540),
(90, 12, 8, 9, 6, 540),
(91, 13, 1, 10, 3, 120),
(92, 13, 2, 5, 8, 540),
(93, 13, 3, 12, 3, 360),
(94, 13, 4, 16, 3, 240),
(95, 13, 5, 23, 3, 540),
(96, 13, 6, 2, 3, 240),
(97, 13, 7, 14, 3, 540),
(98, 13, 8, 9, 6, 420);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prod_downtime_header`
--

CREATE TABLE `tbl_prod_downtime_header` (
  `HeaderID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `MachineType` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prod_downtime_header`
--

INSERT INTO `tbl_prod_downtime_header` (`HeaderID`, `LogDate`, `MachineType`) VALUES
(2, '2025-09-23', 'پرس'),
(3, '2025-09-24', 'پرس'),
(4, '2025-09-25', 'پرس'),
(5, '2025-09-26', 'پرس'),
(6, '2025-09-27', 'پرس'),
(7, '2025-09-28', 'پرس'),
(8, '2025-09-29', 'پرس'),
(9, '2025-09-30', 'پرس'),
(10, '2025-10-01', 'پرس'),
(11, '2025-10-02', 'پرس'),
(12, '2025-10-04', 'پرس'),
(13, '2025-10-05', 'پرس');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_projects`
--

CREATE TABLE `tbl_projects` (
  `ProjectID` int(11) NOT NULL,
  `ProjectName` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `CurrentStage` varchar(100) DEFAULT NULL,
  `ResponsibleEmployeeID` int(11) DEFAULT NULL,
  `PriorityID` int(11) NOT NULL DEFAULT 2,
  `Deadline` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_projects`
--

INSERT INTO `tbl_projects` (`ProjectID`, `ProjectName`, `Description`, `StartDate`, `CurrentStage`, `ResponsibleEmployeeID`, `PriorityID`, `Deadline`) VALUES
(4, 'صیصی', 'یصیصی', '2025-10-15', 'صیصی', 1, 1, '2025-10-15');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_project_tasks`
--

CREATE TABLE `tbl_project_tasks` (
  `TaskID` int(11) NOT NULL,
  `ProjectID` int(11) NOT NULL,
  `TaskDescription` text NOT NULL,
  `ResponsibleEmployeeID` int(11) DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `TaskStatusID` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_project_tasks`
--

INSERT INTO `tbl_project_tasks` (`TaskID`, `ProjectID`, `TaskDescription`, `ResponsibleEmployeeID`, `StartDate`, `TaskStatusID`) VALUES
(2, 4, 'سنگ زنی', 3, '2025-10-15', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quality_deviations`
--

CREATE TABLE `tbl_quality_deviations` (
  `DeviationID` int(11) NOT NULL,
  `DeviationCode` varchar(50) DEFAULT NULL,
  `FamilyID` int(11) DEFAULT NULL COMMENT 'FK to tbl_part_families (اختیاری)',
  `PartID` int(11) DEFAULT NULL COMMENT 'FK to tbl_parts (اختیاری)',
  `Reason` text NOT NULL COMMENT 'دلیل صدور مجوز ارفاقی',
  `Status` enum('Draft','Approved','Expired') NOT NULL DEFAULT 'Draft' COMMENT 'وضعیت مجوز',
  `ValidFrom` date DEFAULT NULL COMMENT 'تاریخ شروع اعتبار',
  `ValidTo` date DEFAULT NULL COMMENT 'تاریخ پایان اعتبار',
  `DocumentationLink` varchar(512) DEFAULT NULL COMMENT 'مسیر فایل مستندات بارگذاری شده',
  `CreatedBy` int(11) DEFAULT NULL COMMENT 'FK to tbl_users - ID کاربر ایجاد کننده',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'زمان ایجاد رکورد',
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'زمان آخرین بروزرسانی'
) ;

--
-- Dumping data for table `tbl_quality_deviations`
--

INSERT INTO `tbl_quality_deviations` (`DeviationID`, `DeviationCode`, `FamilyID`, `PartID`, `Reason`, `Status`, `ValidFrom`, `ValidTo`, `DocumentationLink`, `CreatedBy`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 'DEV-1', 3, 55, 'مستقیم مونتاژ شود', 'Approved', '2025-10-26', '2025-10-26', 'documents/quality_deviations/1761455279_.doc', 1, '2025-10-26 05:07:59', '2025-10-26 05:08:10');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_raw_categories`
--

CREATE TABLE `tbl_raw_categories` (
  `CategoryID` int(11) NOT NULL,
  `CategoryName` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='دسته‌بندی مواد اولیه';

--
-- Dumping data for table `tbl_raw_categories`
--

INSERT INTO `tbl_raw_categories` (`CategoryID`, `CategoryName`) VALUES
(2, 'مفتول'),
(1, 'ورق');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_raw_items`
--

CREATE TABLE `tbl_raw_items` (
  `ItemID` int(11) NOT NULL,
  `ItemName` varchar(255) NOT NULL,
  `CategoryID` int(11) NOT NULL,
  `UnitID` int(11) NOT NULL COMMENT 'FK به جدول واحدهای اندازه‌گیری (tbl_units)',
  `SafetyStock` decimal(10,3) DEFAULT NULL COMMENT 'موجودی اطمینان (اختیاری)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تعریف مواد اولیه';

--
-- Dumping data for table `tbl_raw_items`
--

INSERT INTO `tbl_raw_items` (`ItemID`, `ItemName`, `CategoryID`, `UnitID`, `SafetyStock`) VALUES
(1, 'ورق محفظه کوچک', 1, 1, NULL),
(2, 'مفتول 4.64', 2, 1, NULL),
(3, 'ورق 1-1/16', 1, 1, NULL),
(4, 'مفتول 6.60', 2, 1, NULL),
(5, 'ورق 5/8', 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_raw_transactions`
--

CREATE TABLE `tbl_raw_transactions` (
  `TransactionID` bigint(20) NOT NULL,
  `TransactionDate` datetime NOT NULL DEFAULT current_timestamp(),
  `ItemID` int(11) NOT NULL,
  `TransactionTypeID` int(11) NOT NULL COMMENT 'FK به جدول انواع تراکنش (ورود، خروج، کسر، اضافه)',
  `Quantity` decimal(10,3) NOT NULL COMMENT 'مقدار (مثبت برای ورود/اضافه، منفی برای خروج/کسر)',
  `OperatorEmployeeID` int(11) DEFAULT NULL COMMENT 'FK به جدول کارمندان (عامل)',
  `Description` text DEFAULT NULL COMMENT 'توضیحات (مثلاً برای انبارگردانی)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تراکنش‌های انبار مواد اولیه';

--
-- Dumping data for table `tbl_raw_transactions`
--

INSERT INTO `tbl_raw_transactions` (`TransactionID`, `TransactionDate`, `ItemID`, `TransactionTypeID`, `Quantity`, `OperatorEmployeeID`, `Description`) VALUES
(1, '2025-10-23 16:21:32', 2, 4, 500.000, 13, ''),
(2, '2025-10-23 16:22:02', 1, 1, 200.000, 18, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_receivers`
--

CREATE TABLE `tbl_receivers` (
  `ReceiverID` int(11) NOT NULL,
  `ReceiverName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول تحویل گیرندگان (مشتریان)';

--
-- Dumping data for table `tbl_receivers`
--

INSERT INTO `tbl_receivers` (`ReceiverID`, `ReceiverName`) VALUES
(1, 'R54R'),
(2, 'صث'),
(3, 'یب'),
(4, 'یسب');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roles`
--

CREATE TABLE `tbl_roles` (
  `RoleID` int(11) NOT NULL,
  `RoleName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_roles`
--

INSERT INTO `tbl_roles` (`RoleID`, `RoleName`) VALUES
(2, 'مدیر تولید'),
(1, 'مدیر کل');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_role_permissions`
--

CREATE TABLE `tbl_role_permissions` (
  `RoleID` int(11) NOT NULL,
  `PermissionKey` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_role_permissions`
--

INSERT INTO `tbl_role_permissions` (`RoleID`, `PermissionKey`) VALUES
(1, 'base_info.manage'),
(1, 'base_info.view'),
(1, 'engineering.base_info'),
(1, 'engineering.changes.manage'),
(1, 'engineering.changes.view'),
(1, 'engineering.maintenance.manage'),
(1, 'engineering.maintenance.view'),
(1, 'engineering.projects.manage'),
(1, 'engineering.projects.view'),
(1, 'engineering.spare_parts.manage'),
(1, 'engineering.spare_parts.view'),
(1, 'engineering.tools.manage'),
(1, 'engineering.tools.view'),
(1, 'engineering.view'),
(1, 'planning.bom.manage'),
(1, 'planning.bom.view'),
(1, 'planning.mrp.run'),
(1, 'planning.safety_stock.manage'),
(1, 'planning.safety_stock.view'),
(1, 'planning.sales_orders.manage'),
(1, 'planning.sales_orders.view'),
(1, 'planning.view'),
(1, 'planning.view_alerts'),
(1, 'planning_constraints.manage'),
(1, 'planning_constraints.planning_capacity.run'),
(1, 'planning_constraints.view'),
(1, 'production.assembly_hall.manage'),
(1, 'production.assembly_hall.view'),
(1, 'production.plating_hall.manage'),
(1, 'production.plating_hall.view'),
(1, 'production.production_hall.manage'),
(1, 'production.production_hall.view'),
(1, 'production.view'),
(1, 'quality.deviations.manage'),
(1, 'quality.deviations.view'),
(1, 'quality.overrides.manage'),
(1, 'quality.pending_transactions.manage'),
(1, 'quality.pending_transactions.view'),
(1, 'quality.view'),
(1, 'users.permissions.manage'),
(1, 'users.roles.manage'),
(1, 'users.users.manage'),
(1, 'users.view'),
(1, 'warehouse.inventory.alerts'),
(1, 'warehouse.inventory.history'),
(1, 'warehouse.inventory.snapshot'),
(1, 'warehouse.inventory.view'),
(1, 'warehouse.misc.manage'),
(1, 'warehouse.misc.view'),
(1, 'warehouse.raw.manage'),
(1, 'warehouse.raw.view'),
(1, 'warehouse.transactions.manage'),
(1, 'warehouse.view'),
(2, 'base_info.view'),
(2, 'engineering.view');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_rolling_log_entries`
--

CREATE TABLE `tbl_rolling_log_entries` (
  `RollingEntryID` int(11) NOT NULL,
  `RollingHeaderID` int(11) NOT NULL,
  `MachineID` int(11) NOT NULL,
  `OperatorID` int(11) DEFAULT NULL,
  `StartTime` time DEFAULT NULL,
  `EndTime` time DEFAULT NULL,
  `PartID` int(11) NOT NULL,
  `ProductionKG` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Individual rolling log entries';

--
-- Dumping data for table `tbl_rolling_log_entries`
--

INSERT INTO `tbl_rolling_log_entries` (`RollingEntryID`, `RollingHeaderID`, `MachineID`, `OperatorID`, `StartTime`, `EndTime`, `PartID`, `ProductionKG`) VALUES
(6, 1, 27, 32, '08:30:00', '17:30:00', 13, 51.45);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_rolling_log_header`
--

CREATE TABLE `tbl_rolling_log_header` (
  `RollingHeaderID` int(11) NOT NULL,
  `LogDate` date NOT NULL,
  `AvailableTimeMinutes` int(11) DEFAULT NULL COMMENT 'زمان در دسترس روزانه (دقیقه)',
  `Description` text DEFAULT NULL COMMENT 'توضیحات کلی مربوط به روز'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Header for daily rolling logs';

--
-- Dumping data for table `tbl_rolling_log_header`
--

INSERT INTO `tbl_rolling_log_header` (`RollingHeaderID`, `LogDate`, `AvailableTimeMinutes`, `Description`) VALUES
(1, '2025-10-21', 4800, 'جمشید رول کرد');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_routes`
--

CREATE TABLE `tbl_routes` (
  `RouteID` int(11) NOT NULL,
  `FamilyID` int(11) NOT NULL,
  `FromStationID` int(11) NOT NULL,
  `ToStationID` int(11) NOT NULL,
  `NewStatus` varchar(100) NOT NULL COMMENT 'وضعیت قطعه پس از خروج از ایستگاه مبدا',
  `NewStatusID` int(11) DEFAULT NULL,
  `IsFinalStage` tinyint(1) DEFAULT 0 COMMENT 'آیا این ایستگاه پایانی مسیر است؟ (0=خیر, 1=بله)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='مسیرهای استاندارد جریان تولید برای خانواده‌های قطعات';

--
-- Dumping data for table `tbl_routes`
--

INSERT INTO `tbl_routes` (`RouteID`, `FamilyID`, `FromStationID`, `ToStationID`, `NewStatus`, `NewStatusID`, `IsFinalStage`) VALUES
(1, 1, 2, 4, 'تسمه رول شده', 1, 0),
(2, 1, 4, 8, 'آبکاری شده', 2, 0),
(3, 1, 8, 12, 'آبکاری شده', 2, 1),
(4, 12, 2, 8, 'برش خورده\r\n', 3, 0),
(5, 12, 8, 3, 'برش خورده\r\n', 3, 0),
(6, 12, 3, 8, 'دنده شده\r\n', 4, 0),
(7, 12, 8, 5, 'دنده شده\r\n', 4, 0),
(8, 12, 5, 8, 'رول شده', 1, 0),
(9, 12, 8, 12, 'رول شده', 1, 1),
(10, 11, 2, 8, 'دنده شده\r\n', 4, 0),
(12, 11, 5, 8, 'رول شده', 1, 0),
(13, 11, 8, 5, 'دنده شده\r\n', 4, 0),
(14, 11, 8, 12, 'رول شده', 1, 1),
(15, 10, 2, 1, 'پرسکاری شده', 6, 0),
(16, 10, 1, 8, 'شستشو شده', 7, 0),
(17, 10, 8, 12, 'شستشو شده', 7, 1),
(18, 2, 2, 4, 'پرسکاری شده', 6, 0),
(19, 2, 4, 8, 'آبکاری شده', 2, 0),
(20, 2, 8, 12, 'آبکاری شده', 2, 1),
(21, 4, 6, 1, 'رزوه شده', 8, 0),
(22, 4, 1, 8, 'شستشو شده', 7, 0),
(23, 4, 8, 12, 'شستشو شده', 7, 1),
(24, 6, 6, 4, 'پرسکاری شده', 6, 0),
(25, 6, 4, 8, 'آبکاری شده', 2, 0),
(26, 6, 8, 12, 'آبکاری شده', 2, 1),
(27, 9, 12, 9, 'مونتاژ شده', 9, 0),
(28, 9, 9, 10, 'مونتاژ شده\r\n', 9, 0),
(29, 9, 10, 11, 'بسته بندی شده\r\n', 11, 1),
(30, 3, 12, 9, 'مونتاژ شده\r\n', 9, 0),
(31, 3, 9, 4, 'مونتاژ شده\r\n', 9, 0),
(32, 3, 4, 9, 'آبکاری شده', 2, 0),
(33, 3, 9, 10, 'آبکاری شده', 2, 0),
(34, 3, 10, 11, 'بسته بندی شده\r\n', 11, 1),
(36, 9, 11, 13, '', 11, 1),
(37, 3, 11, 13, '', 11, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_route_overrides`
--

CREATE TABLE `tbl_route_overrides` (
  `OverrideID` int(11) NOT NULL,
  `FamilyID` int(11) NOT NULL,
  `FromStationID` int(11) NOT NULL,
  `ToStationID` int(11) NOT NULL,
  `OutputStatus` varchar(100) DEFAULT NULL COMMENT 'وضعیت قطعه پس از انجام این مسیر غیراستاندارد',
  `OutputStatusID` int(11) DEFAULT NULL,
  `DeviationID` int(11) DEFAULT NULL COMMENT 'FK به جدول مجوزهای ارفاقی (اختیاری)',
  `IsActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `Description` text DEFAULT NULL COMMENT 'توضیحات دلیل تعریف این مسیر غیراستاندارد'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='مسیرهای غیر استاندارد مجاز متصل به مجوز ارفاقی';

--
-- Dumping data for table `tbl_route_overrides`
--

INSERT INTO `tbl_route_overrides` (`OverrideID`, `FamilyID`, `FromStationID`, `ToStationID`, `OutputStatus`, `OutputStatusID`, `DeviationID`, `IsActive`, `Description`) VALUES
(1, 1, 2, 8, 'تسمه آبکاری نشده', 1, NULL, 1, 'باز بودن دهانه تسمه و نیاز به مونتاژ مستقیم'),
(2, 1, 8, 12, 'تسمه آبکاری نشده', 17, NULL, 1, ''),
(3, 9, 12, 8, 'بست آبکاری نشده', 17, NULL, 1, ''),
(4, 9, 8, 4, 'بست آبکاری نشده', 17, NULL, 1, ''),
(6, 3, 9, 4, 'بست سفت  شده', 19, NULL, 1, ''),
(7, 3, 4, 10, 'بست آبکاری شده نرم شده', 20, NULL, 1, ''),
(8, 1, 8, 4, 'آبکاری ضعیف', 21, NULL, 1, ''),
(9, 2, 8, 4, 'آبکاری ضعیف', 21, NULL, 1, ''),
(11, 6, 8, 4, 'آبکاری ضعیف', 21, NULL, 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sales_orders`
--

CREATE TABLE `tbl_sales_orders` (
  `SalesOrderID` int(11) NOT NULL,
  `PartID` int(11) NOT NULL COMMENT 'FK to tbl_parts (محصول نهایی)',
  `QuantityRequired` int(11) NOT NULL COMMENT 'تعداد مورد نیاز',
  `DueDate` date NOT NULL COMMENT 'تاریخ تحویل مورد نیاز',
  `Status` enum('Open','InProgress','Completed','Cancelled') NOT NULL DEFAULT 'Open',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول تقاضا یا سفارشات فروش مشتریان';

--
-- Dumping data for table `tbl_sales_orders`
--

INSERT INTO `tbl_sales_orders` (`SalesOrderID`, `PartID`, `QuantityRequired`, `DueDate`, `Status`, `CreatedAt`) VALUES
(4, 49, 100000, '2025-11-06', 'Open', '2025-11-02 09:02:01'),
(5, 55, 1000000, '2025-11-06', 'Open', '2025-11-02 09:06:26');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_spare_part_orders`
--

CREATE TABLE `tbl_spare_part_orders` (
  `OrderID` int(11) NOT NULL,
  `OrderDate` date NOT NULL,
  `PartID` int(11) NOT NULL,
  `MoldID` int(11) DEFAULT NULL,
  `QuantityOrdered` int(11) NOT NULL,
  `ContractorID` int(11) DEFAULT NULL,
  `OrderStatusID` int(11) NOT NULL DEFAULT 1,
  `DateReceived` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_spare_part_orders`
--

INSERT INTO `tbl_spare_part_orders` (`OrderID`, `OrderDate`, `PartID`, `MoldID`, `QuantityOrdered`, `ContractorID`, `OrderStatusID`, `DateReceived`) VALUES
(3, '2025-10-14', 3, 3, 1, 2, 2, '2025-10-15'),
(5, '2025-10-14', 2, 2, 4, 1, 2, '2025-10-21'),
(6, '2025-10-15', 1, NULL, 4, 3, 2, '2025-10-15'),
(7, '2025-10-25', 37, 4, 6, 2, 2, '2025-10-25');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stations`
--

CREATE TABLE `tbl_stations` (
  `StationID` int(11) NOT NULL,
  `StationName` varchar(100) NOT NULL,
  `StationType` enum('Production','Warehouse','QC','External') NOT NULL COMMENT 'Production, Warehouse, Quality Control, External (e.g., plating service)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_stations`
--

INSERT INTO `tbl_stations` (`StationID`, `StationName`, `StationType`) VALUES
(1, 'شستشو', 'Production'),
(2, 'پرسکاری', 'Production'),
(3, 'دنده زنی', 'Production'),
(4, 'آبکاری', 'Production'),
(5, 'رول', 'Production'),
(6, 'پیچ سازی', 'Production'),
(7, 'دوباره کاری', 'QC'),
(8, 'انبار منفصله', 'Warehouse'),
(9, 'انبار نهایی', 'Warehouse'),
(10, 'بسته بندی', 'Production'),
(11, 'انبار بسته بندی', 'Warehouse'),
(12, 'مونتاژ', 'Production'),
(13, 'مشتری', 'External');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_stock_transactions`
--

CREATE TABLE `tbl_stock_transactions` (
  `TransactionID` bigint(20) NOT NULL,
  `TransactionDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `PartID` int(11) NOT NULL,
  `FromStationID` int(11) NOT NULL,
  `ToStationID` int(11) NOT NULL,
  `GrossWeightKG` decimal(10,3) DEFAULT NULL COMMENT 'وزن ناخالص با پالت بر حسب کیلوگرم',
  `PalletTypeID` int(11) DEFAULT NULL,
  `PalletWeightKG` decimal(10,3) DEFAULT NULL COMMENT 'وزن پالت ثبت شده در زمان تراکنش بر حسب کیلوگرم',
  `NetWeightKG` decimal(10,3) DEFAULT NULL COMMENT 'وزن خالص محاسبه شده بر حسب کیلوگرم',
  `CartonQuantity` int(11) DEFAULT NULL COMMENT 'تعداد کارتن (برای انبار بسته بندی)',
  `BaseWeightGR` decimal(10,3) DEFAULT NULL COMMENT 'وزن پایه بر حسب گرم',
  `AppliedWeightChangePercent` decimal(5,2) DEFAULT NULL COMMENT 'درصد تغییر وزن فرآیند (از جدول تغییرات وزن - فعلا تعریف نشده)',
  `FinalWeightGR` decimal(10,3) DEFAULT NULL COMMENT 'وزن نهایی قطعه محاسبه شده بر حسب گرم',
  `StatusAfter` varchar(100) DEFAULT NULL COMMENT 'وضعیت قطعه پس از تراکنش (از tbl_routes)',
  `StatusAfterID` int(11) DEFAULT NULL,
  `RouteStatus` enum('Standard','NonStandardPending','NonStandardApproved') NOT NULL DEFAULT 'Standard' COMMENT 'وضعیت مسیر استفاده شده',
  `DeviationID` int(11) DEFAULT NULL COMMENT 'در صورت ارتباط با مجوز ارفاقی',
  `PendingReason` text DEFAULT NULL COMMENT 'دلیل انتظار برای مسیر غیراستاندارد',
  `CreatedBy` int(11) DEFAULT NULL COMMENT 'UserID of creator',
  `OperatorEmployeeID` int(11) DEFAULT NULL COMMENT 'شناسه کارمند عامل ثبت کننده',
  `SenderEmployeeID` int(11) DEFAULT NULL COMMENT 'FK به tbl_employees برای تحویل دهنده',
  `ReceiverID` int(11) DEFAULT NULL COMMENT 'FK به tbl_receivers برای تحویل گیرنده',
  `TransactionTypeID` int(11) DEFAULT NULL COMMENT 'FK to tbl_transaction_types, used for stocktake/adjustments',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='رکورد تراکنش‌های موجودی بین ایستگاه‌ها';

--
-- Dumping data for table `tbl_stock_transactions`
--

INSERT INTO `tbl_stock_transactions` (`TransactionID`, `TransactionDate`, `PartID`, `FromStationID`, `ToStationID`, `GrossWeightKG`, `PalletTypeID`, `PalletWeightKG`, `NetWeightKG`, `CartonQuantity`, `BaseWeightGR`, `AppliedWeightChangePercent`, `FinalWeightGR`, `StatusAfter`, `StatusAfterID`, `RouteStatus`, `DeviationID`, `PendingReason`, `CreatedBy`, `OperatorEmployeeID`, `SenderEmployeeID`, `ReceiverID`, `TransactionTypeID`, `CreatedAt`) VALUES
(8, '2025-10-23 09:54:54', 55, 4, 9, 45.000, NULL, 0.000, 45.000, NULL, 15.600, 0.00, 15.600, 'آبکاری شده', 2, 'Standard', NULL, NULL, 1, 13, NULL, NULL, NULL, '2025-10-26 12:24:54'),
(9, '2025-10-23 10:19:10', 44, 9, 10, 45.000, NULL, 0.000, 45.000, NULL, 5.600, 0.00, 5.600, 'مونتاژ شده\r\n', NULL, 'Standard', NULL, NULL, 1, 13, NULL, NULL, NULL, '2025-10-26 12:39:11'),
(10, '2025-10-20 10:12:20', 55, 4, 9, 45.200, 1, 2.600, 42.600, NULL, 15.600, 0.00, 15.600, 'آبکاری شده', 2, 'Standard', NULL, NULL, 1, 4, NULL, NULL, NULL, '2025-10-26 12:42:20'),
(14, '2025-10-21 10:40:14', 3, 8, 12, 46.000, NULL, 0.000, 46.000, NULL, 2.080, 0.00, 2.080, 'تسمه آبکاری نشده', NULL, 'NonStandardApproved', NULL, NULL, 1, 31, NULL, NULL, NULL, '2025-10-26 13:10:14'),
(15, '2025-10-28 08:17:48', 18, 3, 8, 40.000, NULL, 0.000, 40.000, NULL, 10.400, 0.00, 10.400, NULL, 4, 'Standard', NULL, NULL, 1, 13, NULL, NULL, NULL, '2025-10-28 10:47:48'),
(16, '2025-10-28 08:18:24', 64, 4, 9, 40.000, NULL, 0.000, 40.000, NULL, 25.500, 0.00, 25.500, NULL, 2, 'Standard', NULL, NULL, 1, 13, NULL, NULL, NULL, '2025-10-28 10:48:24'),
(17, '2025-10-28 08:19:55', 49, 12, 9, 42.000, NULL, 0.000, 42.000, NULL, 5.600, 0.00, 5.600, NULL, 9, 'Standard', NULL, NULL, 1, 4, NULL, NULL, NULL, '2025-10-28 10:49:55'),
(18, '2025-10-23 08:20:44', 15, 8, 5, 48.000, NULL, 0.000, 48.000, NULL, 7.300, 0.00, 7.300, NULL, 4, 'Standard', NULL, NULL, 1, 6, NULL, NULL, NULL, '2025-10-28 10:50:44'),
(19, '2025-10-23 08:25:13', 43, 4, 8, 200.000, NULL, 0.000, 200.000, NULL, 3.300, 0.00, 3.300, NULL, 2, 'Standard', NULL, NULL, 1, 32, NULL, NULL, NULL, '2025-10-28 10:55:13'),
(20, '2025-10-26 08:28:12', 9, 2, 4, 73.000, NULL, 0.000, 73.000, NULL, 3.200, 0.00, 3.200, NULL, 1, 'Standard', NULL, NULL, 1, 37, NULL, NULL, NULL, '2025-10-28 10:58:12'),
(21, '2025-10-23 08:43:34', 58, 8, 8, 433.000, NULL, 0.000, 433.000, NULL, NULL, 0.00, NULL, NULL, 17, 'Standard', NULL, NULL, 1, 13, NULL, NULL, NULL, '2025-10-28 11:13:34'),
(27, '2025-10-29 05:46:28', 55, 10, 11, 0.000, NULL, 0.000, 0.000, 3, 15.600, 0.00, 15.600, NULL, 11, 'Standard', NULL, NULL, 1, 13, NULL, NULL, NULL, '2025-10-29 08:16:28'),
(28, '2025-10-29 05:48:52', 55, 10, 11, 0.000, NULL, 0.000, 0.000, 5, 15.600, 0.00, 15.600, NULL, 11, 'Standard', NULL, NULL, 1, 15, NULL, NULL, NULL, '2025-10-29 08:18:52'),
(29, '2025-10-29 05:50:04', 58, 10, 11, 0.000, NULL, 0.000, 0.000, 8, 18.800, 0.00, 18.800, NULL, 11, 'Standard', NULL, NULL, 1, 35, NULL, NULL, NULL, '2025-10-29 08:20:04'),
(30, '2025-10-29 05:51:35', 49, 12, 9, 45.000, NULL, 0.000, 45.000, NULL, 5.600, 0.00, 5.600, NULL, 9, 'Standard', NULL, NULL, 1, 35, NULL, NULL, NULL, '2025-10-29 08:21:35'),
(32, '2025-10-29 06:37:34', 55, 11, 13, 0.000, NULL, 0.000, 0.000, 5, NULL, NULL, NULL, NULL, 11, 'Standard', NULL, NULL, 1, 35, NULL, 2, NULL, '2025-10-29 09:07:34'),
(34, '2025-10-29 07:24:54', 49, 11, 13, 0.000, NULL, 0.000, 0.000, 4, NULL, NULL, NULL, NULL, 30, 'Standard', NULL, NULL, 1, NULL, 15, 4, NULL, '2025-10-29 09:54:54'),
(35, '2025-10-29 09:20:27', 48, 11, 13, 0.000, NULL, 0.000, 0.000, 6, NULL, NULL, NULL, NULL, 30, 'Standard', NULL, NULL, 1, 35, 28, 1, NULL, '2025-10-29 11:50:27'),
(36, '2025-10-23 09:20:55', 50, 10, 11, 0.000, NULL, 0.000, 0.000, 5, NULL, NULL, NULL, NULL, 11, 'Standard', NULL, NULL, 1, 13, NULL, NULL, NULL, '2025-10-29 11:50:55'),
(37, '2025-10-23 09:22:25', 55, 11, 13, 0.000, NULL, 0.000, 0.000, 11, NULL, NULL, NULL, NULL, 11, 'Standard', NULL, NULL, 1, 4, NULL, 2, NULL, '2025-10-29 11:52:25'),
(38, '2025-10-23 09:31:40', 44, 11, 13, 0.000, NULL, 0.000, 0.000, 6, NULL, NULL, NULL, NULL, 30, 'Standard', NULL, NULL, 1, 35, 28, 2, NULL, '2025-10-29 12:01:40'),
(39, '2025-10-23 09:50:09', 58, 11, 13, 0.000, NULL, 0.000, 0.000, 6, NULL, NULL, NULL, NULL, 30, 'Standard', NULL, NULL, 1, 15, NULL, 3, NULL, '2025-10-29 12:20:09'),
(40, '2025-10-23 09:50:31', 17, 8, 3, 45.000, NULL, 0.000, 45.000, NULL, 9.500, 0.00, 9.500, NULL, 3, 'Standard', NULL, NULL, 1, 4, NULL, NULL, NULL, '2025-10-29 12:20:31'),
(42, '2025-10-23 10:04:34', 62, 11, 13, 0.000, NULL, 0.000, 0.000, 5, NULL, NULL, NULL, NULL, 30, 'Standard', NULL, NULL, 1, 18, NULL, 3, NULL, '2025-10-29 12:34:34'),
(47, '2025-11-01 03:15:00', 55, 10, 11, 0.000, NULL, 0.000, 0.000, 20, NULL, NULL, NULL, NULL, 11, 'Standard', NULL, NULL, 1, 17, NULL, NULL, NULL, '2025-11-01 05:45:00'),
(48, '2025-11-01 04:05:47', 55, 11, 11, 0.000, NULL, 0.000, NULL, -4, NULL, NULL, NULL, NULL, 11, 'Standard', NULL, NULL, 1, 6, NULL, NULL, 5, '2025-11-01 06:35:47'),
(51, '2025-11-01 04:17:46', 7, 4, 8, 80.000, NULL, 0.000, 80.000, NULL, 2.080, 0.00, 2.080, NULL, 2, 'Standard', NULL, NULL, 1, 7, NULL, NULL, NULL, '2025-11-01 06:47:46'),
(52, '2025-11-02 06:54:33', 7, 2, 8, 40.000, NULL, 0.000, 40.000, NULL, 2.080, 0.00, 2.080, NULL, 1, 'NonStandardApproved', NULL, NULL, 1, 35, NULL, NULL, NULL, '2025-11-02 09:24:33');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_task_statuses`
--

CREATE TABLE `tbl_task_statuses` (
  `TaskStatusID` int(11) NOT NULL,
  `StatusName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_task_statuses`
--

INSERT INTO `tbl_task_statuses` (`TaskStatusID`, `StatusName`) VALUES
(3, 'تکمیل شده'),
(2, 'در حال انجام'),
(1, 'شروع نشده'),
(4, 'متوقف شده');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_transaction_types`
--

CREATE TABLE `tbl_transaction_types` (
  `TypeID` int(11) NOT NULL,
  `TypeName` varchar(100) NOT NULL,
  `StockEffect` smallint(6) NOT NULL DEFAULT 0 COMMENT '1 برای افزایش, -1 برای کاهش, 0 بی‌اثر'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_transaction_types`
--

INSERT INTO `tbl_transaction_types` (`TypeID`, `TypeName`, `StockEffect`) VALUES
(1, 'ورود به انبار', 1),
(2, 'خروج از انبار', -1),
(4, 'موجودی اولیه', 1),
(5, 'کسر انبارگردانی', -1),
(6, 'اضافه انبارگردانی', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_units`
--

CREATE TABLE `tbl_units` (
  `UnitID` int(11) NOT NULL,
  `UnitName` varchar(50) NOT NULL,
  `Symbol` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_units`
--

INSERT INTO `tbl_units` (`UnitID`, `UnitName`, `Symbol`) VALUES
(1, 'کیلوگرم', 'KG'),
(2, 'گرم', 'gr'),
(3, 'لیتر', 'L'),
(4, 'عدد', 'N');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `UserID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `RoleID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`UserID`, `Username`, `PasswordHash`, `EmployeeID`, `RoleID`) VALUES
(1, 'admin', '$2y$10$qzqf.dRF5H4VWtiskwUyWuCQ7CWu8YVlYdOXqF0EK0YlOy8YjooAq', 5, 1),
(2, 'admin1', '$2y$10$H1or0Zq7MDQPNEwn.BElceI1cRx5lVBLvZ30HKnce9ZCpFKOH0S82', 6, 2);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_warehouses`
--

CREATE TABLE `tbl_warehouses` (
  `WarehouseID` int(11) NOT NULL,
  `WarehouseName` varchar(100) NOT NULL,
  `WarehouseTypeID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_warehouses`
--

INSERT INTO `tbl_warehouses` (`WarehouseID`, `WarehouseName`, `WarehouseTypeID`) VALUES
(1, 'انبار نیمه ساخته', 2),
(2, 'انبار پرسکاری', 2),
(3, 'انبار بسته بندی', 4),
(4, 'انبار محصول نهایی', 3),
(5, 'پیچ سازی', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_warehouse_types`
--

CREATE TABLE `tbl_warehouse_types` (
  `WarehouseTypeID` int(11) NOT NULL,
  `TypeName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_warehouse_types`
--

INSERT INTO `tbl_warehouse_types` (`WarehouseTypeID`, `TypeName`) VALUES
(4, 'آماده ارسال'),
(3, 'تولید شده'),
(2, 'در حال ساخت');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_absences`
--
ALTER TABLE `tbl_absences`
  ADD PRIMARY KEY (`AbsenceID`),
  ADD UNIQUE KEY `EmployeeID` (`EmployeeID`,`AbsenceDate`);

--
-- Indexes for table `tbl_assembly_log_entries`
--
ALTER TABLE `tbl_assembly_log_entries`
  ADD PRIMARY KEY (`AssemblyEntryID`),
  ADD KEY `fk_ale_header` (`AssemblyHeaderID`),
  ADD KEY `fk_ale_machine` (`MachineID`),
  ADD KEY `fk_ale_operator1` (`Operator1ID`),
  ADD KEY `fk_ale_operator2` (`Operator2ID`),
  ADD KEY `fk_ale_part` (`PartID`);

--
-- Indexes for table `tbl_assembly_log_header`
--
ALTER TABLE `tbl_assembly_log_header`
  ADD PRIMARY KEY (`AssemblyHeaderID`),
  ADD UNIQUE KEY `LogDate_unique` (`LogDate`);

--
-- Indexes for table `tbl_bom_structure`
--
ALTER TABLE `tbl_bom_structure`
  ADD PRIMARY KEY (`BomID`),
  ADD UNIQUE KEY `unique_parent_child` (`ParentPartID`,`ChildPartID`),
  ADD KEY `fk_bom_child` (`ChildPartID`),
  ADD KEY `fk_bom_req_status` (`RequiredStatusID`);

--
-- Indexes for table `tbl_break_times`
--
ALTER TABLE `tbl_break_times`
  ADD PRIMARY KEY (`BreakID`),
  ADD KEY `idx_break_dept` (`DepartmentID`);

--
-- Indexes for table `tbl_chemicals`
--
ALTER TABLE `tbl_chemicals`
  ADD PRIMARY KEY (`ChemicalID`),
  ADD UNIQUE KEY `ChemicalName` (`ChemicalName`),
  ADD KEY `fk_chem_type` (`ChemicalTypeID`),
  ADD KEY `fk_chem_unit` (`UnitID`);

--
-- Indexes for table `tbl_chemical_types`
--
ALTER TABLE `tbl_chemical_types`
  ADD PRIMARY KEY (`ChemicalTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `tbl_contractors`
--
ALTER TABLE `tbl_contractors`
  ADD PRIMARY KEY (`ContractorID`);

--
-- Indexes for table `tbl_daily_man_hours`
--
ALTER TABLE `tbl_daily_man_hours`
  ADD PRIMARY KEY (`LogID`),
  ADD UNIQUE KEY `LogDate_DepartmentID_unique` (`LogDate`,`DepartmentID`),
  ADD KEY `tbl_daily_man_hours_ibfk_1` (`DepartmentID`);

--
-- Indexes for table `tbl_departments`
--
ALTER TABLE `tbl_departments`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD UNIQUE KEY `DepartmentName` (`DepartmentName`);

--
-- Indexes for table `tbl_downtimereasons`
--
ALTER TABLE `tbl_downtimereasons`
  ADD PRIMARY KEY (`ReasonID`);

--
-- Indexes for table `tbl_downtime_log`
--
ALTER TABLE `tbl_downtime_log`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `tbl_downtime_log_ibfk_1` (`MachineID`),
  ADD KEY `tbl_downtime_log_ibfk_2` (`MoldID_AtTime`),
  ADD KEY `tbl_downtime_log_ibfk_3` (`ReasonID`);

--
-- Indexes for table `tbl_employees`
--
ALTER TABLE `tbl_employees`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD KEY `idx_department` (`DepartmentID`);

--
-- Indexes for table `tbl_engineering_changes`
--
ALTER TABLE `tbl_engineering_changes`
  ADD PRIMARY KEY (`ChangeID`),
  ADD KEY `fk_approved_by_employee` (`ApprovedByEmployeeID`),
  ADD KEY `fk_change_to_spare_part` (`SparePartID`);

--
-- Indexes for table `tbl_engineering_change_feedback`
--
ALTER TABLE `tbl_engineering_change_feedback`
  ADD PRIMARY KEY (`FeedbackID`),
  ADD KEY `fk_feedback_to_change` (`ChangeID`);

--
-- Indexes for table `tbl_eng_spare_parts`
--
ALTER TABLE `tbl_eng_spare_parts`
  ADD PRIMARY KEY (`PartID`),
  ADD UNIQUE KEY `PartCode` (`PartCode`),
  ADD KEY `tbl_eng_spare_parts_ibfk_1` (`MoldID`);

--
-- Indexes for table `tbl_eng_spare_part_transactions`
--
ALTER TABLE `tbl_eng_spare_part_transactions`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `tbl_eng_spare_part_transactions_ibfk_1` (`PartID`),
  ADD KEY `tbl_eng_spare_part_transactions_ibfk_2` (`SenderEmployeeID`),
  ADD KEY `tbl_eng_spare_part_transactions_ibfk_3` (`ReceiverEmployeeID`),
  ADD KEY `tbl_eng_spare_part_transactions_ibfk_4` (`OrderID`),
  ADD KEY `fk_spt_transaction_type` (`TransactionTypeID`),
  ADD KEY `idx_mold` (`MoldID`);

--
-- Indexes for table `tbl_eng_tools`
--
ALTER TABLE `tbl_eng_tools`
  ADD PRIMARY KEY (`ToolID`),
  ADD UNIQUE KEY `ToolCode` (`ToolCode`),
  ADD KEY `fk_tool_tooltype` (`ToolTypeID`),
  ADD KEY `fk_tool_department_eng` (`DepartmentID`);

--
-- Indexes for table `tbl_eng_tool_transactions`
--
ALTER TABLE `tbl_eng_tool_transactions`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `fk_tran_tool` (`ToolID`),
  ADD KEY `fk_tran_trantype` (`TransactionTypeID`),
  ADD KEY `fk_tran_sender` (`SenderEmployeeID`),
  ADD KEY `fk_tran_receiver` (`ReceiverEmployeeID`);

--
-- Indexes for table `tbl_eng_tool_types`
--
ALTER TABLE `tbl_eng_tool_types`
  ADD PRIMARY KEY (`ToolTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `tbl_family_status_compatibility`
--
ALTER TABLE `tbl_family_status_compatibility`
  ADD PRIMARY KEY (`FamilyID`,`StatusID`),
  ADD KEY `fk_fsc_status` (`StatusID`);

--
-- Indexes for table `tbl_inventory_safety_stock`
--
ALTER TABLE `tbl_inventory_safety_stock`
  ADD PRIMARY KEY (`SafetyStockID`),
  ADD UNIQUE KEY `unique_stock_point` (`PartID`,`StationID`,`StatusID`),
  ADD KEY `fk_ss_part` (`PartID`),
  ADD KEY `fk_ss_station` (`StationID`),
  ADD KEY `fk_ss_status` (`StatusID`);

--
-- Indexes for table `tbl_inventory_snapshots`
--
ALTER TABLE `tbl_inventory_snapshots`
  ADD PRIMARY KEY (`SnapshotID`),
  ADD KEY `fk_snapshot_user` (`RecordedByUserID`),
  ADD KEY `fk_snapshot_family` (`FilterFamilyID`),
  ADD KEY `fk_snapshot_part` (`FilterPartID`),
  ADD KEY `idx_snapshot_timestamp` (`SnapshotTimestamp`),
  ADD KEY `idx_snapshot_status` (`FilterStatusID`);

--
-- Indexes for table `tbl_machines`
--
ALTER TABLE `tbl_machines`
  ADD PRIMARY KEY (`MachineID`),
  ADD KEY `idx_machine_type` (`MachineType`),
  ADD KEY `idx_machine_spm` (`strokes_per_minute`);

--
-- Indexes for table `tbl_machine_current_setup`
--
ALTER TABLE `tbl_machine_current_setup`
  ADD PRIMARY KEY (`MachineID`),
  ADD KEY `tbl_machine_current_setup_ibfk_2` (`CurrentMoldID`);

--
-- Indexes for table `tbl_machine_producible_families`
--
ALTER TABLE `tbl_machine_producible_families`
  ADD PRIMARY KEY (`MachineID`,`FamilyID`),
  ADD KEY `fk_mpf_family` (`FamilyID`);

--
-- Indexes for table `tbl_maintenance_actions`
--
ALTER TABLE `tbl_maintenance_actions`
  ADD PRIMARY KEY (`ActionID`),
  ADD UNIQUE KEY `ActionDescription` (`ActionDescription`);

--
-- Indexes for table `tbl_maintenance_breakdown_cause_links`
--
ALTER TABLE `tbl_maintenance_breakdown_cause_links`
  ADD PRIMARY KEY (`BreakdownTypeID`,`CauseID`),
  ADD KEY `fk_link_cause` (`CauseID`);

--
-- Indexes for table `tbl_maintenance_breakdown_types`
--
ALTER TABLE `tbl_maintenance_breakdown_types`
  ADD PRIMARY KEY (`BreakdownTypeID`),
  ADD UNIQUE KEY `Description` (`Description`);

--
-- Indexes for table `tbl_maintenance_causes`
--
ALTER TABLE `tbl_maintenance_causes`
  ADD PRIMARY KEY (`CauseID`),
  ADD UNIQUE KEY `CauseDescription` (`CauseDescription`);

--
-- Indexes for table `tbl_maintenance_cause_action_links`
--
ALTER TABLE `tbl_maintenance_cause_action_links`
  ADD PRIMARY KEY (`CauseID`,`ActionID`),
  ADD KEY `fk_link_action` (`ActionID`);

--
-- Indexes for table `tbl_maintenance_reports`
--
ALTER TABLE `tbl_maintenance_reports`
  ADD PRIMARY KEY (`ReportID`),
  ADD KEY `fk_report_mold` (`MoldID`);

--
-- Indexes for table `tbl_maintenance_report_entries`
--
ALTER TABLE `tbl_maintenance_report_entries`
  ADD PRIMARY KEY (`EntryID`),
  ADD KEY `fk_entry_report` (`ReportID`),
  ADD KEY `fk_entry_breakdown` (`BreakdownTypeID`),
  ADD KEY `fk_entry_cause` (`CauseID`),
  ADD KEY `fk_entry_action` (`ActionID`);

--
-- Indexes for table `tbl_misc_categories`
--
ALTER TABLE `tbl_misc_categories`
  ADD PRIMARY KEY (`CategoryID`),
  ADD UNIQUE KEY `CategoryName_unique` (`CategoryName`);

--
-- Indexes for table `tbl_misc_inventory_transactions`
--
ALTER TABLE `tbl_misc_inventory_transactions`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `fk_misc_tran_item` (`ItemID`),
  ADD KEY `fk_misc_tran_type` (`TransactionTypeID`),
  ADD KEY `fk_misc_tran_operator` (`OperatorEmployeeID`),
  ADD KEY `fk_misc_tran_user` (`CreatedByUserID`);

--
-- Indexes for table `tbl_misc_items`
--
ALTER TABLE `tbl_misc_items`
  ADD PRIMARY KEY (`ItemID`),
  ADD UNIQUE KEY `ItemName_unique` (`ItemName`),
  ADD KEY `fk_miscitem_category` (`CategoryID`),
  ADD KEY `fk_miscitem_unit` (`UnitID`);

--
-- Indexes for table `tbl_misc_item_categories`
--
ALTER TABLE `tbl_misc_item_categories`
  ADD PRIMARY KEY (`CategoryID`),
  ADD UNIQUE KEY `CategoryName_unique` (`CategoryName`);

--
-- Indexes for table `tbl_misc_transactions`
--
ALTER TABLE `tbl_misc_transactions`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `fk_misctx_item` (`ItemID`),
  ADD KEY `fk_misctx_type` (`TransactionTypeID`),
  ADD KEY `fk_misctx_operator` (`OperatorEmployeeID`),
  ADD KEY `idx_misctx_date` (`TransactionDate`);

--
-- Indexes for table `tbl_molds`
--
ALTER TABLE `tbl_molds`
  ADD PRIMARY KEY (`MoldID`),
  ADD KEY `idx_mold_name` (`MoldName`);

--
-- Indexes for table `tbl_mold_machine_compatibility`
--
ALTER TABLE `tbl_mold_machine_compatibility`
  ADD PRIMARY KEY (`MoldID`,`MachineID`),
  ADD KEY `tbl_mold_machine_compatibility_ibfk_2` (`MachineID`);

--
-- Indexes for table `tbl_mold_producible_parts`
--
ALTER TABLE `tbl_mold_producible_parts`
  ADD PRIMARY KEY (`MoldID`,`PartID`),
  ADD KEY `fk_mpp_part` (`PartID`);

--
-- Indexes for table `tbl_order_statuses`
--
ALTER TABLE `tbl_order_statuses`
  ADD PRIMARY KEY (`OrderStatusID`),
  ADD UNIQUE KEY `StatusName` (`StatusName`);

--
-- Indexes for table `tbl_packaging_configs`
--
ALTER TABLE `tbl_packaging_configs`
  ADD PRIMARY KEY (`PackageConfigID`),
  ADD UNIQUE KEY `unique_size` (`SizeID`);

--
-- Indexes for table `tbl_packaging_log_details`
--
ALTER TABLE `tbl_packaging_log_details`
  ADD PRIMARY KEY (`PackagingDetailID`),
  ADD UNIQUE KEY `header_part_unique` (`PackagingHeaderID`,`PartID`),
  ADD KEY `fk_pld_part` (`PartID`);

--
-- Indexes for table `tbl_packaging_log_header`
--
ALTER TABLE `tbl_packaging_log_header`
  ADD PRIMARY KEY (`PackagingHeaderID`),
  ADD UNIQUE KEY `LogDate_unique` (`LogDate`);

--
-- Indexes for table `tbl_packaging_log_shifts`
--
ALTER TABLE `tbl_packaging_log_shifts`
  ADD PRIMARY KEY (`ShiftID`),
  ADD KEY `fk_pls_pkg_header` (`PackagingHeaderID`),
  ADD KEY `fk_pls_pkg_employee` (`EmployeeID`);

--
-- Indexes for table `tbl_pallet_types`
--
ALTER TABLE `tbl_pallet_types`
  ADD PRIMARY KEY (`PalletTypeID`),
  ADD UNIQUE KEY `PalletName` (`PalletName`);

--
-- Indexes for table `tbl_parts`
--
ALTER TABLE `tbl_parts`
  ADD PRIMARY KEY (`PartID`),
  ADD UNIQUE KEY `PartCode` (`PartCode`),
  ADD KEY `idx_family` (`FamilyID`),
  ADD KEY `idx_part_familyid` (`FamilyID`),
  ADD KEY `idx_part_sizeid` (`SizeID`);

--
-- Indexes for table `tbl_part_families`
--
ALTER TABLE `tbl_part_families`
  ADD PRIMARY KEY (`FamilyID`),
  ADD UNIQUE KEY `FamilyName` (`FamilyName`),
  ADD KEY `idx_family_name` (`FamilyName`);

--
-- Indexes for table `tbl_part_plating_groups`
--
ALTER TABLE `tbl_part_plating_groups`
  ADD PRIMARY KEY (`PartID`,`GroupID`),
  ADD KEY `fk_ppg_group` (`GroupID`);

--
-- Indexes for table `tbl_part_raw_materials`
--
ALTER TABLE `tbl_part_raw_materials`
  ADD PRIMARY KEY (`PartBomID`),
  ADD UNIQUE KEY `unique_part_raw_material` (`PartID`,`RawMaterialItemID`),
  ADD KEY `fk_bom_raw_material` (`RawMaterialItemID`);

--
-- Indexes for table `tbl_part_sizes`
--
ALTER TABLE `tbl_part_sizes`
  ADD PRIMARY KEY (`SizeID`),
  ADD UNIQUE KEY `family_size_unique` (`FamilyID`,`SizeName`);

--
-- Indexes for table `tbl_part_statuses`
--
ALTER TABLE `tbl_part_statuses`
  ADD PRIMARY KEY (`StatusID`),
  ADD UNIQUE KEY `StatusName_unique` (`StatusName`);

--
-- Indexes for table `tbl_part_weights`
--
ALTER TABLE `tbl_part_weights`
  ADD PRIMARY KEY (`PartWeightID`),
  ADD KEY `idx_part_weight_partid` (`PartID`),
  ADD KEY `idx_part_weight_dates` (`EffectiveFrom`,`EffectiveTo`);

--
-- Indexes for table `tbl_planning_batch_compatibility`
--
ALTER TABLE `tbl_planning_batch_compatibility`
  ADD PRIMARY KEY (`CompatibilityID`),
  ADD UNIQUE KEY `idx_compatibility_pair` (`PrimaryPartID`,`CompatiblePartID`),
  ADD KEY `CompatiblePartID` (`CompatiblePartID`);

--
-- Indexes for table `tbl_planning_capacity_override`
--
ALTER TABLE `tbl_planning_capacity_override`
  ADD PRIMARY KEY (`OverrideID`),
  ADD UNIQUE KEY `UK_Date_Station` (`PlanningDate`,`StationID`),
  ADD KEY `FK_planning_override_station` (`StationID`),
  ADD KEY `FK_planning_override_user` (`LastUpdatedBy`);

--
-- Indexes for table `tbl_planning_mrp_results`
--
ALTER TABLE `tbl_planning_mrp_results`
  ADD PRIMARY KEY (`ResultID`),
  ADD KEY `fk_mrp_result_run` (`RunID`);

--
-- Indexes for table `tbl_planning_mrp_run`
--
ALTER TABLE `tbl_planning_mrp_run`
  ADD PRIMARY KEY (`RunID`),
  ADD KEY `fk_mrp_run_user` (`RunByUserID`);

--
-- Indexes for table `tbl_planning_part_to_group`
--
ALTER TABLE `tbl_planning_part_to_group`
  ADD PRIMARY KEY (`PartID`,`GroupID`),
  ADD KEY `GroupID` (`GroupID`);

--
-- Indexes for table `tbl_planning_plating_groups`
--
ALTER TABLE `tbl_planning_plating_groups`
  ADD PRIMARY KEY (`GroupID`),
  ADD UNIQUE KEY `idx_group_name` (`GroupName`);

--
-- Indexes for table `tbl_planning_station_capacity_rules`
--
ALTER TABLE `tbl_planning_station_capacity_rules`
  ADD PRIMARY KEY (`RuleID`),
  ADD UNIQUE KEY `UK_Station_Method_Machine_Part` (`StationID`,`CalculationMethod`,`MachineID`,`PartID`),
  ADD KEY `FK_planning_rules_station` (`StationID`),
  ADD KEY `fk_capacity_rule_machine` (`MachineID`),
  ADD KEY `fk_capacity_rule_part` (`PartID`);

--
-- Indexes for table `tbl_planning_vibration_incompatibility`
--
ALTER TABLE `tbl_planning_vibration_incompatibility`
  ADD PRIMARY KEY (`PrimaryPartID`,`IncompatiblePartID`) USING BTREE,
  ADD KEY `fk_vib_incompatible_part` (`IncompatiblePartID`);

--
-- Indexes for table `tbl_planning_work_orders`
--
ALTER TABLE `tbl_planning_work_orders`
  ADD PRIMARY KEY (`WorkOrderID`),
  ADD KEY `fk_wo_run` (`RunID`),
  ADD KEY `fk_wo_station` (`StationID`),
  ADD KEY `fk_wo_part` (`PartID`);

--
-- Indexes for table `tbl_plating_compatibility`
--
ALTER TABLE `tbl_plating_compatibility`
  ADD PRIMARY KEY (`PrimaryPartID`,`CompatiblePartID`),
  ADD KEY `fk_pc_compatible` (`CompatiblePartID`);

--
-- Indexes for table `tbl_plating_events_log`
--
ALTER TABLE `tbl_plating_events_log`
  ADD PRIMARY KEY (`EventID`),
  ADD KEY `EmployeeID` (`EmployeeID`);

--
-- Indexes for table `tbl_plating_log_additions`
--
ALTER TABLE `tbl_plating_log_additions`
  ADD PRIMARY KEY (`AdditionID`),
  ADD KEY `fk_pla_header` (`PlatingHeaderID`),
  ADD KEY `fk_pla_chemical` (`ChemicalID`),
  ADD KEY `fk_pla_vat_idx` (`VatID`);

--
-- Indexes for table `tbl_plating_log_details`
--
ALTER TABLE `tbl_plating_log_details`
  ADD PRIMARY KEY (`PlatingDetailID`),
  ADD KEY `PlatingHeaderID` (`PlatingHeaderID`),
  ADD KEY `PartID` (`PartID`);

--
-- Indexes for table `tbl_plating_log_header`
--
ALTER TABLE `tbl_plating_log_header`
  ADD PRIMARY KEY (`PlatingHeaderID`);

--
-- Indexes for table `tbl_plating_log_shifts`
--
ALTER TABLE `tbl_plating_log_shifts`
  ADD PRIMARY KEY (`ShiftID`),
  ADD KEY `fk_pls_header` (`PlatingHeaderID`),
  ADD KEY `fk_pls_employee` (`EmployeeID`);

--
-- Indexes for table `tbl_plating_process_groups`
--
ALTER TABLE `tbl_plating_process_groups`
  ADD PRIMARY KEY (`GroupID`),
  ADD UNIQUE KEY `GroupName_unique` (`GroupName`);

--
-- Indexes for table `tbl_plating_vats`
--
ALTER TABLE `tbl_plating_vats`
  ADD PRIMARY KEY (`VatID`),
  ADD UNIQUE KEY `VatName` (`VatName`);

--
-- Indexes for table `tbl_plating_vat_analysis`
--
ALTER TABLE `tbl_plating_vat_analysis`
  ADD PRIMARY KEY (`AnalysisID`),
  ADD KEY `fk_pva_vat` (`VatID`),
  ADD KEY `idx_pva_date` (`AnalysisDate`);

--
-- Indexes for table `tbl_priorities`
--
ALTER TABLE `tbl_priorities`
  ADD PRIMARY KEY (`PriorityID`),
  ADD UNIQUE KEY `PriorityName` (`PriorityName`);

--
-- Indexes for table `tbl_processes`
--
ALTER TABLE `tbl_processes`
  ADD PRIMARY KEY (`ProcessID`),
  ADD UNIQUE KEY `ProcessName` (`ProcessName`);

--
-- Indexes for table `tbl_process_weight_changes`
--
ALTER TABLE `tbl_process_weight_changes`
  ADD PRIMARY KEY (`ProcessWeightID`),
  ADD KEY `idx_pwc_part_stations_date` (`PartID`,`FromStationID`,`ToStationID`,`EffectiveFrom`,`EffectiveTo`),
  ADD KEY `FromStationID` (`FromStationID`),
  ADD KEY `ToStationID` (`ToStationID`);

--
-- Indexes for table `tbl_prod_daily_log_details`
--
ALTER TABLE `tbl_prod_daily_log_details`
  ADD PRIMARY KEY (`DetailID`),
  ADD KEY `HeaderID` (`HeaderID`),
  ADD KEY `MachineID` (`MachineID`),
  ADD KEY `PartID` (`PartID`),
  ADD KEY `idx_mold_log` (`MoldID`),
  ADD KEY `idx_prod_detail_headerid` (`HeaderID`),
  ADD KEY `idx_prod_detail_machineid` (`MachineID`),
  ADD KEY `idx_prod_detail_moldid` (`MoldID`),
  ADD KEY `idx_prod_detail_partid` (`PartID`);

--
-- Indexes for table `tbl_prod_daily_log_header`
--
ALTER TABLE `tbl_prod_daily_log_header`
  ADD PRIMARY KEY (`HeaderID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `idx_prod_header_logdate` (`LogDate`),
  ADD KEY `idx_prod_header_machinetype` (`MachineType`);

--
-- Indexes for table `tbl_prod_downtime_details`
--
ALTER TABLE `tbl_prod_downtime_details`
  ADD PRIMARY KEY (`DetailID`),
  ADD KEY `fk_downtime_header` (`HeaderID`),
  ADD KEY `fk_downtime_machine` (`MachineID`),
  ADD KEY `fk_downtime_mold` (`MoldID`),
  ADD KEY `fk_downtime_reason` (`ReasonID`),
  ADD KEY `idx_down_detail_headerid` (`HeaderID`),
  ADD KEY `idx_down_detail_machineid` (`MachineID`),
  ADD KEY `idx_down_detail_moldid` (`MoldID`),
  ADD KEY `idx_down_detail_reasonid` (`ReasonID`);

--
-- Indexes for table `tbl_prod_downtime_header`
--
ALTER TABLE `tbl_prod_downtime_header`
  ADD PRIMARY KEY (`HeaderID`),
  ADD UNIQUE KEY `log_date_machine_type` (`LogDate`,`MachineType`),
  ADD KEY `idx_down_header_logdate` (`LogDate`),
  ADD KEY `idx_down_header_machinetype` (`MachineType`);

--
-- Indexes for table `tbl_projects`
--
ALTER TABLE `tbl_projects`
  ADD PRIMARY KEY (`ProjectID`),
  ADD KEY `tbl_projects_ibfk_1` (`ResponsibleEmployeeID`),
  ADD KEY `tbl_projects_ibfk_2` (`PriorityID`);

--
-- Indexes for table `tbl_project_tasks`
--
ALTER TABLE `tbl_project_tasks`
  ADD PRIMARY KEY (`TaskID`),
  ADD KEY `tbl_project_tasks_ibfk_1` (`ProjectID`),
  ADD KEY `tbl_project_tasks_ibfk_2` (`ResponsibleEmployeeID`),
  ADD KEY `tbl_project_tasks_ibfk_3` (`TaskStatusID`);

--
-- Indexes for table `tbl_quality_deviations`
--
ALTER TABLE `tbl_quality_deviations`
  ADD PRIMARY KEY (`DeviationID`),
  ADD UNIQUE KEY `DeviationCode` (`DeviationCode`),
  ADD UNIQUE KEY `DeviationCode_2` (`DeviationCode`),
  ADD KEY `CreatedBy` (`CreatedBy`),
  ADD KEY `idx_deviation_status` (`Status`),
  ADD KEY `idx_deviation_family` (`FamilyID`),
  ADD KEY `idx_deviation_part` (`PartID`),
  ADD KEY `idx_deviation_validity` (`ValidFrom`,`ValidTo`);

--
-- Indexes for table `tbl_raw_categories`
--
ALTER TABLE `tbl_raw_categories`
  ADD PRIMARY KEY (`CategoryID`),
  ADD UNIQUE KEY `CategoryName_unique` (`CategoryName`);

--
-- Indexes for table `tbl_raw_items`
--
ALTER TABLE `tbl_raw_items`
  ADD PRIMARY KEY (`ItemID`),
  ADD UNIQUE KEY `ItemName_unique` (`ItemName`),
  ADD KEY `fk_rawitem_category` (`CategoryID`),
  ADD KEY `fk_rawitem_unit` (`UnitID`);

--
-- Indexes for table `tbl_raw_transactions`
--
ALTER TABLE `tbl_raw_transactions`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `fk_rawtx_item` (`ItemID`),
  ADD KEY `fk_rawtx_type` (`TransactionTypeID`),
  ADD KEY `fk_rawtx_operator` (`OperatorEmployeeID`),
  ADD KEY `idx_rawtx_date` (`TransactionDate`);

--
-- Indexes for table `tbl_receivers`
--
ALTER TABLE `tbl_receivers`
  ADD PRIMARY KEY (`ReceiverID`),
  ADD UNIQUE KEY `ReceiverName_unique` (`ReceiverName`);

--
-- Indexes for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  ADD PRIMARY KEY (`RoleID`),
  ADD UNIQUE KEY `RoleName` (`RoleName`);

--
-- Indexes for table `tbl_role_permissions`
--
ALTER TABLE `tbl_role_permissions`
  ADD PRIMARY KEY (`RoleID`,`PermissionKey`);

--
-- Indexes for table `tbl_rolling_log_entries`
--
ALTER TABLE `tbl_rolling_log_entries`
  ADD PRIMARY KEY (`RollingEntryID`),
  ADD KEY `fk_rle_header` (`RollingHeaderID`),
  ADD KEY `fk_rle_machine` (`MachineID`),
  ADD KEY `fk_rle_operator` (`OperatorID`),
  ADD KEY `fk_rle_part` (`PartID`);

--
-- Indexes for table `tbl_rolling_log_header`
--
ALTER TABLE `tbl_rolling_log_header`
  ADD PRIMARY KEY (`RollingHeaderID`),
  ADD UNIQUE KEY `LogDate_unique` (`LogDate`);

--
-- Indexes for table `tbl_routes`
--
ALTER TABLE `tbl_routes`
  ADD PRIMARY KEY (`RouteID`),
  ADD KEY `idx_routes_family_id` (`FamilyID`),
  ADD KEY `idx_routes_from_station` (`FromStationID`),
  ADD KEY `idx_routes_to_station` (`ToStationID`),
  ADD KEY `idx_routes_new_status_id` (`NewStatusID`);

--
-- Indexes for table `tbl_route_overrides`
--
ALTER TABLE `tbl_route_overrides`
  ADD PRIMARY KEY (`OverrideID`),
  ADD KEY `idx_override_family` (`FamilyID`),
  ADD KEY `idx_override_deviation` (`DeviationID`),
  ADD KEY `FromStationID` (`FromStationID`),
  ADD KEY `ToStationID` (`ToStationID`),
  ADD KEY `idx_override_lookup` (`FamilyID`,`FromStationID`,`ToStationID`,`IsActive`),
  ADD KEY `idx_overrides_output_status_id` (`OutputStatusID`);

--
-- Indexes for table `tbl_sales_orders`
--
ALTER TABLE `tbl_sales_orders`
  ADD PRIMARY KEY (`SalesOrderID`),
  ADD KEY `fk_so_part` (`PartID`);

--
-- Indexes for table `tbl_spare_part_orders`
--
ALTER TABLE `tbl_spare_part_orders`
  ADD PRIMARY KEY (`OrderID`),
  ADD KEY `tbl_spare_part_orders_ibfk_1` (`PartID`),
  ADD KEY `tbl_spare_part_orders_ibfk_2` (`ContractorID`),
  ADD KEY `tbl_spare_part_orders_ibfk_3` (`OrderStatusID`),
  ADD KEY `idx_mold` (`MoldID`);

--
-- Indexes for table `tbl_stations`
--
ALTER TABLE `tbl_stations`
  ADD PRIMARY KEY (`StationID`),
  ADD UNIQUE KEY `StationName` (`StationName`);

--
-- Indexes for table `tbl_stock_transactions`
--
ALTER TABLE `tbl_stock_transactions`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `idx_stock_part` (`PartID`),
  ADD KEY `idx_stock_from_station` (`FromStationID`),
  ADD KEY `idx_stock_to_station` (`ToStationID`),
  ADD KEY `idx_stock_pallet_type` (`PalletTypeID`),
  ADD KEY `idx_stock_deviation` (`DeviationID`),
  ADD KEY `idx_stock_createdby` (`CreatedBy`),
  ADD KEY `idx_stock_transaction_date` (`TransactionDate`),
  ADD KEY `idx_st_operator` (`OperatorEmployeeID`),
  ADD KEY `idx_stock_status_after_id` (`StatusAfterID`),
  ADD KEY `idx_stock_transaction_type` (`TransactionTypeID`),
  ADD KEY `fk_st_sender_employee` (`SenderEmployeeID`),
  ADD KEY `fk_st_receiver` (`ReceiverID`);

--
-- Indexes for table `tbl_task_statuses`
--
ALTER TABLE `tbl_task_statuses`
  ADD PRIMARY KEY (`TaskStatusID`),
  ADD UNIQUE KEY `StatusName` (`StatusName`);

--
-- Indexes for table `tbl_transaction_types`
--
ALTER TABLE `tbl_transaction_types`
  ADD PRIMARY KEY (`TypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `tbl_units`
--
ALTER TABLE `tbl_units`
  ADD PRIMARY KEY (`UnitID`),
  ADD UNIQUE KEY `UnitName` (`UnitName`),
  ADD UNIQUE KEY `Symbol` (`Symbol`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `idx_employee` (`EmployeeID`),
  ADD KEY `idx_role` (`RoleID`);

--
-- Indexes for table `tbl_warehouses`
--
ALTER TABLE `tbl_warehouses`
  ADD PRIMARY KEY (`WarehouseID`),
  ADD UNIQUE KEY `WarehouseName` (`WarehouseName`),
  ADD KEY `tbl_warehouses_ibfk_1` (`WarehouseTypeID`);

--
-- Indexes for table `tbl_warehouse_types`
--
ALTER TABLE `tbl_warehouse_types`
  ADD PRIMARY KEY (`WarehouseTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_absences`
--
ALTER TABLE `tbl_absences`
  MODIFY `AbsenceID` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_assembly_log_entries`
--
ALTER TABLE `tbl_assembly_log_entries`
  MODIFY `AssemblyEntryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tbl_assembly_log_header`
--
ALTER TABLE `tbl_assembly_log_header`
  MODIFY `AssemblyHeaderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_bom_structure`
--
ALTER TABLE `tbl_bom_structure`
  MODIFY `BomID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_break_times`
--
ALTER TABLE `tbl_break_times`
  MODIFY `BreakID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_chemicals`
--
ALTER TABLE `tbl_chemicals`
  MODIFY `ChemicalID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_chemical_types`
--
ALTER TABLE `tbl_chemical_types`
  MODIFY `ChemicalTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_contractors`
--
ALTER TABLE `tbl_contractors`
  MODIFY `ContractorID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_daily_man_hours`
--
ALTER TABLE `tbl_daily_man_hours`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_departments`
--
ALTER TABLE `tbl_departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_downtimereasons`
--
ALTER TABLE `tbl_downtimereasons`
  MODIFY `ReasonID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tbl_downtime_log`
--
ALTER TABLE `tbl_downtime_log`
  MODIFY `LogID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_employees`
--
ALTER TABLE `tbl_employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `tbl_engineering_changes`
--
ALTER TABLE `tbl_engineering_changes`
  MODIFY `ChangeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_engineering_change_feedback`
--
ALTER TABLE `tbl_engineering_change_feedback`
  MODIFY `FeedbackID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_eng_spare_parts`
--
ALTER TABLE `tbl_eng_spare_parts`
  MODIFY `PartID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT for table `tbl_eng_spare_part_transactions`
--
ALTER TABLE `tbl_eng_spare_part_transactions`
  MODIFY `TransactionID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_eng_tools`
--
ALTER TABLE `tbl_eng_tools`
  MODIFY `ToolID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `tbl_eng_tool_transactions`
--
ALTER TABLE `tbl_eng_tool_transactions`
  MODIFY `TransactionID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_eng_tool_types`
--
ALTER TABLE `tbl_eng_tool_types`
  MODIFY `ToolTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `tbl_inventory_safety_stock`
--
ALTER TABLE `tbl_inventory_safety_stock`
  MODIFY `SafetyStockID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_inventory_snapshots`
--
ALTER TABLE `tbl_inventory_snapshots`
  MODIFY `SnapshotID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_machines`
--
ALTER TABLE `tbl_machines`
  MODIFY `MachineID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `tbl_maintenance_actions`
--
ALTER TABLE `tbl_maintenance_actions`
  MODIFY `ActionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_maintenance_breakdown_types`
--
ALTER TABLE `tbl_maintenance_breakdown_types`
  MODIFY `BreakdownTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_maintenance_causes`
--
ALTER TABLE `tbl_maintenance_causes`
  MODIFY `CauseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_maintenance_reports`
--
ALTER TABLE `tbl_maintenance_reports`
  MODIFY `ReportID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_maintenance_report_entries`
--
ALTER TABLE `tbl_maintenance_report_entries`
  MODIFY `EntryID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbl_misc_categories`
--
ALTER TABLE `tbl_misc_categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_misc_inventory_transactions`
--
ALTER TABLE `tbl_misc_inventory_transactions`
  MODIFY `TransactionID` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_misc_items`
--
ALTER TABLE `tbl_misc_items`
  MODIFY `ItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_misc_item_categories`
--
ALTER TABLE `tbl_misc_item_categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_misc_transactions`
--
ALTER TABLE `tbl_misc_transactions`
  MODIFY `TransactionID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_molds`
--
ALTER TABLE `tbl_molds`
  MODIFY `MoldID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tbl_order_statuses`
--
ALTER TABLE `tbl_order_statuses`
  MODIFY `OrderStatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_packaging_configs`
--
ALTER TABLE `tbl_packaging_configs`
  MODIFY `PackageConfigID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tbl_packaging_log_details`
--
ALTER TABLE `tbl_packaging_log_details`
  MODIFY `PackagingDetailID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_packaging_log_header`
--
ALTER TABLE `tbl_packaging_log_header`
  MODIFY `PackagingHeaderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_packaging_log_shifts`
--
ALTER TABLE `tbl_packaging_log_shifts`
  MODIFY `ShiftID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_pallet_types`
--
ALTER TABLE `tbl_pallet_types`
  MODIFY `PalletTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_parts`
--
ALTER TABLE `tbl_parts`
  MODIFY `PartID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT for table `tbl_part_families`
--
ALTER TABLE `tbl_part_families`
  MODIFY `FamilyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbl_part_raw_materials`
--
ALTER TABLE `tbl_part_raw_materials`
  MODIFY `PartBomID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_part_sizes`
--
ALTER TABLE `tbl_part_sizes`
  MODIFY `SizeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `tbl_part_statuses`
--
ALTER TABLE `tbl_part_statuses`
  MODIFY `StatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tbl_part_weights`
--
ALTER TABLE `tbl_part_weights`
  MODIFY `PartWeightID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `tbl_planning_batch_compatibility`
--
ALTER TABLE `tbl_planning_batch_compatibility`
  MODIFY `CompatibilityID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tbl_planning_capacity_override`
--
ALTER TABLE `tbl_planning_capacity_override`
  MODIFY `OverrideID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_planning_mrp_results`
--
ALTER TABLE `tbl_planning_mrp_results`
  MODIFY `ResultID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_planning_mrp_run`
--
ALTER TABLE `tbl_planning_mrp_run`
  MODIFY `RunID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_planning_plating_groups`
--
ALTER TABLE `tbl_planning_plating_groups`
  MODIFY `GroupID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_planning_station_capacity_rules`
--
ALTER TABLE `tbl_planning_station_capacity_rules`
  MODIFY `RuleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_planning_work_orders`
--
ALTER TABLE `tbl_planning_work_orders`
  MODIFY `WorkOrderID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_plating_events_log`
--
ALTER TABLE `tbl_plating_events_log`
  MODIFY `EventID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_plating_log_additions`
--
ALTER TABLE `tbl_plating_log_additions`
  MODIFY `AdditionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_plating_log_details`
--
ALTER TABLE `tbl_plating_log_details`
  MODIFY `PlatingDetailID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `tbl_plating_log_header`
--
ALTER TABLE `tbl_plating_log_header`
  MODIFY `PlatingHeaderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_plating_log_shifts`
--
ALTER TABLE `tbl_plating_log_shifts`
  MODIFY `ShiftID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_plating_process_groups`
--
ALTER TABLE `tbl_plating_process_groups`
  MODIFY `GroupID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_plating_vats`
--
ALTER TABLE `tbl_plating_vats`
  MODIFY `VatID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_plating_vat_analysis`
--
ALTER TABLE `tbl_plating_vat_analysis`
  MODIFY `AnalysisID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_priorities`
--
ALTER TABLE `tbl_priorities`
  MODIFY `PriorityID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_processes`
--
ALTER TABLE `tbl_processes`
  MODIFY `ProcessID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_process_weight_changes`
--
ALTER TABLE `tbl_process_weight_changes`
  MODIFY `ProcessWeightID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_prod_daily_log_details`
--
ALTER TABLE `tbl_prod_daily_log_details`
  MODIFY `DetailID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=197;

--
-- AUTO_INCREMENT for table `tbl_prod_daily_log_header`
--
ALTER TABLE `tbl_prod_daily_log_header`
  MODIFY `HeaderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tbl_prod_downtime_details`
--
ALTER TABLE `tbl_prod_downtime_details`
  MODIFY `DetailID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `tbl_prod_downtime_header`
--
ALTER TABLE `tbl_prod_downtime_header`
  MODIFY `HeaderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbl_projects`
--
ALTER TABLE `tbl_projects`
  MODIFY `ProjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_project_tasks`
--
ALTER TABLE `tbl_project_tasks`
  MODIFY `TaskID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_quality_deviations`
--
ALTER TABLE `tbl_quality_deviations`
  MODIFY `DeviationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_raw_categories`
--
ALTER TABLE `tbl_raw_categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_raw_items`
--
ALTER TABLE `tbl_raw_items`
  MODIFY `ItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_raw_transactions`
--
ALTER TABLE `tbl_raw_transactions`
  MODIFY `TransactionID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_receivers`
--
ALTER TABLE `tbl_receivers`
  MODIFY `ReceiverID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `RoleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_rolling_log_entries`
--
ALTER TABLE `tbl_rolling_log_entries`
  MODIFY `RollingEntryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_rolling_log_header`
--
ALTER TABLE `tbl_rolling_log_header`
  MODIFY `RollingHeaderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_routes`
--
ALTER TABLE `tbl_routes`
  MODIFY `RouteID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `tbl_route_overrides`
--
ALTER TABLE `tbl_route_overrides`
  MODIFY `OverrideID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_sales_orders`
--
ALTER TABLE `tbl_sales_orders`
  MODIFY `SalesOrderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_spare_part_orders`
--
ALTER TABLE `tbl_spare_part_orders`
  MODIFY `OrderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_stations`
--
ALTER TABLE `tbl_stations`
  MODIFY `StationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbl_stock_transactions`
--
ALTER TABLE `tbl_stock_transactions`
  MODIFY `TransactionID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `tbl_task_statuses`
--
ALTER TABLE `tbl_task_statuses`
  MODIFY `TaskStatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_transaction_types`
--
ALTER TABLE `tbl_transaction_types`
  MODIFY `TypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_units`
--
ALTER TABLE `tbl_units`
  MODIFY `UnitID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_warehouses`
--
ALTER TABLE `tbl_warehouses`
  MODIFY `WarehouseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_warehouse_types`
--
ALTER TABLE `tbl_warehouse_types`
  MODIFY `WarehouseTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_absences`
--
ALTER TABLE `tbl_absences`
  ADD CONSTRAINT `tbl_absences_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`);

--
-- Constraints for table `tbl_assembly_log_entries`
--
ALTER TABLE `tbl_assembly_log_entries`
  ADD CONSTRAINT `fk_ale_header` FOREIGN KEY (`AssemblyHeaderID`) REFERENCES `tbl_assembly_log_header` (`AssemblyHeaderID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ale_machine` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`),
  ADD CONSTRAINT `fk_ale_operator1` FOREIGN KEY (`Operator1ID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ale_operator2` FOREIGN KEY (`Operator2ID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ale_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`);

--
-- Constraints for table `tbl_bom_structure`
--
ALTER TABLE `tbl_bom_structure`
  ADD CONSTRAINT `fk_bom_child` FOREIGN KEY (`ChildPartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bom_parent` FOREIGN KEY (`ParentPartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bom_req_status` FOREIGN KEY (`RequiredStatusID`) REFERENCES `tbl_part_statuses` (`StatusID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_break_times`
--
ALTER TABLE `tbl_break_times`
  ADD CONSTRAINT `fk_break_department` FOREIGN KEY (`DepartmentID`) REFERENCES `tbl_departments` (`DepartmentID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_chemicals`
--
ALTER TABLE `tbl_chemicals`
  ADD CONSTRAINT `fk_chem_type` FOREIGN KEY (`ChemicalTypeID`) REFERENCES `tbl_chemical_types` (`ChemicalTypeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chem_unit` FOREIGN KEY (`UnitID`) REFERENCES `tbl_units` (`UnitID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_daily_man_hours`
--
ALTER TABLE `tbl_daily_man_hours`
  ADD CONSTRAINT `tbl_daily_man_hours_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `tbl_departments` (`DepartmentID`);

--
-- Constraints for table `tbl_downtime_log`
--
ALTER TABLE `tbl_downtime_log`
  ADD CONSTRAINT `tbl_downtime_log_ibfk_1` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`),
  ADD CONSTRAINT `tbl_downtime_log_ibfk_2` FOREIGN KEY (`MoldID_AtTime`) REFERENCES `tbl_molds` (`MoldID`),
  ADD CONSTRAINT `tbl_downtime_log_ibfk_3` FOREIGN KEY (`ReasonID`) REFERENCES `tbl_downtimereasons` (`ReasonID`);

--
-- Constraints for table `tbl_employees`
--
ALTER TABLE `tbl_employees`
  ADD CONSTRAINT `tbl_employees_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `tbl_departments` (`DepartmentID`);

--
-- Constraints for table `tbl_engineering_changes`
--
ALTER TABLE `tbl_engineering_changes`
  ADD CONSTRAINT `fk_approved_by_employee` FOREIGN KEY (`ApprovedByEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_change_to_spare_part` FOREIGN KEY (`SparePartID`) REFERENCES `tbl_eng_spare_parts` (`PartID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_engineering_change_feedback`
--
ALTER TABLE `tbl_engineering_change_feedback`
  ADD CONSTRAINT `fk_feedback_to_change` FOREIGN KEY (`ChangeID`) REFERENCES `tbl_engineering_changes` (`ChangeID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_eng_spare_parts`
--
ALTER TABLE `tbl_eng_spare_parts`
  ADD CONSTRAINT `tbl_eng_spare_parts_ibfk_1` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`);

--
-- Constraints for table `tbl_eng_spare_part_transactions`
--
ALTER TABLE `tbl_eng_spare_part_transactions`
  ADD CONSTRAINT `fk_spt_transaction_type` FOREIGN KEY (`TransactionTypeID`) REFERENCES `tbl_transaction_types` (`TypeID`),
  ADD CONSTRAINT `fk_transaction_mold` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_eng_spare_part_transactions_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `tbl_eng_spare_parts` (`PartID`),
  ADD CONSTRAINT `tbl_eng_spare_part_transactions_ibfk_2` FOREIGN KEY (`SenderEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`),
  ADD CONSTRAINT `tbl_eng_spare_part_transactions_ibfk_3` FOREIGN KEY (`ReceiverEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`),
  ADD CONSTRAINT `tbl_eng_spare_part_transactions_ibfk_4` FOREIGN KEY (`OrderID`) REFERENCES `tbl_spare_part_orders` (`OrderID`);

--
-- Constraints for table `tbl_eng_tools`
--
ALTER TABLE `tbl_eng_tools`
  ADD CONSTRAINT `fk_tool_department_eng` FOREIGN KEY (`DepartmentID`) REFERENCES `tbl_departments` (`DepartmentID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tool_tooltype` FOREIGN KEY (`ToolTypeID`) REFERENCES `tbl_eng_tool_types` (`ToolTypeID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_eng_tool_transactions`
--
ALTER TABLE `tbl_eng_tool_transactions`
  ADD CONSTRAINT `fk_tran_receiver` FOREIGN KEY (`ReceiverEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tran_sender` FOREIGN KEY (`SenderEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tran_tool` FOREIGN KEY (`ToolID`) REFERENCES `tbl_eng_tools` (`ToolID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tran_trantype` FOREIGN KEY (`TransactionTypeID`) REFERENCES `tbl_transaction_types` (`TypeID`);

--
-- Constraints for table `tbl_family_status_compatibility`
--
ALTER TABLE `tbl_family_status_compatibility`
  ADD CONSTRAINT `fk_fsc_family` FOREIGN KEY (`FamilyID`) REFERENCES `tbl_part_families` (`FamilyID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fsc_status` FOREIGN KEY (`StatusID`) REFERENCES `tbl_part_statuses` (`StatusID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_inventory_safety_stock`
--
ALTER TABLE `tbl_inventory_safety_stock`
  ADD CONSTRAINT `fk_ss_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ss_station` FOREIGN KEY (`StationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ss_status` FOREIGN KEY (`StatusID`) REFERENCES `tbl_part_statuses` (`StatusID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_inventory_snapshots`
--
ALTER TABLE `tbl_inventory_snapshots`
  ADD CONSTRAINT `fk_snapshot_family` FOREIGN KEY (`FilterFamilyID`) REFERENCES `tbl_part_families` (`FamilyID`),
  ADD CONSTRAINT `fk_snapshot_part` FOREIGN KEY (`FilterPartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_snapshot_status` FOREIGN KEY (`FilterStatusID`) REFERENCES `tbl_part_statuses` (`StatusID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_snapshot_user` FOREIGN KEY (`RecordedByUserID`) REFERENCES `tbl_users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_machine_current_setup`
--
ALTER TABLE `tbl_machine_current_setup`
  ADD CONSTRAINT `tbl_machine_current_setup_ibfk_1` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`),
  ADD CONSTRAINT `tbl_machine_current_setup_ibfk_2` FOREIGN KEY (`CurrentMoldID`) REFERENCES `tbl_molds` (`MoldID`);

--
-- Constraints for table `tbl_machine_producible_families`
--
ALTER TABLE `tbl_machine_producible_families`
  ADD CONSTRAINT `fk_mpf_family` FOREIGN KEY (`FamilyID`) REFERENCES `tbl_part_families` (`FamilyID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mpf_machine` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_maintenance_breakdown_cause_links`
--
ALTER TABLE `tbl_maintenance_breakdown_cause_links`
  ADD CONSTRAINT `fk_link_breakdown` FOREIGN KEY (`BreakdownTypeID`) REFERENCES `tbl_maintenance_breakdown_types` (`BreakdownTypeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_link_cause` FOREIGN KEY (`CauseID`) REFERENCES `tbl_maintenance_causes` (`CauseID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_maintenance_cause_action_links`
--
ALTER TABLE `tbl_maintenance_cause_action_links`
  ADD CONSTRAINT `fk_link_action` FOREIGN KEY (`ActionID`) REFERENCES `tbl_maintenance_actions` (`ActionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_link_cause_to_action` FOREIGN KEY (`CauseID`) REFERENCES `tbl_maintenance_causes` (`CauseID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_maintenance_reports`
--
ALTER TABLE `tbl_maintenance_reports`
  ADD CONSTRAINT `fk_report_mold` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_maintenance_report_entries`
--
ALTER TABLE `tbl_maintenance_report_entries`
  ADD CONSTRAINT `fk_entry_action` FOREIGN KEY (`ActionID`) REFERENCES `tbl_maintenance_actions` (`ActionID`),
  ADD CONSTRAINT `fk_entry_breakdown` FOREIGN KEY (`BreakdownTypeID`) REFERENCES `tbl_maintenance_breakdown_types` (`BreakdownTypeID`),
  ADD CONSTRAINT `fk_entry_cause` FOREIGN KEY (`CauseID`) REFERENCES `tbl_maintenance_causes` (`CauseID`),
  ADD CONSTRAINT `fk_entry_report` FOREIGN KEY (`ReportID`) REFERENCES `tbl_maintenance_reports` (`ReportID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_misc_inventory_transactions`
--
ALTER TABLE `tbl_misc_inventory_transactions`
  ADD CONSTRAINT `fk_misc_tran_item` FOREIGN KEY (`ItemID`) REFERENCES `tbl_misc_items` (`ItemID`),
  ADD CONSTRAINT `fk_misc_tran_operator` FOREIGN KEY (`OperatorEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_misc_tran_type` FOREIGN KEY (`TransactionTypeID`) REFERENCES `tbl_transaction_types` (`TypeID`),
  ADD CONSTRAINT `fk_misc_tran_user` FOREIGN KEY (`CreatedByUserID`) REFERENCES `tbl_users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_misc_items`
--
ALTER TABLE `tbl_misc_items`
  ADD CONSTRAINT `fk_miscitem_category` FOREIGN KEY (`CategoryID`) REFERENCES `tbl_misc_categories` (`CategoryID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_miscitem_unit` FOREIGN KEY (`UnitID`) REFERENCES `tbl_units` (`UnitID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_misc_transactions`
--
ALTER TABLE `tbl_misc_transactions`
  ADD CONSTRAINT `fk_misctx_item` FOREIGN KEY (`ItemID`) REFERENCES `tbl_misc_items` (`ItemID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_misctx_operator` FOREIGN KEY (`OperatorEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_misctx_type` FOREIGN KEY (`TransactionTypeID`) REFERENCES `tbl_transaction_types` (`TypeID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_mold_machine_compatibility`
--
ALTER TABLE `tbl_mold_machine_compatibility`
  ADD CONSTRAINT `tbl_mold_machine_compatibility_ibfk_1` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`),
  ADD CONSTRAINT `tbl_mold_machine_compatibility_ibfk_2` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`);

--
-- Constraints for table `tbl_mold_producible_parts`
--
ALTER TABLE `tbl_mold_producible_parts`
  ADD CONSTRAINT `fk_mpp_mold` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mpp_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_packaging_configs`
--
ALTER TABLE `tbl_packaging_configs`
  ADD CONSTRAINT `fk_packaging_config_size` FOREIGN KEY (`SizeID`) REFERENCES `tbl_part_sizes` (`SizeID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_packaging_log_details`
--
ALTER TABLE `tbl_packaging_log_details`
  ADD CONSTRAINT `fk_pld_header` FOREIGN KEY (`PackagingHeaderID`) REFERENCES `tbl_packaging_log_header` (`PackagingHeaderID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pld_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_packaging_log_shifts`
--
ALTER TABLE `tbl_packaging_log_shifts`
  ADD CONSTRAINT `fk_pls_pkg_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pls_pkg_header` FOREIGN KEY (`PackagingHeaderID`) REFERENCES `tbl_packaging_log_header` (`PackagingHeaderID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_parts`
--
ALTER TABLE `tbl_parts`
  ADD CONSTRAINT `fk_part_to_family` FOREIGN KEY (`FamilyID`) REFERENCES `tbl_part_families` (`FamilyID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_part_to_size` FOREIGN KEY (`SizeID`) REFERENCES `tbl_part_sizes` (`SizeID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_part_plating_groups`
--
ALTER TABLE `tbl_part_plating_groups`
  ADD CONSTRAINT `fk_ppg_group` FOREIGN KEY (`GroupID`) REFERENCES `tbl_plating_process_groups` (`GroupID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ppg_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_part_raw_materials`
--
ALTER TABLE `tbl_part_raw_materials`
  ADD CONSTRAINT `fk_bom_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bom_raw_material` FOREIGN KEY (`RawMaterialItemID`) REFERENCES `tbl_raw_items` (`ItemID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_part_sizes`
--
ALTER TABLE `tbl_part_sizes`
  ADD CONSTRAINT `fk_size_to_family` FOREIGN KEY (`FamilyID`) REFERENCES `tbl_part_families` (`FamilyID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_part_weights`
--
ALTER TABLE `tbl_part_weights`
  ADD CONSTRAINT `tbl_part_weights_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_planning_batch_compatibility`
--
ALTER TABLE `tbl_planning_batch_compatibility`
  ADD CONSTRAINT `tbl_planning_batch_compatibility_ibfk_1` FOREIGN KEY (`PrimaryPartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_planning_batch_compatibility_ibfk_2` FOREIGN KEY (`CompatiblePartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_planning_capacity_override`
--
ALTER TABLE `tbl_planning_capacity_override`
  ADD CONSTRAINT `FK_planning_override_station` FOREIGN KEY (`StationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_planning_override_user` FOREIGN KEY (`LastUpdatedBy`) REFERENCES `tbl_users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_planning_mrp_results`
--
ALTER TABLE `tbl_planning_mrp_results`
  ADD CONSTRAINT `fk_mrp_result_run` FOREIGN KEY (`RunID`) REFERENCES `tbl_planning_mrp_run` (`RunID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_planning_mrp_run`
--
ALTER TABLE `tbl_planning_mrp_run`
  ADD CONSTRAINT `fk_mrp_run_user` FOREIGN KEY (`RunByUserID`) REFERENCES `tbl_users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_planning_part_to_group`
--
ALTER TABLE `tbl_planning_part_to_group`
  ADD CONSTRAINT `tbl_planning_part_to_group_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_planning_part_to_group_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `tbl_planning_plating_groups` (`GroupID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_planning_station_capacity_rules`
--
ALTER TABLE `tbl_planning_station_capacity_rules`
  ADD CONSTRAINT `FK_planning_rules_station` FOREIGN KEY (`StationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_capacity_rule_machine` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_capacity_rule_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_planning_vibration_incompatibility`
--
ALTER TABLE `tbl_planning_vibration_incompatibility`
  ADD CONSTRAINT `fk_vib_incompatible_part` FOREIGN KEY (`IncompatiblePartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vib_primary_part` FOREIGN KEY (`PrimaryPartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_planning_work_orders`
--
ALTER TABLE `tbl_planning_work_orders`
  ADD CONSTRAINT `fk_wo_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`),
  ADD CONSTRAINT `fk_wo_run` FOREIGN KEY (`RunID`) REFERENCES `tbl_planning_mrp_run` (`RunID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wo_station` FOREIGN KEY (`StationID`) REFERENCES `tbl_stations` (`StationID`);

--
-- Constraints for table `tbl_plating_compatibility`
--
ALTER TABLE `tbl_plating_compatibility`
  ADD CONSTRAINT `fk_pc_compatible` FOREIGN KEY (`CompatiblePartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pc_primary` FOREIGN KEY (`PrimaryPartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_plating_events_log`
--
ALTER TABLE `tbl_plating_events_log`
  ADD CONSTRAINT `tbl_plating_events_log_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`);

--
-- Constraints for table `tbl_plating_log_additions`
--
ALTER TABLE `tbl_plating_log_additions`
  ADD CONSTRAINT `fk_pla_chemical` FOREIGN KEY (`ChemicalID`) REFERENCES `tbl_chemicals` (`ChemicalID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pla_header` FOREIGN KEY (`PlatingHeaderID`) REFERENCES `tbl_plating_log_header` (`PlatingHeaderID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_plating_log_details`
--
ALTER TABLE `tbl_plating_log_details`
  ADD CONSTRAINT `tbl_plating_log_details_ibfk_1` FOREIGN KEY (`PlatingHeaderID`) REFERENCES `tbl_plating_log_header` (`PlatingHeaderID`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_plating_log_details_ibfk_2` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`);

--
-- Constraints for table `tbl_plating_log_shifts`
--
ALTER TABLE `tbl_plating_log_shifts`
  ADD CONSTRAINT `fk_pls_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pls_header` FOREIGN KEY (`PlatingHeaderID`) REFERENCES `tbl_plating_log_header` (`PlatingHeaderID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_plating_vat_analysis`
--
ALTER TABLE `tbl_plating_vat_analysis`
  ADD CONSTRAINT `fk_pva_vat` FOREIGN KEY (`VatID`) REFERENCES `tbl_plating_vats` (`VatID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_process_weight_changes`
--
ALTER TABLE `tbl_process_weight_changes`
  ADD CONSTRAINT `tbl_process_weight_changes_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_process_weight_changes_ibfk_2` FOREIGN KEY (`FromStationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_process_weight_changes_ibfk_3` FOREIGN KEY (`ToStationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_prod_daily_log_details`
--
ALTER TABLE `tbl_prod_daily_log_details`
  ADD CONSTRAINT `fk_log_detail_mold` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_prod_daily_log_details_ibfk_1` FOREIGN KEY (`HeaderID`) REFERENCES `tbl_prod_daily_log_header` (`HeaderID`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_prod_daily_log_details_ibfk_2` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`),
  ADD CONSTRAINT `tbl_prod_daily_log_details_ibfk_3` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`);

--
-- Constraints for table `tbl_prod_daily_log_header`
--
ALTER TABLE `tbl_prod_daily_log_header`
  ADD CONSTRAINT `tbl_prod_daily_log_header_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `tbl_departments` (`DepartmentID`);

--
-- Constraints for table `tbl_prod_downtime_details`
--
ALTER TABLE `tbl_prod_downtime_details`
  ADD CONSTRAINT `fk_downtime_header` FOREIGN KEY (`HeaderID`) REFERENCES `tbl_prod_downtime_header` (`HeaderID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_downtime_machine` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`),
  ADD CONSTRAINT `fk_downtime_mold` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`),
  ADD CONSTRAINT `fk_downtime_reason` FOREIGN KEY (`ReasonID`) REFERENCES `tbl_downtimereasons` (`ReasonID`);

--
-- Constraints for table `tbl_projects`
--
ALTER TABLE `tbl_projects`
  ADD CONSTRAINT `tbl_projects_ibfk_1` FOREIGN KEY (`ResponsibleEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`),
  ADD CONSTRAINT `tbl_projects_ibfk_2` FOREIGN KEY (`PriorityID`) REFERENCES `tbl_priorities` (`PriorityID`);

--
-- Constraints for table `tbl_project_tasks`
--
ALTER TABLE `tbl_project_tasks`
  ADD CONSTRAINT `tbl_project_tasks_ibfk_1` FOREIGN KEY (`ProjectID`) REFERENCES `tbl_projects` (`ProjectID`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_project_tasks_ibfk_2` FOREIGN KEY (`ResponsibleEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`),
  ADD CONSTRAINT `tbl_project_tasks_ibfk_3` FOREIGN KEY (`TaskStatusID`) REFERENCES `tbl_task_statuses` (`TaskStatusID`);

--
-- Constraints for table `tbl_quality_deviations`
--
ALTER TABLE `tbl_quality_deviations`
  ADD CONSTRAINT `tbl_quality_deviations_ibfk_1` FOREIGN KEY (`FamilyID`) REFERENCES `tbl_part_families` (`FamilyID`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_quality_deviations_ibfk_2` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_quality_deviations_ibfk_3` FOREIGN KEY (`CreatedBy`) REFERENCES `tbl_users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_raw_items`
--
ALTER TABLE `tbl_raw_items`
  ADD CONSTRAINT `fk_rawitem_category` FOREIGN KEY (`CategoryID`) REFERENCES `tbl_raw_categories` (`CategoryID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rawitem_unit` FOREIGN KEY (`UnitID`) REFERENCES `tbl_units` (`UnitID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_raw_transactions`
--
ALTER TABLE `tbl_raw_transactions`
  ADD CONSTRAINT `fk_rawtx_item` FOREIGN KEY (`ItemID`) REFERENCES `tbl_raw_items` (`ItemID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rawtx_operator` FOREIGN KEY (`OperatorEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rawtx_type` FOREIGN KEY (`TransactionTypeID`) REFERENCES `tbl_transaction_types` (`TypeID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_role_permissions`
--
ALTER TABLE `tbl_role_permissions`
  ADD CONSTRAINT `fk_permission_role` FOREIGN KEY (`RoleID`) REFERENCES `tbl_roles` (`RoleID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_rolling_log_entries`
--
ALTER TABLE `tbl_rolling_log_entries`
  ADD CONSTRAINT `fk_rle_header` FOREIGN KEY (`RollingHeaderID`) REFERENCES `tbl_rolling_log_header` (`RollingHeaderID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rle_machine` FOREIGN KEY (`MachineID`) REFERENCES `tbl_machines` (`MachineID`),
  ADD CONSTRAINT `fk_rle_operator` FOREIGN KEY (`OperatorID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rle_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`);

--
-- Constraints for table `tbl_routes`
--
ALTER TABLE `tbl_routes`
  ADD CONSTRAINT `fk_route_new_status` FOREIGN KEY (`NewStatusID`) REFERENCES `tbl_part_statuses` (`StatusID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_routes_ibfk_1` FOREIGN KEY (`FamilyID`) REFERENCES `tbl_part_families` (`FamilyID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_routes_ibfk_2` FOREIGN KEY (`FromStationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_routes_ibfk_3` FOREIGN KEY (`ToStationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_route_overrides`
--
ALTER TABLE `tbl_route_overrides`
  ADD CONSTRAINT `fk_override_deviation_optional` FOREIGN KEY (`DeviationID`) REFERENCES `tbl_quality_deviations` (`DeviationID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_override_output_status` FOREIGN KEY (`OutputStatusID`) REFERENCES `tbl_part_statuses` (`StatusID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_route_overrides_ibfk_1` FOREIGN KEY (`FamilyID`) REFERENCES `tbl_part_families` (`FamilyID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_route_overrides_ibfk_2` FOREIGN KEY (`FromStationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_route_overrides_ibfk_3` FOREIGN KEY (`ToStationID`) REFERENCES `tbl_stations` (`StationID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_route_overrides_ibfk_4` FOREIGN KEY (`DeviationID`) REFERENCES `tbl_quality_deviations` (`DeviationID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_sales_orders`
--
ALTER TABLE `tbl_sales_orders`
  ADD CONSTRAINT `fk_so_part` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_spare_part_orders`
--
ALTER TABLE `tbl_spare_part_orders`
  ADD CONSTRAINT `fk_order_mold` FOREIGN KEY (`MoldID`) REFERENCES `tbl_molds` (`MoldID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_spare_part_orders_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `tbl_eng_spare_parts` (`PartID`),
  ADD CONSTRAINT `tbl_spare_part_orders_ibfk_2` FOREIGN KEY (`ContractorID`) REFERENCES `tbl_contractors` (`ContractorID`),
  ADD CONSTRAINT `tbl_spare_part_orders_ibfk_3` FOREIGN KEY (`OrderStatusID`) REFERENCES `tbl_order_statuses` (`OrderStatusID`);

--
-- Constraints for table `tbl_stock_transactions`
--
ALTER TABLE `tbl_stock_transactions`
  ADD CONSTRAINT `fk_st_operator` FOREIGN KEY (`OperatorEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_st_receiver` FOREIGN KEY (`ReceiverID`) REFERENCES `tbl_receivers` (`ReceiverID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_st_sender_employee` FOREIGN KEY (`SenderEmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_transaction_type` FOREIGN KEY (`TransactionTypeID`) REFERENCES `tbl_transaction_types` (`TypeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaction_status_after` FOREIGN KEY (`StatusAfterID`) REFERENCES `tbl_part_statuses` (`StatusID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_stock_transactions_ibfk_1` FOREIGN KEY (`PartID`) REFERENCES `tbl_parts` (`PartID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_stock_transactions_ibfk_2` FOREIGN KEY (`FromStationID`) REFERENCES `tbl_stations` (`StationID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_stock_transactions_ibfk_3` FOREIGN KEY (`ToStationID`) REFERENCES `tbl_stations` (`StationID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_stock_transactions_ibfk_4` FOREIGN KEY (`PalletTypeID`) REFERENCES `tbl_pallet_types` (`PalletTypeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_stock_transactions_ibfk_5` FOREIGN KEY (`DeviationID`) REFERENCES `tbl_quality_deviations` (`DeviationID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_stock_transactions_ibfk_6` FOREIGN KEY (`CreatedBy`) REFERENCES `tbl_users` (`UserID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD CONSTRAINT `fk_user_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`RoleID`) REFERENCES `tbl_roles` (`RoleID`);

--
-- Constraints for table `tbl_warehouses`
--
ALTER TABLE `tbl_warehouses`
  ADD CONSTRAINT `tbl_warehouses_ibfk_1` FOREIGN KEY (`WarehouseTypeID`) REFERENCES `tbl_warehouse_types` (`WarehouseTypeID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
