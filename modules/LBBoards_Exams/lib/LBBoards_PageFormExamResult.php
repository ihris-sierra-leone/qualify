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
class LBBoards_PageFormExamResult extends I2CE_Page {
  protected function action() {
    $this->factory = I2CE_FormFactory::instance();
    if ($this->isPost()) { //save results
      $user = new I2CE_User();
      $examApplyArr = $this->post("exam_apply");
      $subject = $this->post("exam_subject");
      foreach($examApplyArr as $examApply) {
        if(!array_key_exists("score_".$examApply,$this->post())) {
          // this result is already approved
          continue;
        }
        $score = $this->post("score_".$examApply);
        $examApplyObj = $this->factory->createContainer("exam_apply|".$examApply);
        $examApplyObj->populateChildren("exam_result");
        
        $resFound = false;
        foreach($examApplyObj->getChildren("exam_result") as $examResObj) {
          $this_subject = $examResObj->getField("exam_subject")->getDBValue();
          if($this_subject == $subject) {
            $resFound = true;
            break;
          }
        }
        if(!$resFound && $score == "") {
          continue;
        }
        if(!$resFound) {
          $examResObj = $this->factory->createContainer("exam_result|0");
        }
        $subjectObj = $this->factory->createContainer($subject);
        $subjectObj->populate();
        $pass_mark = $subjectObj->getField("pass_mark")->getValue();
        $assessment_type = $subjectObj->getField("assessment_type")->getDBValue();
        if($score == "pass" || ($score >= $pass_mark && $assessment_type == "assessment_type|marks")) {
          $examResObj->getField("exam_assessment")->setFromDB("exam_assessment|pass");
        } else if($score == "fail" || ($score < $pass_mark && $assessment_type == "assessment_type|marks")) {
          $examResObj->getField("exam_assessment")->setFromDB("exam_assessment|fail");
        } else {
          $examResObj->getField("exam_assessment")->setFromDB("exam_assessment|fail");
        }
        $examResObj->populate();
        $examResObj->getField("score")->setValue($score);
        $examResObj->getField("exam_subject")->setFromDB($subject);
        $examResObj->setParent("exam_apply|".$examApplyObj->getID());
        $examResObj->save($user);
      }
      $this->userMessage("Exam Results Processed Successfully");
      $this->setRedirect("board_exam_result");
    } else if($this->get_exists("exam_subject")) { //display candidates to add exam score
      $this->template->appendFileById("individual_exam_result_columns.html", "div", "individual_exam_results");
      $exam_subject = $this->get("exam_subject");
      $subjectObj = $this->factory->createContainer($exam_subject);
      $subjectObj->populate();
      $this->template->setForm($subjectObj);
      $exam_schedule = $this->get("exam_schedule");
      $examScheduleObj = $this->factory->createContainer($exam_schedule);
      $examScheduleObj->populate();
      $this->template->setForm($examScheduleObj);

      $exSubNode = $this->template->getElementById("exam_subject");
      $inputSubjNode = $this->template->createElement("input", array("type" => "hidden", "name" => "exam_subject", "value" => $exam_subject));
      $this->template->appendNode($inputSubjNode, $exSubNode);
      $where = array(
        "operator" => "AND",
        "operand" => array(
          0 => array(
            "operator" => "FIELD_LIMIT",
            "field" => "results_uploaded",
            "style" => "equals",
            "data" => array("value" => 0)
          ),
          1 => array(
            "operator" => "FIELD_LIMIT",
            "field" => "exam_app_status",
            "style" => "equals",
            "data" => array("value" => "exam_app_status|accepted")
          ),
          2 => array(
            "operator" => "FIELD_LIMIT",
            "field" => "exam_schedule",
            "style" => "equals",
            "data" => array("value" => $exam_schedule)
          )
        )
      );
      $assessment_type = $subjectObj->getField("assessment_type")->getDBValue();
      $examApplySearch = I2CE_FormStorage::search("exam_apply", false, $where);
      $applicantFound = false;
      foreach($examApplySearch as $examApply) {
        $examApplyObj = $this->factory->createContainer("exam_apply|".$examApply);
        $examApplyObj->populate();
        $resit = $examApplyObj->getField("resit")->getDBValue();
        if ($resit) {
          // get list of subjects supposed to retake
          $retake_subjects = LBBoards_Module_Exams::getRetakingSubjects($resit, "exam_apply|".$examApply);
          if(!in_array($exam_subject, $retake_subjects)) {
            continue;
          }
        }
        $applicantFound = true;
        $thisExamResultNode = $this->template->appendFileById("individual_exam_result.html", "div", "candidates_list");
        $parent = $examApplyObj->getParent();
        $parentObj = $this->factory->createContainer($parent);
        $parentObj->populate();
        $this->template->setForm($examApplyObj, $thisExamResultNode);
        $this->template->setForm($parentObj, $thisExamResultNode);

        $examApplyObj->populateChildren("exam_result");
        $score = "";
        $resFound = false;
        foreach($examApplyObj->getChildren("exam_result") as $examResObj) {
          $examResObj->populate();
          $this_subject = $examResObj->getField("exam_subject")->getDBValue();
          if ($this_subject == $exam_subject) {
            $resFound = true;
            $score = $examResObj->getField("score")->getValue();
            break;
          }
        }
        if(!$resFound) {
          $examResObj = $this->factory->createContainer("exam_result");
          $examResObj->populate();
        }
        $this->template->setForm($examResObj, $thisExamResultNode);
        $approval_status = $examResObj->getField("result_app_status")->getDBValue();
        $readonly = false;
        if($approval_status == "exam_app_status|accepted") {
          $readonly = true;
        }
        $scoreNode = $this->template->getElementByName("score", 0, $thisExamResultNode);
        if($assessment_type == "assessment_type|marks") {
          if($readonly) {
            $inputNode = $this->template->createElement(
              "input", array(
                "type" => "text", 
                "name" => "score_".$examApply, 
                "value" => $score,
                "readonly" => true
              )
            );
          } else {
            $inputNode = $this->template->createElement(
              "input", array(
                "type" => "text", 
                "name" => "score_".$examApply, 
                "value" => $score,
              )
            );
          }
          $this->template->appendNode($inputNode,$scoreNode);
        } else if($assessment_type == "assessment_type|passfail") {
          if($readonly) {
            $selectNode = $this->template->createElement("select", array("name" => "score_".$examApply, "disabled" => true), "Select One");
          } else {
            $selectNode = $this->template->createElement("select", array("name" => "score_".$examApply), "Select One");
          }
          $this->template->appendNode($selectNode, $scoreNode);
          $scoreSelNode = $this->template->getElementByName("score_".$examApply, 0, $thisExamResultNode);

          $optionNode = $this->template->createElement("option", array("value" => ""), "Select One");
          $this->template->appendNode($optionNode, $scoreSelNode);

          if($score === "pass") {
            $optionNode = $this->template->createElement("option", array("value" => "pass", "selected" => "selected"), "Pass");
          } else {
            $optionNode = $this->template->createElement("option", array("value" => "pass"), "Pass");
          }
          $this->template->appendNode($optionNode, $scoreSelNode);

          if($score === "fail") {
            $optionNode = $this->template->createElement("option", array("value" => "fail", "selected" => "selected"), "Fail");
          } else {
            $optionNode = $this->template->createElement("option", array("value" => "fail"), "Fail");
          }
          $this->template->appendNode($optionNode, $scoreSelNode);
        }

        $hiddenNode = $this->template->getElementByName("hidden_data", 0, $thisExamResultNode);
        $hidden_data = $this->template->createElement("input", array("type" => "hidden", "name" => "exam_apply[$examApply]", "value" => $examApply));
        $this->template->appendNode($hidden_data,$hiddenNode);
      }

      if(!$applicantFound) {
        $node = $this->template->getElementById("header");
        $nodata = $this->template->createElement("h4", array(), "No Applicant Enrolled For This Course");
        $this->template->appendNode($nodata, $node);
        $this->template->removeNodeById("individual_exam_results");
      }
    } else if($this->get_exists("exam_schedule")) { //display subjects for this schedule
      $exam_schedule = $this->get("exam_schedule");
      $examScheduleObj = $this->factory->createContainer($exam_schedule);
      $examScheduleObj->populate();
      $this->template->setForm($examScheduleObj);
      $cadres = array();
      $cadres[] = $examScheduleObj->getField("cadre")->getDBValue();
      $where = array(
        "operator" => "FIELD_LIMIT",
        "field" => "cadre",
        "style" => "in",
        "data" => array("value" => $cadres)
      );
      $searchExamSubject = I2CE_FormStorage::search("exam_subject", false, $where);
      if (count($searchExamSubject) == 0) {
        $node = $this->template->getElementById("exam_subjects");
        $nodata = $this->template->createElement("h4", array(), "No Subjects For This Schedule");
        $this->template->appendNode($nodata, $node);
        return;
      }
      $node = $this->template->getElementById("header");
      $nodata = $this->template->createElement("h3", array(), "Select Subject");
      $this->template->appendNode($nodata, $node);
      foreach($searchExamSubject as $subject) {
        $subjectObj = $this->factory->createContainer("exam_subject|".$subject);
        $subjectObj->populate();
        $exSubjNode = $this->template->getElementById("exam_subjects");
        $listNode = $this->template->createElement("li");
        $href = "board_exam_result?exam_subject=exam_subject|".$subject."&exam_schedule=".$exam_schedule;
        $hrefNode = $this->template->createElement("a", array("href" => $href), $subjectObj->getField("name")->getValue());
        $this->template->appendNode($hrefNode, $listNode);
        $this->template->appendNode($listNode, $exSubjNode);
      }
    } else {
      $headerNode = $this->template->getElementById("header");
      $headerText = $this->template->createElement("h3", array(), "Select Schedule");
      $this->template->appendNode($headerText, $headerNode);
      $where = array(
        "operator" => "AND",
        "operand" => array(
          0 => array(
            "operator" => "FIELD_LIMIT",
            "field" => "results_uploaded",
            "style" => "equals",
            "data" => array("value" => 0)
          ),
          1 => array(
            "operator" => "FIELD_LIMIT",
            "field" => "exam_app_status",
            "style" => "equals",
            "data" => array("value" => "exam_app_status|accepted")
          )
        )
      );
      $schedulesSearch = I2CE_FormStorage::listFields("exam_apply", array("exam_schedule"), false, $where);
      if (count($schedulesSearch) == 0) {
        $node = $this->template->getElementById("exam_schedules");
        $nodata = $this->template->createElement("h4", array(), "No Scheduled Exams");
        $this->template->appendNode($nodata, $node);
      }
      $examSchedules = [];
      foreach($schedulesSearch as $schedule) {
        if(!in_array($schedule["exam_schedule"], $examSchedules)) {
          $examSchedules[] = $schedule["exam_schedule"];
        }
      }
      foreach($examSchedules as $schedule) {
        $scheduleObj = $this->factory->createContainer($schedule);
        $scheduleObj->populate();
        $node = $this->template->appendFileById("exam_schedule_unprocessed.html", "div", "exam_schedules");
        $this->template->setForm($scheduleObj, $node);
      }
    }
  }
}