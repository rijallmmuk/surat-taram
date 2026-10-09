-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.11.14-MariaDB-0ubuntu0.24.04.1 - Ubuntu 24.04
-- Server OS:                    debian-linux-gnu
-- HeidiSQL Version:             12.18.1.1
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table opensid.tweb_penduduk_agama
CREATE TABLE IF NOT EXISTS `tweb_penduduk_agama` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table opensid.tweb_penduduk_agama: ~7 rows (approximately)
INSERT INTO `tweb_penduduk_agama` (`id`, `nama`) VALUES
	(1, 'ISLAM'),
	(2, 'KRISTEN'),
	(3, 'KATHOLIK'),
	(4, 'HINDU'),
	(5, 'BUDHA'),
	(6, 'KHONGHUCU'),
	(7, 'Kepercayaan Terhadap Tuhan YME / Lainnya');

-- Dumping structure for table opensid.tweb_penduduk_hubungan
CREATE TABLE IF NOT EXISTS `tweb_penduduk_hubungan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table opensid.tweb_penduduk_hubungan: ~11 rows (approximately)
INSERT INTO `tweb_penduduk_hubungan` (`id`, `nama`) VALUES
	(1, 'KEPALA KELUARGA'),
	(2, 'SUAMI'),
	(3, 'ISTRI'),
	(4, 'ANAK'),
	(5, 'MENANTU'),
	(6, 'CUCU'),
	(7, 'ORANGTUA'),
	(8, 'MERTUA'),
	(9, 'FAMILI LAIN'),
	(10, 'PEMBANTU'),
	(11, 'LAINNYA');

-- Dumping structure for table opensid.tweb_penduduk_kawin
CREATE TABLE IF NOT EXISTS `tweb_penduduk_kawin` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table opensid.tweb_penduduk_kawin: ~4 rows (approximately)
INSERT INTO `tweb_penduduk_kawin` (`id`, `nama`) VALUES
	(1, 'BELUM KAWIN'),
	(2, 'KAWIN'),
	(3, 'CERAI HIDUP'),
	(4, 'CERAI MATI');

-- Dumping structure for table opensid.tweb_penduduk_pekerjaan
CREATE TABLE IF NOT EXISTS `tweb_penduduk_pekerjaan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table opensid.tweb_penduduk_pekerjaan: ~89 rows (approximately)
INSERT INTO `tweb_penduduk_pekerjaan` (`id`, `nama`) VALUES
	(1, 'BELUM/TIDAK BEKERJA'),
	(2, 'MENGURUS RUMAH TANGGA'),
	(3, 'PELAJAR/MAHASISWA'),
	(4, 'PENSIUNAN'),
	(5, '5'),
	(6, 'TENTARA NASIONAL INDONESIA (TNI)'),
	(7, 'KEPOLISIAN RI (POLRI)'),
	(8, 'PERDAGANGAN'),
	(9, 'PETANI/PEKEBUN'),
	(10, 'PETERNAK'),
	(11, 'NELAYAN/PERIKANAN'),
	(12, 'INDUSTRI'),
	(13, 'KONSTRUKSI'),
	(14, 'TRANSPORTASI'),
	(15, 'KARYAWAN SWASTA'),
	(16, 'KARYAWAN BUMN'),
	(17, 'KARYAWAN BUMD'),
	(18, 'KARYAWAN HONORER'),
	(19, 'BURUH HARIAN LEPAS'),
	(20, 'BURUH TANI/PERKEBUNAN'),
	(21, 'BURUH NELAYAN/PERIKANAN'),
	(22, 'BURUH PETERNAKAN'),
	(23, 'PEMBANTU RUMAH TANGGA'),
	(24, 'TUKANG CUKUR'),
	(25, 'TUKANG LISTRIK'),
	(26, 'TUKANG BATU'),
	(27, 'TUKANG KAYU'),
	(28, 'TUKANG SOL SEPATU'),
	(29, 'TUKANG LAS/PANDAI BESI'),
	(30, 'TUKANG JAHIT'),
	(31, 'TUKANG GIGI'),
	(32, 'PENATA RIAS'),
	(33, 'PENATA BUSANA'),
	(34, 'PENATA RAMBUT'),
	(35, 'MEKANIK'),
	(36, 'SENIMAN'),
	(37, 'TABIB'),
	(38, 'PARAJI'),
	(39, 'PERANCANG BUSANA'),
	(40, 'PENTERJEMAH'),
	(41, 'IMAM MASJID'),
	(42, 'PENDETA'),
	(43, 'PASTOR'),
	(44, 'WARTAWAN'),
	(45, 'USTADZ/MUBALIGH'),
	(46, 'JURU MASAK'),
	(47, 'PROMOTOR ACARA'),
	(48, 'ANGGOTA DPR-RI'),
	(49, 'ANGGOTA DPD'),
	(50, 'ANGGOTA BPK'),
	(51, 'PRESIDEN'),
	(52, 'WAKIL PRESIDEN'),
	(53, 'ANGGOTA MAHKAMAH KONSTITUSI'),
	(54, 'ANGGOTA KABINET KEMENTERIAN'),
	(55, 'DUTA BESAR'),
	(56, 'GUBERNUR'),
	(57, 'WAKIL GUBERNUR'),
	(58, 'BUPATI'),
	(59, 'WAKIL BUPATI'),
	(60, 'WALIKOTA'),
	(61, 'WAKIL WALIKOTA'),
	(62, 'ANGGOTA DPRD PROVINSI'),
	(63, 'ANGGOTA DPRD KABUPATEN/KOTA'),
	(64, 'DOSEN'),
	(65, 'GURU'),
	(66, 'PILOT'),
	(67, 'PENGACARA'),
	(68, 'NOTARIS'),
	(69, 'ARSITEK'),
	(70, 'AKUNTAN'),
	(71, 'KONSULTAN'),
	(72, 'DOKTER'),
	(73, 'BIDAN'),
	(74, 'PERAWAT'),
	(75, 'APOTEKER'),
	(76, 'PSIKIATER/PSIKOLOG'),
	(77, 'PENYIAR TELEVISI'),
	(78, 'PENYIAR RADIO'),
	(79, 'PELAUT'),
	(80, 'PENELITI'),
	(81, 'SOPIR'),
	(82, 'PIALANG'),
	(83, 'PARANORMAL'),
	(84, 'PEDAGANG'),
	(85, 'PERANGKAT DESA'),
	(86, 'KEPALA DESA'),
	(87, 'BIARAWATI'),
	(88, 'WIRASWASTA'),
	(89, 'LAINNYA');

