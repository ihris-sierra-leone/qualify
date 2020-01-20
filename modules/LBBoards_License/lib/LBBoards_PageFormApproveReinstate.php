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


class LBBoards_PageFormApproveReinstate extends iHRIS_PageFormParentPerson{



    /**
     * Create and load data for the objects used for this form.
     *
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    protected function loadObjects() {
      $licenseMod = new LBBoards_Module_License();
      if(!$licenseMod->canApproveDiscipline()) {
          $this->setRedirect("noaccess");
      }
      $this->factory = I2CE_FormFactory::instance();
      if ($this->isPost()) {
        $primary = $this->factory->createContainer( "disciplinary_action" );
        $primary->load( $this->post );
      }
      else if ($this->get_exists('id')) {
        $id = $this->get('id');
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
        if($primary->getField("reinstate_date")->getDBValue() == "0000-00-00 00:00:00" || $primary->getField("reinstate_status")->getDBValue() == "exam_app_status|accepted") {
          $this->userMessage("Cant Approve/Reject This Reinstate");
          $this->setRedirect(  "view?id=" . $primary->getParent() );
        }
      }
      $primary->getField("reinstate_status")->setOption("required", true);
      $primary->getField("registrar_reinstate_notes")->setOption("required", true);
      $person = parent::loadPerson(  $primary->getParent() );
      $this->setObject( $person, I2CE_PageForm::EDIT_PARENT);
      $this->setObject( $primary, I2CE_PageForm::EDIT_PRIMARY);
    }

    /**
     * Save the objects to the database.
     *
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
        $primary = $this->factory->createContainer("disciplinary_action");
        if (!$primary instanceof I2CE_Form) {
            I2CE::raiseError("Could not create disciplinary_action form");
            return;
        }
        $primary->load($this->post);
        //check if there is another discipline which is pending
        $where = array(
          "operator" => "AND",
          "operand" => array(
            0 => array(
              "operator" => "FIELD_LIMIT",
              "field" => "parent",
              "style" => "equals",
              "data" => array("value" => $primary->getParent())
            ),
            1 => array(
              "operator" => "FIELD_LIMIT",
              "field" => "suspend",
              "style" => "equals",
              "data" => array("value" => 1)
            ),
            2 => array(
              "operator" => "NOT",
              "operand" => array(
                0 => array(
                  "operator" => "FIELD_LIMIT",
                  "field" => "id",
                  "style" => "equals",
                  "data" => array("value" => $primary->getID())
                )
              )
            ),
            3 => array(
              "operator" => "FIELD_LIMIT",
              "field" => "reinstate_status",
              "style" => "equals",
              "data" => array("value" => "exam_app_status|pending")
            ),
          )
        );
        $searchDiscipline = I2CE_FormStorage::search("disciplinary_action", false, $where);
        //activate this license if there is no other pending discipline
        if($primary->getField("reinstate_status")->getDBValue() == "exam_app_status|accepted" && count($searchDiscipline) == 0) {
          $person = $primary->getParent();
          $personObj = $this->factory->createContainer($person);
          $personObj->populateLast( array( "license" => "created" ) );
          $licenseObj = current( $personObj->children['license'] );
          if($licenseObj) {
            $newLicenseObj = $this->factory->createContainer("license");
            $newLicenseObj->populate();
            foreach($licenseObj->getFieldNames() as $field) {
              $newLicenseObj->getField($field)->setFromDB($licenseObj->getField($field)->getDBValue());
            }
            $newLicenseObj->getField("license_status")->setFromDB("license_status|active");
            $status_date = date("Y-m-d H:i:s");
            $newLicenseObj->getField("status_date")->setFromDB($status_date);
            $newLicenseObj->setParent($person);
            $user = new I2CE_User;
            $newLicenseObj->save($user);

            $emails = LBBoards_Module_Qualify::getPersonEmail($primary->getParent());
            $emails = implode(",", $emails);
            $fname = $personObj->getField("firstname")->getValue();
            $license_number = $licenseObj->getField("license_number")->getValue();
            $date = $primary->getField("reinstate_date")->getDisplayValue();
            $body = "Dear " . $fname.",<br>Your License Number<b> ".$license_number."</b> has been reinstated effective from ".$date;
            $subject = "License Reinstated";
            LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
          }
        }
        parent::save();
        $this->userMessage("Reinstate Saved Successfully");
        $this->setRedirect(  "view?id=" . $primary->getParent() );
        $primary->cleanup();
    }


}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
