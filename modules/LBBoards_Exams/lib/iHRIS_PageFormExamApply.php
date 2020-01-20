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


class iHRIS_PageFormExamApply extends iHRIS_PageFormParentPerson{



    /**
     * Create and load data for the objects used for this form.
     *
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    protected function loadObjects() {
        $applied_schedules = array();
        $this->factory = I2CE_FormFactory::instance();
        if ($this->isPost()) {
          $primary = $this->factory->createContainer($this->getForm());
          if (!$primary instanceof I2CE_Form) {
              I2CE::raiseError("Could not create exam_apply form");
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
          $primary->getField('results_uploaded')->setValue("0");
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
          if (strpos($parent,'|')=== false) {
              I2CE::raiseError("Deprecated use of parent variable");
              $parent =  'person|' . $parent;
          }
          $parentObj = $this->factory->createContainer($parent);
          $parentObj->populate();
          if(!LBBoards_Module_Qualify::isRegistered($parent)) {
              $this->setRedirect('noaccess');
              return;
          }
          $primary->getField('application_date')->setFromDB(date('Y-m-d'));
          $primary->getField('results_uploaded')->setValue("0");
          $primary->getField('index_number')->setValue($parentObj->getField("registration_number")->getValue());
          $primary->setParent($parent);
        }
        if ($this->isGet()) {
            $primary->load($this->get());
        }
        $parentObj = $this->factory->createContainer($primary->getParent());
        $parentObj->populate();
        $parentObj->populateChildren("exam_apply");
        foreach($parentObj->getChildren("exam_apply") as $examApplyObj) {
            $ex_sc = $examApplyObj->getField("exam_schedule")->getDBValue();
            $ex_sc = explode("|", $ex_sc);
            if (count($ex_sc) == 2) {
                $applied_schedules[] = $ex_sc[1];
            }
        }
        $person = parent::loadPerson(  $primary->getParent() );
        $cadre = $person->getField("cadre")->getDBValue();
        $this->filterExams($primary, $applied_schedules, $cadre);
        $this->setObject( $person, I2CE_PageForm::EDIT_PARENT);
        $this->setObject( $primary, I2CE_PageForm::EDIT_PRIMARY);
    }

    public function filterExams ($exam_apply, $applied_schedules = array(), $cadre) {
        $today_date = date("Y-m-d");
        $where = array (
            "operator" => "AND",
            "operand" => array(
                0 => array(
                    "operator" => "FIELD_LIMIT",
                    "field" => "application_start_date",
                    "style" => "lessthan_equals",
                    "data" => array("value" => $today_date)
                ),
                1 => array(
                    "operator" => "FIELD_LIMIT",
                    "field" => "application_end_date",
                    "style" => "greaterthan_equals",
                    "data" => array("value" => $today_date)
                ),
                2 => array(
                    "operator" => "FIELD_LIMIT",
                    "field" => "cadre",
                    "style" => "equals",
                    "data" => array("value" => $cadre)
                ),
                3 => array(
                    "operator" => "NOT",
                    "operand" => array(
                        0 => array(
                            "operator" => "FIELD_LIMIT",
                            "field" => "id",
                            "style" => "in",
                            "data" => array("value" => $applied_schedules)
                        )
                    )
                )
            )
        );
        $exam_schedule = $exam_apply->getField("exam_schedule");
        $exam_schedule->setOption(array("meta","limits","default","exam_schedule"),$where);
    }

    /**
     * Save the objects to the database.
     *
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
        $exam_apply = $this->factory->createContainer("exam_apply");
        $exam_apply->load($this->post);
        $parentObj = $this->factory->createContainer($exam_apply->getParent());
        $parentObj->populate();
        $parentObj->populateChildren("exam_apply");
        foreach($parentObj->getChildren("exam_apply") as $examApplyObj) {
            if($exam_apply->getID() == $examApplyObj->getID()) {
                continue;
            }
            $resit = $examApplyObj->getField("resit")->getDBValue();
            if(!$resit) {
            $exam_apply->getField("resit")->setFromDB("exam_apply|".$examApplyObj->getID());
            break;
            } else {
                $exam_apply->getField("resit")->setFromDB($resit);
                break;
            }
        }
        $user = new I2CE_User;
	    $exam_apply->save($user);
        $this->userMessage("Exam Application Successfully Made");
        $this->setRedirect(  "view?id=" . $exam_apply->getParent() );
        $exam_apply->cleanup();
    }


}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