-- Dumping structure for table opensid.tweb_penduduk_pendidikan_kk
CREATE TABLE IF NOT EXISTS `tweb_penduduk_pendidikan_kk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table opensid.tweb_penduduk_pendidikan_kk: ~10 rows (approximately)
INSERT INTO `tweb_penduduk_pendidikan_kk` (`id`, `nama`) VALUES
	(1, 'TIDAK/BELUM SEKOLAH'),
	(2, 'BELUM TAMAT SD/SEDERAJAT'),
	(3, 'TAMAT SD/SEDERAJAT'),
	(4, 'SLTP/SEDERAJAT'),
	(5, 'SLTA/SEDERAJAT'),
	(6, 'DIPLOMA I/II'),
	(7, 'AKADEMI/DIPLOMA III/S. MUDA'),
	(8, 'DIPLOMA IV/STRATA I'),
	(9, 'STRATA II'),
	(10, 'STRATA III');

-- Dumping structure for table opensid.tweb_penduduk_sex
CREATE TABLE IF NOT EXISTS `tweb_penduduk_sex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table opensid.tweb_penduduk_sex: ~2 rows (approximately)
INSERT INTO `tweb_penduduk_sex` (`id`, `nama`) VALUES
	(1, 'Laki-laki'),
	(2, 'Perempuan');

-- Dumping structure for table opensid.tweb_penduduk_warganegara
CREATE TABLE IF NOT EXISTS `tweb_penduduk_warganegara` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table opensid.tweb_penduduk_warganegara: ~3 rows (approximately)
INSERT INTO `tweb_penduduk_warganegara` (`id`, `nama`) VALUES
	(1, 'WNI'),
	(2, 'WNA'),
	(3, 'DUA KEWARGANEGARAAN');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
