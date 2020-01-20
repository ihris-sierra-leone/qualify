#!/usr/bin/php
<?php
/*
 * © Copyright 2007, 2008 IntraHealth International, Inc.
 * 
 * This File is part of iHRIS
 * 
 * iHRIS is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * The page wrangler
 * 
 * This page loads the main HTML template for the home page of the site.
 * @package iHRIS
 * @subpackage DemoManage
 * @access public
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */



$dir = getcwd();
chdir("../pages");
$i2ce_site_user_access_init = null;
$wd = getcwd();

$i2ce_site_user_database = null;
require_once( $wd . DIRECTORY_SEPARATOR . 'config.values.php');

$local_config = $wd . DIRECTORY_SEPARATOR .'local' . DIRECTORY_SEPARATOR . 'config.values.php';
if (file_exists($local_config)) {
    require_once($local_config);
}

if(!isset($i2ce_site_i2ce_path) || !is_dir($i2ce_site_i2ce_path)) {
    echo "Please set the \$i2ce_site_i2ce_path in $local_config";
    exit(55);
}

require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');
I2CE::raiseError("Connecting to DB");
if (isset($i2ce_site_dsn)) {
    @I2CE::initializeDSN($i2ce_site_dsn,   $i2ce_site_user_access_init,    $i2ce_site_module_config);         
} else if (isset($i2ce_site_database_user)) {    
    I2CE::initialize($i2ce_site_database_user,
                     $i2ce_site_database_password,
                     $i2ce_site_database,
                     $i2ce_site_user_database,
                     $i2ce_site_module_config         
        );
} else {
    die("Do not know how to configure system\n");
}

I2CE::raiseError("Connected to DB");
$factory = I2CE_FormFactory::instance();
$user = new I2CE_User();
require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');
$expired_date1 = date("Y-m-d", strtotime("+3 months"));
$expired_date2 = date("Y-m-d", strtotime('+1 months', strtotime($expired_date1)));
//get licenses that will expire in three months
$where = array(
  "operator" => "AND",
  "operand" => array(
    0 => array(
      "operator" => "FIELD_LIMIT",
      "field" => "end_date",
      "style" => "greaterthan_equals",
      "data" => array("value" => $expired_date1)
    ),
    1 => array(
      "operator" => "FIELD_LIMIT",
      "field" => "end_date",
      "style" => "lessthan_equals",
      "data" => array("value" => $expired_date2)
    ),
    2 => array(
      "operator" => "FIELD_LIMIT",
      "field" => "advance_email_notification",
      "style" => "equals",
      "data" => array("value" => 1)
    )
  )
);

$licenses = I2CE_FormStorage::search("license", false, $where);
$license_numbers = [];
foreach($licenses as $license) {
  $licenseObj = $factory->createContainer("license|".$license);
  $licenseObj->populate();
  $personID = $licenseObj->getParent();
  $personObj = $factory->createContainer($personID);
  $personObj->populate();
  $fname = $personObj->getField("firstname")->getValue();
  $cadre = $personObj->getField("cadre")->getDisplayValue();
  $license_number = $licenseObj->getField("license_number")->getValue();
  $end_date = $licenseObj->getField("end_date")->getDisplayValue();
  $license_numbers[] = $license_number;
  $emails = LBBoards_Module_Qualify::getPersonEmail($personID);
  $emails = implode(",", $emails);
  $body = "Dear " . $fname.",<br>Your License<b> $license_number ($cadre)</b> will expire ". $end_date. " Please start the renewal process";
  $subject = "License Expiration";
  $sent = LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
  if($sent) {
    $user = new I2CE_User;
    $licenseObj->getField("advance_email_notification")->setValue("1");
    $licenseObj->save($user);
  }
}

if(count($license_numbers) > 0) {
  $license_numbers = implode("<br>", $license_numbers);
  $roles = array("role|registrar","role|registrar_officer","role|data_officer","role|admin");
  $emails = LBBoards_Module_Qualify::getEmailByRole($roles);
  $emails = implode(",", $emails);
  $body = "Hi All <br> Below licenses will expire in 3 months";
  $body .= "<p>". $license_numbers."<p>";
  $subject = "License Expiration";
  LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
}


//check expired licenses
$today = date("Y-m-d");
$where = array(
  "operator" => "AND",
  "operand" => array(
    0 => array(
      "operator" => "FIELD_LIMIT",
      "field" => "license_status",
      "style" => "equals",
      "data" => array("value" => "license_status|active")
    ),
    1 => array(
      "operator" => "FIELD_LIMIT",
      "field" => "end_date",
      "style" => "lessthan_equals",
      "data" => array("value" => $today)
    )
  )
);
$license_numbers = array();
$licenses = I2CE_FormStorage::search("license", false, $where);
foreach($licenses as $license) {
  $licenseObj = $factory->createContainer("license|".$license);
  $licenseObj->populate();
  $personID = $licenseObj->getParent();
  $personObj = $factory->createContainer($personID);
  $personObj->populate();
  $fname = $personObj->getField("firstname")->getValue();
  $cadre = $personObj->getField("cadre")->getDisplayValue();
  $license_number = $licenseObj->getField("license_number")->getValue();
  $license_numbers[] = $license_number;
  $emails = LBBoards_Module_Qualify::getPersonEmail($personID);
  $emails = implode(",", $emails);
  $end_date = $licenseObj->getField("end_date")->getDisplayValue();
  $body = "Dear " . $fname.",<br>Your License<b> $license_number ($cadre)</b> have expired since ". $end_date;
  $subject = "License Expired";
  LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
  $user = new I2CE_User;
  $licenseObj->getField("license_status")->setFromDB("license_status|expired");
  $status_date = date("Y-m-d H:i:s");
  $licenseObj->getField("status_date")->setFromDB($status_date);
  $licenseObj->save($user);
}

if(count($license_numbers) > 0) {
  $license_numbers = implode("<br>", $license_numbers);
  $roles = array("role|registrar","role|registrar_officer","role|data_officer","role|admin");
  $emails = LBBoards_Module_Qualify::getEmailByRole($roles);
  $emails = implode(",", $emails);
  $body = "Hi All <br> Below licenses have expired";
  $body .= "<p>". $license_numbers."<p>";
  $subject = "License Expired";
  LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
}
?>