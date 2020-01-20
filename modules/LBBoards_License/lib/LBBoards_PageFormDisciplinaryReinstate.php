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


class LBBoards_PageFormDisciplinaryReinstate extends iHRIS_PageFormParentPerson{



    /**
     * Create and load data for the objects used for this form.
     *
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    protected function loadObjects() {
        $licenseMod = new LBBoards_Module_License();
        if(!$licenseMod->canEditDiscipline()) {
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
        if($primary->getField("disciplinary_status")->getDBValue() != "exam_app_status|accepted" || $primary->getField("suspend")->getValue() == 0) {
          $this->userMessage("Cant Reinstate This Disciplinary Action");
          $this->setRedirect(  "view?id=" . $primary->getParent() );
        }
      }
      $primary->getField("reinstate_date")->setOption("required", true);
      $primary->getField("officer_reinstate_notes")->setOption("required", true);
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
        parent::save();
        $this->userMessage("Disciplinary Action Approved Successfully");
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
