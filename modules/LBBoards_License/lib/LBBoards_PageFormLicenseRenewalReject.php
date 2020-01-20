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


class LBBoards_PageFormLicenseRenewalReject extends iHRIS_PageFormParentPerson{



    /**
     * Create and load data for the objects used for this form.
     *
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    protected function loadObjects() {
        $licenseMod = new LBBoards_Module_License();
        if(!$licenseMod->canApproveLicenseRenewal()) {
            $this->setRedirect("noaccess");
        }
      $this->factory = I2CE_FormFactory::instance();
      if ($this->isPost()) {
        $primary = $this->factory->createContainer($this->getForm());
        if (!$primary instanceof I2CE_Form) {
            I2CE::raiseError("Could not create license_renewal_apply form");
            return;
        }
        $secondary = $this->factory->createContainer("license_renewal_apply");
        $primary->load($this->post);
        $secondary->load($this->post);
      }
      else {
        $id = $this->get('id');
        $parent = $this->get('parent');
        if ($this->get_exists('id')) {
            $id = $this->get('id');
            if (strpos($id,'|')=== false) {
                I2CE::raiseError("Deprecated use of id variable");
                $id = $this->getForm() . '|' . $id;
            }
        } else {
            $id = $this->getForm() . '|0';
        }
        $primary = $this->factory->createContainer("license_renewal_reject");
        $secondary = $this->factory->createContainer($id);
        if (!$primary instanceof I2CE_Form || $primary->getName() != $this->getForm()) {
            I2CE::raiseError("Could not create valid " . $this->getForm() . "form from id:$id");
            return false;
        }
        $primary->populate();
        $primary->setParent($parent);
        $secondary->populate();
      }
      $person = parent::loadPerson(  $primary->getParent() );
      $this->setObject( $person, I2CE_PageForm::EDIT_PARENT);
      $this->setObject( $primary, I2CE_PageForm::EDIT_PRIMARY);
      $this->setObject( $secondary, I2CE_PageForm::EDIT_SECONDARY);
    }

    /**
     * Save the objects to the database.
     *
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
      $renewalObj = $this->factory->createContainer("license_renewal_apply|0");
      $renewalObj->load($this->post);
      $renewalObj->populate();
      $primary = $this->factory->createContainer($this->getForm());
      $primary->load($this->post);
      $renewalObj->getField("reason")->setValue($primary->getField("reason")->getValue());
      $renewalObj->getField("application_status")->setFromDB("exam_app_status|rejected");
      $slip = $this->post["form"]["license_renewal_apply"][$renewalObj->getID()]["fields"]["payment_slip"];
      $renewalObj->getField("payment_slip")->setFromDB($slip);
      $user = new I2CE_User;
      $renewalObj->save($user);

        $emails = LBBoards_Module_Qualify::getPersonEmail($primary->getParent());
        $emails = implode(",", $emails);
        $personObj = $this->factory->createContainer($primary->getParent());
        $personObj->populate();
        
        $fname = $personObj->getField("firstname")->getValue();
        $reason = $primary->getField("reason")->getValue();
        $body = "Dear " . $fname.",<br>A request to renew your license has been rejected with reason '".$reason."'";
        $subject = "License Renewal";
        LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
      $this->userMessage("License Renewal Application Has Been Successfully Rejected");
      $this->setRedirect(  "view?id=" . $primary->getParent() );
      $primary->cleanup();
      $renewalObj->cleanup();
    }


}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
