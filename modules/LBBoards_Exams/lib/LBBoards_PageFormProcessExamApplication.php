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
	* Manage license renewals.
	* 
	* @package iHRIS
	* @subpackage Qualify
	* @access public
	* @author Ally Shaban <allyshaban5@gmail.com>
	* @since v2.0.0
	* @version v2.0.0
	*/
	
	/**
	* Page object to handle the renewal of licenses.
	* 
	* @package iHRIS
	* @subpackage Qualify
	* @access public
	*/
class LBBoards_PageFormProcessExamApplication extends I2CE_Page {
  protected function action() {
    $examMod = new LBBoards_Module_Exams();
    if(!$examMod->canApproveExamApplicants()) {
      $this->setRedirect("noaccess");
    }
    $this->factory = I2CE_FormFactory::instance();
    $user = new I2CE_User();
    if ($this->get_exists('type')) {
      $type = $this->get('type');
      $reason = $this->get('reason');
      $examApplyObj = $this->factory->createContainer($this->get('exam_apply'));
      $examApplyObj->populate();
      if ($type == 'approve') {
        $status = "Approved";
        $examApplyObj->getField('exam_app_status')->setFromDB('exam_app_status|accepted');
      } else if ($type == 'reject') {
        $status = "Rejected";
        $examApplyObj->getField('exam_app_status')->setFromDB('exam_app_status|rejected');
      }
      $examApplyObj->getField('reason')->setValue($reason);
      $examApplyObj->save($user);
      $personID = $examApplyObj->getParent();
      $personObj = $this->factory->createContainer($personID);
      $personObj->populate();
      $emails = LBBoards_Module_Qualify::getPersonEmail($personID);
      $emails = implode(",", $emails);
      $body = "Your application to sit for board exam - ".$examApplyObj->getField("exam_schedule")->getDisplayValue()." - has been ".$status;
      if($reason) {
        $body .= " with reason '".$reason. "'.";
      }
      if($type == "approve") {
        $body .= ".Your index number is ".$personObj->getField("registration_number")->getValue();
      }
      $subject = "Board Examination Application Status";
      LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
      return;
    }
    $today_date = date("Y-m-d");
    $where = array (
      "operator" => "OR",
      "operand" => array(
        0 => array(
          "operator" => "FIELD_LIMIT",
          "field" => "exam_start_date",
          "style" => "greaterthan_equals",
          "data" => array("value" => $today_date)
        ),
        1 => array(
          "operator" => "FIELD_LIMIT",
          "field" => "exam_end_date",
          "style" => "greaterthan_equals",
          "data" => array("value" => $today_date)
        )
      )
    );
    $examTypeScheduleSearch = I2CE_FormStorage::search('exam_schedule',false,$where);
    foreach($examTypeScheduleSearch as $examSched) {
      $where = array(
        "operator" => "FIELD_LIMIT",
        "field" => "exam_schedule",
        "style" => "equals",
        "data" => array("value" => 'exam_schedule|'.$examSched)
      );
      $examApplySearch = I2CE_FormStorage::search('exam_apply',false,$where);
      foreach ($examApplySearch as $examApply) {
        $applicant_node = $this->template->appendFileById("applicant.html", "div", "applicants_list");
        $examApplyObj = $this->factory->createContainer('exam_apply|'.$examApply);
        $examApplyObj->populate();
        $this->template->setForm($examApplyObj, $applicant_node);
        $personObj = $this->factory->createContainer($examApplyObj->getParent());
        $personObj->populate();
        $this->template->setForm($personObj, $applicant_node);
        $status = $examApplyObj->getField('exam_app_status')->getDisplayValue();

        $approve_found = false;
        if ($status == "Rejected" || $status == "Pending") {
          $approve_found = true;
          $approveNode = $this->template->getElementByName("action_approve", 0, $applicant_node);
          $aNode =$this->template->createElement(
            "input",array(
              "type"=>"button",
              "class" => "button",
              "value"=>"Approve", 
              "id" => $examApply."approve",
              "onclick" => "verify($examApply,'approve')"
            ), 
            "Approve"
          );
          $this->template->appendNode($aNode,$approveNode);
        }
        if ($status == "Pending") {
          $href = "process_exam_application?type=reject&exam_apply=exam_apply|".$examApplyObj->getID();
          $rejectNode = $this->template->getElementByName("action_reject",0,$applicant_node);
          if ($approve_found) {
            $separatorNode = $this->template->createElement("label",""," | ");
            $this->template->appendNode($separatorNode,$rejectNode);
          }
          $aNode =$this->template->createElement(
            "input",array(
              "type"=>"button",
              "class" => "button",
              "value"=>"Reject",
              "id" => $examApply."reject", 
              "onclick" => "verify($examApply,'reject')"
            ), 
            "Reject"
          );
          $this->template->appendNode($aNode,$rejectNode);
        }
        
        $reason = $examApplyObj->getField("reason")->getDBValue();
        $reasonNode = $this->template->getElementByName("reason", 0, $applicant_node);
        if ($reason) {
          $reasonInput = $this->template->createElement("label", "", $reason);
        } else {
          $reasonInput = $this->template->createElement(
            "textarea", array(
              "rows" => 2,
              "class" => "form_field apply_exam_reason",
              "id" => "reason".$examApplyObj->getID()
            )
          );
        }
        $errorNode=$this->template->createElement("span",array("class"=>"error","id"=>"reason_error".$examApply));
        $this->template->appendNode($errorNode,$reasonNode);
        $this->template->appendNode($reasonInput,$reasonNode);
      }
    }
  }
}