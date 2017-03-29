<?php
define('WP_USE_THEMES', false);
require('../../../../wp-load.php');


include_once("classes/export-user.php");

$export		=	 new  ExportUserData();
$export->generate_data();

$reportId 	= 	"1";
$start 		=	strtotime("09/09/2016");
$end		=	time();

$_POST['customer_role']		= "Tutor";
$_REQUEST['reportType'] 	= "invoice";
$_POST ['format'] 			=	'csv';
hm_xoiwcp_run_scheduled_report($reportId, $start, $end);
$_REQUEST['reportType'] 	= "customer";
hm_xoiwcp_run_scheduled_report($reportId, $start, $end);