<?php
# get2post.php
# 
# Copyright (C) 2016  Matt Florell <vicidial@gmail.com>    LICENSE: AGPLv2
#
# This script is designed to take a url as part of the query string and convert 
# it to a POST HTTP request and log to the url log table. uniqueid is required!
# Example Dispo Call URL input:
# VARhttp://127.0.0.1/agc/get2post.php?uniqueid=--A--uniqueid--B--&type=dispo&HTTPURLTOPOST=127.0.0.1/agc/vdc_call_url_test.php?lead_id=--A--lead_id--B--
# VARhttp://127.0.0.1/agc/get2post.php?uniqueid=--A--uniqueid--B--&type=start&HTTPSURLTOPOST=127.0.0.1/agc/vdc_call_url_test.php?lead_id=--A--lead_id--B--
#
# CHANGELOG:
# 160302-1159 - First build of script
#

$version = '2.12-1';
$build = '160302-1159';

require("dbconnect_mysqli.php");
require("functions.php");

$query_string = getenv("QUERY_STRING");
$request_uri = getenv("REQUEST_URI");

if (isset($_GET["uniqueid"]))			{$uniqueid=$_GET["uniqueid"];}
	elseif (isset($_POST["uniqueid"]))	{$uniqueid=$_POST["uniqueid"];}
if (isset($_GET["type"]))				{$type=$_GET["type"];}
	elseif (isset($_POST["type"]))		{$type=$_POST["type"];}
if (isset($_GET["DB"]))					{$DB=$_GET["DB"];}
	elseif (isset($_POST["DB"]))		{$DB=$_POST["DB"];}

header ("Content-type: text/html; charset=utf-8");
header ("Cache-Control: no-cache, must-revalidate");  // HTTP/1.1
header ("Pragma: no-cache");                          // HTTP/1.0

$txt = '.txt';
$StarTtime = date("U");
$NOW_DATE = date("Y-m-d");
$NOW_TIME = date("Y-m-d H:i:s");
$CIDdate = date("mdHis");
$ENTRYdate = date("YmdHis");
$MT[0]='';


#############################################
##### START SYSTEM_SETTINGS LOOKUP #####
$stmt = "SELECT use_non_latin,webroot_writable FROM system_settings;";
$rslt=mysql_to_mysqli($stmt, $link);
if ($DB) {echo "$stmt\n";}
$qm_conf_ct = mysqli_num_rows($rslt);
if ($qm_conf_ct > 0)
	{
	$row=mysqli_fetch_row($rslt);
	$non_latin =				$row[0];
	$webroot_writable =			$row[1];
	}
##### END SETTINGS LOOKUP #####
###########################################

$uniqueid=preg_replace('/[^-\.\_0-9a-zA-Z]/',"",$uniqueid);
$type=preg_replace('/[^-\_0-9a-zA-Z]/',"",$type);
$DB=preg_replace('/[^0-9]/',"",$DB);

# default optional vars if not set
if (!isset($type))   {$type="get2post";}

if (strlen($uniqueid) < 10)
	{print "ERROR: uniqueid is not valid";   exit;}

if (preg_match("/HTTPURLTOPOST=|HTTPSURLTOPOST=/",$query_string))
	{
	$post_url='';
	$curl_ready=0;
	if (preg_match("/HTTPURLTOPOST=/",$query_string))
		{
		$post_url_prep = explode('HTTPURLTOPOST=',$query_string);
		$post_url = "http://" . $post_url_prep[1];
		$post_page = $post_url;
		$post_vars='';
		if (preg_match("/\?/",$post_url))
			{
			$post_var_prep = explode('?',$post_url);
			$post_page = $post_var_prep[0];
			$post_vars = $post_var_prep[1];
			}
		$curl_ready++;
		}
	if (preg_match("/HTTPSURLTOPOST/",$query_string))
		{
		$post_url_prep = explode('HTTPSURLTOPOST=',$query_string);
		$post_url = "https://" . $post_url_prep[1];
		$post_page = $post_url;
		$post_vars='';
		if (preg_match("/\?/",$post_url))
			{
			$post_var_prep = explode('?',$post_url);
			$post_page = $post_var_prep[0];
			$post_vars = $post_var_prep[1];
			}
		$curl_ready++;
		}
	if ( ($curl_ready > 0) and (strlen($post_page) > 8) )
		{
		### insert a new url log entry
		$SQL_log = "$post_url";
		$SQL_log = preg_replace('/;/','',$SQL_log);
		$SQL_log = addslashes($SQL_log);
		$stmt = "INSERT INTO vicidial_url_log SET uniqueid='$uniqueid',url_date='$NOW_TIME',url_type='$type',url='$SQL_log',url_response='';";
		if ($DB) {echo "$stmt\n";}
		$rslt=mysql_to_mysqli($stmt, $link);
		$affected_rows = mysqli_affected_rows($link);
		$url_id = mysqli_insert_id($link);

		$URLstart_sec = date("U");

		# use cURL to call the copy custom fields code
		$curl = curl_init();

		# Set some options - we are passing in a useragent too here
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_URL => $post_page,
			CURLOPT_POSTFIELDS => $post_vars,
			CURLOPT_USERAGENT => 'VICIdial get2post'
		));

		# Send the request & save response to $resp
		$resp = curl_exec($curl);

		# Close request to clear up some resources
		curl_close($curl);
		
		if ($DB) 
			{
			echo "|$uniqueid|$type|<br>\n";
			echo "|$query_string|<br>\n";
			echo "|$post_url|<br>\n";
			echo "|$post_page|<br>\n";
			echo "|$post_vars|<br>\n";
			echo "|$resp|<br>\n";
			}


		### update url log entry
		$URLend_sec = date("U");
		$URLdiff_sec = ($URLend_sec - $URLstart_sec);

		$stmt = "UPDATE vicidial_url_log SET response_sec='$URLdiff_sec',url_response='$resp' where url_log_id='$url_id';";
		if ($DB) {echo "$stmt\n";}
		$rslt=mysql_to_mysqli($stmt, $link);
			if ($mel > 0) {mysql_error_logging($NOW_TIME,$link,$mel,$stmt,'00422',$user,$server_ip,$session_name,$one_mysql_log);}
		$affected_rows = mysqli_affected_rows($link);

		}
	else
		{print "ERROR: post url is invalid";   exit;}
	}
else
	{print "ERROR: post url is not populated";   exit;}


#$output = '';
#$output .= "$uniqueid|$type|$DB|";

if (strlen($resp) > 0)
	{echo "$resp";}

if ($webroot_writable > 0)
	{
	$fp = fopen ("./get2post.txt", "a");
	fwrite ($fp, "$output|$query_string\n");
	fclose($fp);
	}

exit;

?>
