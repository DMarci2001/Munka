<?php


//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

require_once(__DIR__ . "/config/BookingConstants_".getConfigFile().".php");
require_once(__DIR__ . "/library/sql.php");
require_once(__DIR__ . "/library/sql_common.php");
require_once(__DIR__ . "/library/Lang.php");
require_once(__DIR__ . "/library/Settings.php");
require_once(__DIR__ . "/library/Utils.php");
require_once(__DIR__ . "/library/User.php");
require_once(__DIR__ . "/library/NotificationService.php");
require_once(__DIR__ . "/library/AjaxService.php");
require_once(__DIR__ . "/library/CompanyService.php");
require_once(__DIR__ . "/library/MunkakorVizsgalatok.php");
require_once(__DIR__ . "/library/pages/CorePage.php");
require_once(__DIR__ . "/library/pages/BookingPage.php");
require_once(__DIR__ . "/library/pages/BookingListPage.php");
require_once(__DIR__ . "/library/pages/BookingSuccessfulPage.php");
require_once(__DIR__ . "/library/pages/BookingValidatePage.php");
require_once(__DIR__ . "/library/pages/BookingDeletePage.php");
require_once(__DIR__ . "/library/pages/BookingDeleteSuccessfulPage.php");
require_once(__DIR__ . "/library/pages/LoginPage.php");
require_once(__DIR__ . "/library/pages/LoginWithTajNumberPage.php");
require_once(__DIR__ . "/library/pages/RegistrationPage.php");
require_once(__DIR__ . "/library/pages/ProfilePage.php");
require_once(__DIR__ . "/library/pages/PasswordSendPage.php");
require_once(__DIR__ . "/library/pages/ValidateLoginPage.php");
require_once(__DIR__ . "/library/pages/ValidationSuccessfulPage.php");
require_once(__DIR__ . "/library/pages/DocumentsPage.php");
require_once(__DIR__ . "/library/pages/BeutalokPage.php");
require_once(__DIR__ . "/library/pages/LeletekPage.php");
require_once(__DIR__ . "/library/pages/AlkalmassagiTajekoztatoPage.php");
require_once(__DIR__ . "/library/pages/AutoertesitesleiratkozasPage.php");
require_once(__DIR__ . "/library/pages/RemoteBookingPage.php");
require_once(__DIR__ . "/library/pages/CovidFormPage.php");
require_once(__DIR__ . "/library/pages/WebFogleuPage.php");
require_once(__DIR__ . "/library/pages/OltasIgenyFelmeresPage.php");
require_once(__DIR__ . "/library/pages/OltasJelentkezesPage.php");
require_once(__DIR__ . "/library/pages/ElsosegelyVizsgaPage.php");
require_once(__DIR__ . "/library/pages/CovidOltasNaploPage.php");
require_once(__DIR__ . "/library/pages/MissingDataPage.php");
require_once(__DIR__ . "/library/pages/ServicesPage.php");
require_once(__DIR__ . "/library/Page.php");
require_once(__DIR__ . "/library/DocAgent.php");
require_once(__DIR__ . "/library/BookingService.php");
require_once(__DIR__ . "/library/BeosztasService.php");
require_once(__DIR__ . "/library/PrintService.php");
require_once(__DIR__ . "/library/ZeusService.php");
require_once(__DIR__ . "/library/foglaljorvost/FoGeneral.php");
require_once(__DIR__ . "/library/foglaljorvost/FoglaljOrvostService.php");
require_once(__DIR__ . "/library/ReservationExportService.php");
require_once(__DIR__ . "/library/DokirexService.php");
require_once(__DIR__ . "/library/DicomService.php");
require_once(__DIR__ . "/library/ExcelService.php");
require_once(__DIR__ . "/library/BookingSyncApi.php");
require_once(__DIR__ . "/library/PatientService.php");
require_once(__DIR__ . "/library/AdminUser.php");
require_once(__DIR__ . "/library/SynlabService.php");

require_once(__DIR__ . "/library/other/seeme-gateway-class.php");
require_once(__DIR__ . "/library/other/google-drive-downloader.php");
require_once(__DIR__ . "/library/other/SimplePayService.php");

