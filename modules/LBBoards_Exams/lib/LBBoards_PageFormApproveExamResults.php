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
class LBBoards_PageFormApproveExamResults extends I2CE_Page {
  protected function action() {
    $examMod = new LBBoards_Module_Exams();
    if(!$examMod->canApproveExamResults()) {
      $this->setRedirect("noaccess");
    }
    $this->factory = I2CE_FormFactory::instance();
    if($this->get_exists("type")) { //aprove/reject results
      $user = new I2CE_User();
      $type = $this->get("type");
      $examApply = $this->get("exam_apply");
      $examSubject = $this->get("exam_subject");
      $examSchedule = $this->get("exam_schedule");
      $examApplyObj = $this->factory->createContainer($examApply);
      $examApplyObj->populate();
      $examApplyObj->populateChildren("exam_result");

      $resFound = false;
      foreach($examApplyObj->getChildren("exam_result") as $examResObj) {
        $this_subject = $examResObj->getField("exam_subject")->getDBValue();
        if($this_subject == $examSubject) {
          $resFound = true;
          break;
        }
      }
      if(!$resFound) {
        I2CE::raiseError("No results object found for ".$examApply);
      }
      if($type == "approve") {
        $status = "accepted";
      } else if($type == "reject") {
        $status = "rejected";
      }
      $examResObj->populate();
      $examResObj->getField("result_app_status")->setFromDB("exam_app_status|".$status);
      $examResObj->save($user);

      $resit = $examApplyObj->getField("resit")->getDBValue();
      $all_results_approved = false;
      if(!$resit) {
        // get all subjects supposed to do
        $sched_subjs = LBBoards_Module_Exams::getSubjetsFromSchedule($examSchedule);
        // get all approved results
        $res_subjs = LBBoards_Module_Exams::getSubjectsFromResults($examApply, true);
        if(count($sched_subjs) == count($res_subjs)) {
          $all_results_approved = true;
        }
      } else if($resit) {
        $exam_retake = LBBoards_Module_Exams::getRetakingSubjects($resit, $examApply);
        // get approved results from this sit
        $res_subjs = LBBoards_Module_Exams::getSubjectsFromResults($examApply, true);
        if (count($res_subjs) == count($exam_retake)) {
          $all_results_approved = true;
        }
      }
      if($all_results_approved) {
        // add overal exam assessment
        $examApplyObj->getField("results_uploaded")->setValue("1");
        $examApplyObj->save($user);
        $person = $examApplyObj->getParent();
        $isPassedAll = $this->isPassedAllExams($person, $examApply);
        $personObj = $this->factory->createContainer($person);
        $where = array(
          "operator" => "FIELD_LIMIT",
          "field" => "exam_apply",
          "style" => "equals",
          "data" => array("value" => $examApply)
        );
        $searchOverallAss = I2CE_FormStorage::search("overall_exam_assessment", false, $where);
        if(count($searchOverallAss) == 1) {
          $overallAssObj = $this->createContainer("overall_exam_assessment|".$searchOverallAss[0]);
        } else {
          $overallAssObj = $this->factory->createContainer("overall_exam_assessment|0");
          $overallAssObj->populate();
          $overallAssObj->getField("exam_apply")->setFromDB($examApply);
          $overallAssObj->setParent($person);
        }
        if($isPassedAll) {
          $overallAssObj->getField("exam_assessment")->setFromDB("exam_assessment|pass");
        } else {
          $overallAssObj->getField("exam_assessment")->setFromDB("exam_assessment|fail");
        }
        $overallAssObj->save($user);
        //if passed all then give license number
        if($isPassedAll) {
          $examScheduleObj = $this->factory->createContainer($examSchedule);
          $examScheduleObj->populate();

          $personObj->populateChildren("license");
          if(count($personObj->getChildren("license")) == 0) {
            $cadre = $examScheduleObj->getField("cadre")->getDBValue();
            $license_start_date = $examScheduleObj->getField("license_start_date")->getDBValue();
            $licenseNum = $this->getNewLicense($cadre);
            $cadreObj = $this->factory->createContainer($cadre);
            $cadreObj->populate();
            $cadreObj->getField("last_license_number")->setValue($licenseNum);
            $cadreObj->save($user);
            $licenseObj = $this->factory->createContainer("license");
            $licenseObj->populate();
            $licenseObj->setParent($person);
            $licenseObj->getField("license_number")->setValue($licenseNum);
            $start_date = date("Y-m-d");
            $licenseObj->getField("start_date")->setFromDB($license_start_date);
            $end_date = date("Y-m-d", strtotime('+2 years', strtotime($license_start_date)));
            $licenseObj->getField("end_date")->setFromDB($end_date);
            $licenseObj->getField("license_status")->setFromDB("license_status|active");
            $status_date = date("Y-m-d H:i:s");
            $licenseObj->getField("status_date")->setFromDB($status_date);
            $licenseObj->save($user);
          }
        }

        //send email aout results
        $emails = LBBoards_Module_Qualify::getPersonEmail($person);
        $emails = implode(",", $emails);
        $personObj->populate();
        if($isPassedAll) {
          $status = "Passed";
        } else {
          $status = "Failed";
        }
        $fname = $personObj->getField("firstname")->getValue();
        $body = "Dear " . $fname.",<br>Your board exam results - ".$examApplyObj->getField("exam_schedule")->getDisplayValue().
                " - has been published. Overall you have ". $status;
        if($licenseNum) {
          $body .= "Your license number is ".$licenseNum;
        }
        $body .=". please visit the board online system to view your results";
        $subject = "Board Examination Results Published";
        LBBoards_Module_Qualify::sendEmail($emails, $subject, $body);
      }
      $this->userMessage("Exam Results Approved Successfully");
      $this->setRedirect("approve_exam_result?exam_subject=".$examSubject."&exam_schedule=".$examSchedule);
    } else if($this->get_exists("exam_subject")) { //displaying list of candidates
      $this->template->appendFileById("individual_exam_result_columns_approval.html", "div", "individual_exam_results");
      $exam_subject = $this->get("exam_subject");
      $subjectObj = $this->factory->createContainer($exam_subject);
      $subjectObj->populate();
      $this->template->setForm($subjectObj);
      $exam_schedule = $this->get("exam_schedule");
      $examScheduleObj = $this->factory->createContainer($exam_schedule);
      $examScheduleObj->populate();
      $this->template->setForm($examScheduleObj);

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
      $applicantFound = false;
      $examApplySearch = I2CE_FormStorage::search("exam_apply", false, $where);
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
        $back_link_node = $this->template->getElementById("back_link");
        $link = $this->template->createElement("a",array("href" => "approve_exam_result?exam_schedule=".$exam_schedule), "View Subjects List");
        $this->template->appendNode($link, $back_link_node);
        $thisExamResultNode = $this->template->appendFileById("individual_exam_result_approval.html", "div", "candidates_list");
        $parent = $examApplyObj->getParent();
        $parentObj = $this->factory->createContainer($parent);
        $parentObj->populate();

        $examApplyObj->populateChildren("exam_result");
        $results = array();
        
        $resFound = false;
        foreach($examApplyObj->getChildren("exam_result") as $examResObj) {
          $examResObj->populate();
          $this_subject = $examResObj->getField("exam_subject")->getDBValue();
          if ($this_subject == $exam_subject) {
            $resFound = true;
            break;
          }
        }
        if(!$resFound) {
          $examResObj = $this->factory->createContainer("exam_result");
          $examResObj->populate();
        }
        $res_app_status = $examResObj->getField("result_app_status")->getDBValue();

        $approved = true;
        if($res_app_status == "exam_app_status|pending" || $res_app_status == "exam_app_status|rejected") {
          $approved = false;
          $approveNode = $this->template->getElementByName("action_approve", 0, $thisExamResultNode);
          $href = "approve_exam_result?type=approve&exam_apply=exam_apply|".$examApply."&exam_subject=".$exam_subject."&exam_schedule=".$exam_schedule;
          $hrefNode = $this->template->createElement("a", array("href" => $href), "Approve");
          $this->template->appendNode($hrefNode, $approveNode);
        }
        if($res_app_status == "exam_app_status|pending" || $res_app_status == "exam_app_status|accepted") {
          if (!$approved) {
            $rejectNode = $this->template->getElementByName("action_reject", 0, $thisExamResultNode);
            $hrefNode = $this->template->createElement("a", array(), " | ");
            $this->template->appendNode($hrefNode, $rejectNode);
          }
          $rejectNode = $this->template->getElementByName("action_reject", 0, $thisExamResultNode);
          $href = "approve_exam_result?type=reject&exam_apply=exam_apply|".$examApply."&exam_subject=".$exam_subject."&exam_schedule=".$exam_schedule;
          $hrefNode = $this->template->createElement("a", array("href" => $href), "Reject");
          $this->template->appendNode($hrefNode, $rejectNode);
        }

        $this->template->setForm($examApplyObj, $thisExamResultNode);
        $this->template->setForm($parentObj, $thisExamResultNode);
        $this->template->setForm($examResObj, $thisExamResultNode);
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
        $node = $this->template->getElementById("header");
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
        $href = "approve_exam_result?exam_subject=exam_subject|".$subject."&exam_schedule=".$exam_schedule;
        $hrefNode = $this->template->createElement("a", array("href" => $href), $subjectObj->getField("name")->getValue());
        $this->template->appendNode($hrefNode, $listNode);
        $this->template->appendNode($listNode, $exSubjNode);
      }
    } else { //displaying exam_schedules
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
        $node = $this->template->getElementById("header");
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
        $node = $this->template->appendFileById("exam_schedule_approval.html", "div", "exam_schedules");
        $this->template->setForm($scheduleObj, $node);
      }
    }
  }

  private function isPassedAllExams($person, $originalApplication) {
    $personObj = $this->factory->createContainer($person);
    $personObj->populateChildren("exam_apply");
    $exam_applications = $personObj->getChildren("exam_apply");

    $exam_subjects = LBBoards_Module_Exams::getSubjectsFromResults($originalApplication);
    $passedAll = true;
    foreach($exam_subjects as $exam_subject) {
      $isPassed = LBBoards_Module_Exams::isPassed($exam_subject, $originalApplication);
      if(!$isPassed) {
        $passedResit = false;
        foreach($exam_applications as $exam_application) {
          $this_exam_application = "exam_apply|".$exam_application->getID();
          if($this_exam_application == $originalApplication) {
            continue;
          }
          $isPassed = LBBoards_Module_Exams::isPassed($exam_subject, $this_exam_application);
          if($isPassed) {
            $passedResit = true;
            break;
          }
        }
        if(!$passedResit) {
          $passedAll = false;
          break;
        }
      }
    }
    if($passedAll) {
      return true;
    } else {
      return false;
    }
  }

  static function getNewLicense($cadre) {
    $factory = I2CE_FormFactory::instance();
    $cadreObj = $factory->createContainer($cadre);
    $cadreObj->populate();
    $reg_num = $cadreObj->getField("last_license_number")->getValue();
    $code = $cadreObj->getField("code")->getValue();
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
    $reg_numArr = explode("-",$reg_num);
    if(count($reg_numArr) == 1) {
      $reg_num = $code."-".$reg_num;
    }
    return $reg_num;
  }
}