<?php
/**
 * © Copyright 2008 IntraHealth International, Inc.
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
 *  Ngorongoro_Module_Trainng
 * @package I2CE
 * @subpackage Core
 * @author Ally Shaban <allyshaban5@gmail.com>
 * @copyright Copyright &copy; 2008 IntraHealth International, Inc.
 * This file is part of I2CE. I2CE is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version. I2CE is distributed in the hope
 * that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have
 * received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
 * @version 2.1
 * @access public
 */

class LBBoards_Module_License extends I2CE_Module
{
    public $person;

    public static function getMethods()
    {
        return array(
            'iHRIS_PageView->action_license' => 'action_license',
            'iHRIS_PageView->action_license_renewal_apply' => 'action_license_renewal_apply',
            'iHRIS_PageView->action_disciplinary_action' => 'action_disciplinary_action',
            'iHRIS_PageView->action_test' => 'action_test',
        );
    }
    public function action_disciplinary_action($page) {
        if (!$page instanceof iHRIS_PageView) {
            return false;
		}
        return $page->addChildForms('disciplinary_action', 'siteContent');
    }

    public function action_license_renewal_apply($page) {
        $factory = I2CE_FormFactory::instance();
        if (!$page instanceof iHRIS_PageView) {
            return false;
        }
        $this->template = $page->getTemplate();
        $personID = $page->getPerson()->getNameId();
        $where = array(
            "operator" => "FIELD_LIMIT",
            "field" => "parent",
            "style" => "equals",
            "data" => array("value" => $personID)
        );
        $license_renew = I2CE_FormStorage::search("license_renewal_apply", false, $where, array("-created"), true);
        if ($license_renew) {
            $license_renew_node = $this->template->appendFileById("view_license_renewal_apply.html", "div", "license_renewal_apply");
            $licenseRenewObj = $factory->createContainer("license_renewal_apply|".$license_renew);
            $licenseRenewObj->populate();
            $page->setForm($licenseRenewObj, $license_renew_node);
        }
        $this->checkLicenseExpire($page);
    }

    public function checkLicenseExpire($page) {
        $personID = $page->getPerson()->getNameId();
    }

    public function action_license($page)
    {
        $factory = I2CE_FormFactory::instance();
        if (!$page instanceof iHRIS_PageView) {
            return false;
        }
        $this->template = $page->getTemplate();
        $personID = $page->getPerson()->getNameId();
        $this->person = $personID;
        $where = array(
            "operator" => "FIELD_LIMIT",
            "field" => "parent",
            "style" => "equals",
            "data" => array("value" => $personID)
        );
        $license = I2CE_FormStorage::search("license", false, $where, array("-created"), true);
        if ($license) {
            $license_node = $this->template->appendFileById("view_license.html", "div", "license");
            $licenseObj = $factory->createContainer("license|".$license);
            $licenseObj->populate();
            $page->setForm($licenseObj, $license_node);
        }
    }

    public function canRenewLicense() {
        $factory = I2CE_FormFactory::instance();
        $can_renew = false;
        $where = array(
            "operator" => "FIELD_LIMIT",
            "field" => "parent",
            "style" => "equals",
            "data" => array("value" => $this->person)
        );
        $license = I2CE_FormStorage::search("license", false, $where, array("-created"), true);
        if($license) {
            $licenseObj = $factory->createContainer("license|".$license);
            $licenseObj->populate();
            $expired_date1 = date("Y-m-d", strtotime("+3 months"));
            $expired_date2 = date("Y-m-d", strtotime('+1 months', strtotime($expired_date1)));
            $license_end_date = $licenseObj->getField("end_date")->getDBValue();
            $projection = date("Y-m-d", strtotime('-3 months', strtotime($license_end_date)));
            $today = date("Y-m-d");
            if($projection <= $today) {
                $can_renew = true;
            } else {
                $can_renew = false;
            }
        }
        if(!$can_renew) {
            return false;
        }
        $user = new I2CE_User();
        if($user->role === "personnel" || $user->role === "registration_officer" || $user->role === "data_officer" || $user->role === "admin") {
            $can_renew = true;
        } else {
            $can_renew = false;
        }
        return $can_renew;
    }
    public function canAddDiscipline() {
        $user = new I2CE_User();
        $factory = I2CE_FormFactory::instance();
        $can_add = false;
        $person = LBBoards_PageView::$personid;
        $where = array(
            "operator" => "FIELD_LIMIT",
            "field" => "parent",
            "style" => "equals",
            "data" => array("value" => $person)
        );
        $disciplinaryAct = I2CE_FormStorage::search("disciplinary_action", false, $where, array("-created"), true);
        if(!$disciplinaryAct) {
            $can_add = true;
        } else {
            $disciplinaryActObj = $factory->createContainer("disciplinary_action|".$disciplinaryAct);
            $disciplinaryActObj->populate();
            $suspend = $disciplinaryActObj->getField("suspend")->getValue();
            if(!$suspend) {
                $can_add = true;
            } else {
                $reinstate_status = $disciplinaryActObj->getField("reinstate_status")->getDBValue();
                $disciplinary_status = $disciplinaryActObj->getField("disciplinary_status")->getDBValue();
                if($reinstate_status == "exam_app_status|accepted" || $disciplinary_status == "exam_app_status|rejected") {
                    $can_add = true;
                }
            }
        }
        if(!$can_add) {
            return false;
        }
        if($user->role === "registration_officer" || $user->role === "data_officer" || $user->role === "admin") {
            $can_add = true;
        } else {
            $can_add = false;
        }
        return $can_add;
    }
    public function canEditDiscipline() {
        $user = new I2CE_User();
        if($user->role === "registration_officer" || $user->role === "data_officer" || $user->role === "admin") {
            return true;
        } else {
            return false;
        }
    }
    public function canApproveDiscipline() {
        $user = new I2CE_User();
        if($user->role === "registrar") {
            return true;
        } else {
            return false;
        }
    }
    public function canApproveLicenseRenewal() {
        $user = new I2CE_User();
        if($user->role === "registrar") {
            return true;
        } else {
            return false;
        }
    }

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