//admin...

if (isset($GLOBALS["admin"])) {
    require_once(__DIR__ . "/library/AdminPage.php");
    require_once(__DIR__ . "/library/AdminUtils.php");
    require_once(__DIR__ . "/library/AdminAjaxService.php");
    require_once(__DIR__ . "/library/AdminBookingEditor.php");
    require_once(__DIR__ . "/library/AdminBeoEditor.php");
    require_once(__DIR__ . "/library/AdminLeletService.php");
    require_once(__DIR__ . "/library/AdminProtocolService.php");
    require_once(__DIR__ . "/library/pages_workschedule/WorkScheduleService.php");
    require_once(__DIR__ . "/library/salary/SalaryCalculator.php");
    require_once(__DIR__ . "/library/pages_admin/DailyStat/DailyStatService.php");
    require_once(__DIR__ . "/library/pages_admin/DailyStat/MonthlyStatService.php");

    require_once(__DIR__ . "/library/pages_admin/AdminCorePage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminReferralPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminReferalStatusQueryPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminBookingPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminLoginPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminArrivalsPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminElsosegelyVizsgaPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminCovidListPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminOnlineFogleuPage.php");
	require_once(__DIR__ . "/library/pages_admin/AdminBanktransactionsPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminPlacesPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminScreeningsPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminPatientsPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminCompaniesPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminDoctorsPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminLangSettingsPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminSettingsPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminLogPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminStatPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminRecepcioListaPage.php");
    require_once(__DIR__ . "/library/pages_admin/DailyStat/AdminDailyStatPage.php");
    require_once(__DIR__ . "/library/pages_admin/DailyStat/AdminMonthlyStatPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminUsersPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminSalaryPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminInvoicesPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminErrorPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminOltasIgenyekPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminDicomPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminLaborkeroPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminLabortetelekPage.php");
    require_once(__DIR__ . "/library/pages_workschedule/AdminWorkSchedulePage.php");
    require_once(__DIR__ . "/library/pages_workschedule/WorkersSubPage.php");
    require_once(__DIR__ . "/library/pages_workschedule/WorkplacesSubPage.php");
    require_once(__DIR__ . "/library/pages_workschedule/NotifySubPage.php");
    require_once(__DIR__ . "/library/pages_workschedule/PrintSubPage.php");
    require_once(__DIR__ . "/library/pages_admin/AdminPreBookingManagementPage.php");

    //ehhez a pluginhoz megszűnt a composer support php 7.1-hez, ezért kézzel kellett beletenni
    require_once 'vendor/mikehaertl/php-tmpfile/src/File.php';
    require_once 'vendor/mikehaertl/php-shellcommand/src/Command.php';
    require_once 'vendor/mikehaertl/php-pdftk/src/Command.php';
    require_once 'vendor/mikehaertl/php-pdftk/src/DataFields.php';
    require_once 'vendor/mikehaertl/php-pdftk/src/FdfFile.php';
    require_once 'vendor/mikehaertl/php-pdftk/src/InfoFields.php';
    require_once 'vendor/mikehaertl/php-pdftk/src/Pdf.php';
    require_once 'vendor/mikehaertl/php-pdftk/src/XfdfFile.php';
    //ehhez a pluginhoz megszűnt a composer support php 7.1-hez, ezért kézzel kellett beletenni
}

function getConfigFile() {
    $config = "";

    if (isset($_GET["config"])) {
        $config = $_GET["config"];
    }

    if (isset($_SERVER["HTTP_HOST"])) {
        $host = $_SERVER["HTTP_HOST"];

        if (substr_count($host, "hungariamed.hu") || substr_count($host, "jns.hu")) {
            $config = "hmm";
            if (substr_count($host, "demo.hungariamed.hu")) {
                $config = "demo";
            }
            if (substr_count($host, "marciteszt.hungariamed.hu")) {
                $config = "hmm";
            }
        }
        if (substr_count($host, "keltexmed.")) {
            $config = "keltexmed";
        }
    }

    if (empty($config)) {
        die("Error loading config!");
    }

    return $config;
}