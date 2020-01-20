<?php
/**
* © Copyright 2007 IntraHealth International, Inc.
* 
* This File is part of I2CE 
* 
* I2CE is free software; you can redistribute it and/or modify 
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
*  iHRIS_Module_Qualify
* @package I2CE
* @subpackage Core
* @author Carl Leitner <litlfred@ibiblio.org>
* @copyright Copyright &copy; 2007 IntraHealth International, Inc. 
* This file is part of I2CE. I2CE is free software; you can redistribute it and/or modify it under 
* the terms of the GNU General Public License as published by the Free Software Foundation; either 
* version 3 of the License, or (at your option) any later version. I2CE is distributed in the hope 
* that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
* or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have 
* received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
* @version 2.1
* @access public
*/


class LBBoards_Module_Qualify extends I2CE_Module {
  static public function isRegistered($person) {
    $factory = I2CE_FormFactory::instance();
    $personObj = $factory->createContainer($person);
    $personObj->populate();
    $registration_status = $personObj->getField("registration_status")->getDBValue();
    if($registration_status != "exam_app_status|accepted") {
      return false;
    } else {
      return true;
    }
  }
  static public function sendEmail($to,$subject, $body) {
    if(!$to) {
      I2CE::raiseError("Missing email address, stop sending this email");
      return false;
    }
    if(!$subject) {
      I2CE::raiseError("Missing subject, stop sending this email");
      return false;
    }
    if(!$body) {
      I2CE::raiseError("Missing message body, stop sending this email");
      return false;
    }
    error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_STRICT);

    set_include_path("." . PATH_SEPARATOR . ($UserDir = dirname($_SERVER['DOCUMENT_ROOT'])) . "/pear/php" . PATH_SEPARATOR . get_include_path());
    require_once "Mail.php";

    $factory = I2CE_FormFactory::instance();
    $smtp = I2CE_FormStorage::search("mail_smtp", false, array(), array("created"), true);
    if(!$smtp) {
      I2CE::raiseError("No SMTP Configuration found, stop sending email");
      return false;
    }
    $smtpObj = $factory->createContainer("mail_smtp|".$smtp);
    $smtpObj->populate();
    $host = $smtpObj->getField("host")->getValue();
    $username = $smtpObj->getField("username")->getValue();
    $password = $smtpObj->getField("password")->getValue();
    $port = $smtpObj->getField("port")->getValue();
    $from = $smtpObj->getField("from")->getValue();
    $reply_to = $smtpObj->getField("reply_to")->getValue();

    $headers = array (
      'From' => "Licensing Board<".$from.">", 
      'To' => $to, 
      'Subject' => $subject, 
      'Reply-To' => $reply_to,
      'Content-Type' => 'text/html; charset=UTF-8\r\n'
    );
    $smtp = Mail::factory(
      'smtp', 
      array (
        'host' => $host, 
        'port' => $port, 
        'auth' => true, 
        'username' => $username, 
        'password' => $password
      )
    );
    $mail = $smtp->send($to, $headers, $body);


    if (PEAR::isError($mail)) {
      I2CE::raiseError($mail->getMessage());
      return false;
    } else {
      I2CE::raiseError("Email successfully sent!");
      return true;
    }
  }

  static public function getPersonEmail($person) {
    $factory = I2CE_FormFactory::instance();
    $personObj = $factory->createContainer($person);
    $contactForms = array("person_contact_personal","person_contact_work","person_contact_emergency","person_contact_other");
    $emails = array();
    foreach($contactForms as $contact) {
      $personObj->populateChildren($contact);
      if(!array_key_exists($contact, $personObj->children)) {
        continue;
      }
      $obj = current($personObj->children[$contact]);
      if($obj) {
        $email = $obj->getField("email")->getValue();
        if($email) {
          $emails[] = $email;
        }
      }
    }
    return $emails;
  }

  static public function getEmailByRole($roles) {
    $factory = I2CE_FormFactory::instance();
    $where = array(
      "operator" => "FIELD_LIMIT",
      "field" => "role",
      "style" => "in",
      "data" => array("value" => $roles)
    );
    $users = I2CE_FormStorage::search("user", false, $where);
    $emails = array();
    foreach($users as $user) {
      $userObj = $factory->createContainer("user|".$user);
      $userObj->populate();
      $email = $userObj->getField("email")->getDBValue();
      if($email) {
        $emails[] = $email;
      }
    }
    return $emails;
  }
}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
