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
*  iHRIS_PageFormPerson
* @package I2CE
* @subpackage Core
* @author Ally Shaban <allyshaban5@gmail.com>
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


class LBBoards_ProcessRegistration extends I2CE_PageForm{



    /**
     * Create and load data for the objects used for this form.
     *
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    protected function loadObjects() {
      $this->factory = I2CE_FormFactory::instance();
      if ($this->isPost()) {
        $primary = $this->factory->createContainer( "person" );
        $primary->load( $this->post );
      }
      else if ($this->get_exists('id')) {
        $id = $this->get('id');
        $emails = LBBoards_Module_Qualify::getPersonEmail($id);
        if(count($emails) == 0) {
          $this->userMessage("Add email into contact information before approving/rejecting");
          $this->setRedirect(  "view?id=" . $id );
        }
        if ($this->get_exists('id')) {
          $id = $this->get('id');
          if (strpos($id,'|')=== false) {
            I2CE::raiseError("Deprecated use of id variable");
            $id = $this->getForm() . '|' . $id;
          }
        } else {
          $id = $this->getForm() . '|0';
        }
        $primary = $this->factory->createContainer($id);
        $primary->populate();
        if($primary->getField("registration_status")->getDBValue() != "exam_app_status|pending") {
          $this->userMessage("Cant process this registration");
          $this->setRedirect(  "view?id=" . $id );
        }
      }
      $primary->getField("decision_date")->setOption("required", true);
      $primary->getField("registration_status")->setOption("required", true);
      $this->setObject( $primary, I2CE_PageForm::EDIT_PRIMARY);
    }

    public function getNewRegistrationNumber() {
      $searchPeople = I2CE_FormStorage::search("person",false,array(),array("-registration_number"),true);
      if($searchPeople) {
          $personObj = $this->factory->createContainer("person|".$searchPeople);
          $personObj->populate();
          $reg_num = $personObj->getField("registration_number")->getValue();
      }
      $reg_num++;
      $reg_num_length = strlen($reg_num);
      if($reg_num_length == 1) {
          $reg_num = "000".$reg_num;
      }
      if($reg_num_length == 2) {
          $reg_num = "00".$reg_num;
      }
      if($reg_num_length == 3) {
          $reg_num = "0".$reg_num;
      }
      return $reg_num;
    }

    protected function validate() {
      $registration_status = $this->getPrimary()->getField("registration_status");
        $decision_date = $this->getPrimary()->getField("decision_date");
        $reject_reason = $this->getPrimary()->getField("reject_reason");
        if (!$decision_date->getDisplayValue()) {
          $decision_date->setInvalidMessage('required');
        }
        if (!$reject_reason->getValue() && $registration_status->getDBValue() == "exam_app_status|rejected") {
          $reject_reason->setInvalidMessage('required');
        }
        if($registration_status->getDBValue() == "exam_app_status|pending") {
          $registration_status->setInvalidMessage('Status should either be accepted or rejected');
        }
        if($registration_status->getDBValue() == "") {
          $registration_status->setInvalidMessage('required');
        }
    }
    /**
     * Save the objects to the database.
     *
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
      parent::save();
      $primary = $this->factory->createContainer($this->getPrimary()->getNameId());
      $primary->populate();
      $primary->load($this->post);
      $reg_num = $primary->getField("registration_number")->getValue();
      $cadre = $primary->getField("cadre")->getDisplayValue();
      $reject_reason = $primary->getField('reject_reason')->getValue();
      $reg_status = $primary->getField("registration_status")->getDBValue();
      if(!$reg_num && $reg_status == "exam_app_status|accepted") {
          $reg_num = $this->getNewRegistrationNumber();
          $primary->getField('registration_number')->setValue($reg_num);
          $primary->getField('reject_reason')->setValue("");
      }
      $user = new I2CE_User;
      $saved = $primary->save($user);
      $emails = LBBoards_Module_Qualify::getPersonEmail($this->getPrimary()->getNameId());

      //create login account
      $email = current($emails);
      $username = $reg_num;
      $password = $primary->getField("surname")->getValue();
      $userObj = $this->factory->createContainer("user|".$username);
			$userObj->getField("username")->setValue($username);
			$userObj->getField("firstname")->setValue($primary->getField("firstname")->getValue());
			$userObj->getField("lastname")->setValue($primary->getField("surname")->getValue());
      $userObj->getField("role")->setFromDB("role|personnel");
      $userObj->getField("email")->setValue($email);
      $userObj->getField("password")->setValue($password);
      $userObj->save($user);
      $primary->populate();
      $userMapObj = $this->factory->createContainer("user_map");
      $userMapObj->setParent($this->getPrimary()->getNameId());
      $userMapObj->getField("username")->setFromDB("user|".$username);
      $userMapObj->save($user);
      
      $emails = implode(",", $emails);
      $fname = $primary->getField("firstname")->getValue();
      if($reg_status == "exam_app_status|accepted") {
        $body = "Dear $fname <br>Your request to register with the licensing board have been accepted and you have been assigned a registration number $reg_num and cadre $cadre";
        $body .= "<p>We have also created your account on the License Board online system, your username is $username and your password is $password. Please change your password after first login</p>";
      } else if($reg_status == "exam_app_status|rejected") {
        $body = "Dear $fname <br>Your request to register with the licensing board have been rejected with reason '".$reject_reason."'";
      }
      $subject = "Registration License Board";
      if($reg_status == "exam_app_status|accepted" || $reg_status == "exam_app_status|rejected") {
        LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
      }
      $this->userMessage("Registration Processed Successfully");
      $this->setRedirect(  "view?id=" . $this->getPrimary()->getNameId() );
      $primary->cleanup();
    }

    protected function displayControls( $save = false, $show_edit = true ) {
        if ( $save ) {
            $this->template->addFile( "button_save.html" );
        } elseif ( $this->getPrimary()->getId() > 0 ) {
            $this->template->addFile( "button_confirm_notchild.html" );     
        } else {
            $this->template->addFile( "button_confirm_only.html" );     
        }
    }

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
