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


class LBBoards_PageFormLicenseRenewalApply extends iHRIS_PageFormParentPerson{



    /**
     * Create and load data for the objects used for this form.
     *
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    protected function loadObjects() {
        $licenseMod = new LBBoards_Module_License();
        $applied_schedules = array();
        $this->factory = I2CE_FormFactory::instance();
        if($this->get_exists("type")) {
            if(!$licenseMod->canApproveLicenseRenewal()) {
                return $this->setRedirect("noaccess");
            }
            $id = $this->get("application_id");
            $parent = $this->get("parent");
            $parentObj = $this->factory->createContainer($parent);
            $parentObj->populateLast( array( "license" => "created" ) );
            $oldLicenseObj = current( $parentObj->children['license'] );
            $user = new I2CE_User;
            $primary = $this->factory->createContainer($id);
            $primary->populate();
            $primary->getField("application_status")->setFromDB("exam_app_status|accepted");
            if($oldLicenseObj) {
                $licenseObj = $this->factory->createContainer("license");
                $licenseObj->populate();
                $licenseObj->setParent($parent);
                foreach($oldLicenseObj->getFieldNames() as $field) {
                    $licenseObj->getField($field)->setFromDB($oldLicenseObj->getField($field)->getDBValue());
                }
                $start_date = $oldLicenseObj->getField("end_date")->getDBValue();
                $start_date = date("Y-m-d", strtotime('+1 days', strtotime($start_date)));
                $licenseObj->getField("start_date")->setFromDB($start_date);
                $end_date = date("Y-m-d", strtotime('+2 years', strtotime($start_date)));
                $licenseObj->getField("end_date")->setFromDB($end_date);
                $status_date = date("Y-m-d H:i:s");
                $licenseObj->getField("status_date")->setFromDB($status_date);
                $licenseObj->getField("license_status")->setFromDB("license_status|active");
                $licenseObj->getField("advance_email_notification")->setValue(0);
                $licenseObj->save($user);
            }
            $primary->save($user);
            if($oldLicenseObj) {
                $emails = LBBoards_Module_Qualify::getPersonEmail($primary->getParent());
                $emails = implode(",", $emails);
                $personObj = $this->factory->createContainer($primary->getParent());
                $personObj->populate();
                
                $fname = $personObj->getField("firstname")->getValue();
                $expire_date = $licenseObj->getField("end_date")->getDBValue();
                $license_number = $licenseObj->getField("license_number")->getValue();
                $body = "Dear " . $fname.",<br>Your License Number<b> ".$license_number."</b> has been renewed, the new expire date is ".$expire_date;
                $body .= ". Please come and collect your new license";
                $subject = "License Renewal";
                LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
            }
            $this->userMessage("License Renewal Application Approved");
            $this->setRedirect(  "view?id=" . $primary->getParent() );
        } else {
            if(!$licenseMod->canRenewLicense()) {
                $this->setRedirect("noaccess");
            }
            if ($this->isPost()) {
            $primary = $this->factory->createContainer($this->getForm());
            if (!$primary instanceof I2CE_Form) {
                I2CE::raiseError("Could not create license_renewal_apply form");
                return;
            }
            $primary->load($this->post);
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
            if (!$primary instanceof I2CE_Form || $primary->getName() != $this->getForm()) {
                I2CE::raiseError("Could not create valid " . $this->getForm() . "form from id:$id");
                return false;
            }
            $primary->populate();
            }
            elseif ( $this->get_exists('parent') ) {
                $primary = $this->factory->createContainer($this->getForm());
                if (!$primary instanceof I2CE_Form) {
                    return;
                }
                $parent = $this->get('parent');
                if(!LBBoards_Module_Qualify::isRegistered($parent)) {
                    $this->setRedirect('noaccess');
                    return;
                }
                if (strpos($parent,'|')=== false) {
                    I2CE::raiseError("Deprecated use of parent variable");
                    $parent =  'person|' . $parent;
                }
                $primary->getField('application_date')->setFromDB(date('Y-m-d'));
                $primary->setParent($parent);
            }
            $person = parent::loadPerson(  $primary->getParent() );
            $this->setObject( $person, I2CE_PageForm::EDIT_PARENT);
            $this->setObject( $primary, I2CE_PageForm::EDIT_PRIMARY);
        }
    }

    /**
     * Save the objects to the database.
     *
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
        $primary = $this->factory->createContainer($this->getForm());
        if (!$primary instanceof I2CE_Form) {
            I2CE::raiseError("Could not create license_renewal_apply form");
            return;
        }
        $primary->load($this->post);
        parent::save();
        $this->userMessage("Application For License Renewal Has Been Saved Successfully");
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
