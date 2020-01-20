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

class LBBoards_Module_Exams extends I2CE_Module
{

    public static function getMethods()
    {
        return array(
            'iHRIS_PageView->action_exam_apply' => 'action_exam_apply',
            'iHRIS_PageView->action_overall_exam_assessment' => 'action_overall_exam_assessment',
            'iHRIS_PageView->action_license' => 'action_license'
        );
    }
    public static function getHooks()
    {
        return array(
            'validate_form_exam_schedule' => 'validate_form_exam_schedule',
            'validate_form_exam_subject' => 'validate_form_exam_subject',
        );
    }
    public function validate_form_exam_schedule($form)
    {
        if(!I2CE_Validate::checkDate($form->exam_start_date)) {
            $form->setInvalidMessage('exam_start_date', 'This field is required');
        }
        if(!I2CE_Validate::checkDate($form->exam_end_date)) {
            $form->setInvalidMessage('exam_end_date', 'This field is required');
        }
        if(!I2CE_Validate::checkDate($form->application_start_date)) {
            $form->setInvalidMessage('application_start_date', 'This field is required');
        }
        if(!I2CE_Validate::checkDate($form->application_end_date)) {
            $form->setInvalidMessage('application_end_date', 'This field is required');
        }
        if(!I2CE_Validate::checkDate($form->license_start_date)) {
            $form->setInvalidMessage('license_start_date', 'This field is required');
        }
        if ($form->exam_end_date->before($form->exam_start_date)) {
            $form->setInvalidMessage('exam_end_date', 'Exam End Date Should Be After Exam Start Date');
            $form->setInvalidMessage('exam_start_date', 'Exam Start Date Should Be Before Exam End Date');
        }
        if ($form->application_end_date->before($form->application_start_date)) {
            $form->setInvalidMessage('application_end_date', 'Application End Date Should Be After Application Start Date');
            $form->setInvalidMessage('application_start_date', 'Application Start Date Should Be Before Application End Date');
        }

        if ($form->exam_start_date->before($form->application_start_date)) {
            $form->setInvalidMessage('application_start_date', 'Application Start Date Should Be Before Exam Start Date');
            $form->setInvalidMessage('exam_start_date', 'Exam Start Date Should Be After Application Start Date');
        }
        if ($form->exam_start_date->before($form->application_end_date)) {
            $form->setInvalidMessage('application_end_date', 'Application End Date Should Be Before Exam Start Date');
            $form->setInvalidMessage('exam_start_date', 'Exam Start Date Should Be After Application End Date');
        }
        if ($form->license_start_date->before($form->exam_end_date)) {
            $form->setInvalidMessage('license_start_date', 'License Start Date Should Be After Exam End Date');
        }
    }

    public function validate_form_exam_subject($form)
    {
			$assessment_type = implode("|", $form->assessment_type);
			if ($form->pass_mark != '' && $assessment_type == "assessment_type|passfail") {
					return $form->setInvalidMessage('pass_mark', "This mode of assessment do not require pass mark");
			}
			if ($form->pass_mark == '' && $assessment_type == "assessment_type|marks") {
					return $form->setInvalidMessage('pass_mark', "This mode of assessment requires pass mark");
			}
			if ($form->pass_mark > 100 || $form->pass_mark < 0) {
					return $form->setInvalidMessage('pass_mark', "Pass mark must be in a range between 0-100");
			}
    }
    public function action_exam_apply($page)
    {
        if (!$page instanceof iHRIS_PageView) {
            return false;
		}
		$this->action_view_exam_result($page);
        return $page->addChildForms('exam_apply', 'siteContent');
    }

    public function action_overall_exam_assessment($page) {
        if (!$page instanceof iHRIS_PageView) {
            return false;
		}
        return $page->addChildForms('overall_exam_assessment', 'siteContent');
    }

    public function action_view_exam_result($page) {
			$factory = I2CE_FormFactory::instance();
			$this->template = $page->getTemplate();
			$personID = $page->getPerson()->getNameId();
			$personObj = $factory->createContainer($personID);
			$personObj->populate();
			$personObj->populateChildren("exam_apply");
			foreach($personObj->getChildren("exam_apply") as $examApplyObj) {
                $isUploaded = $examApplyObj->getField("results_uploaded")->getValue();
                if ($isUploaded !== 1) {
                    continue;
                }
				$result_node = $this->template->appendFileById("view_exam_result_base.html", "div", "view_exam_result");
				$page->setForm($examApplyObj, $result_node);
				$examApplyObj->populateChildren("exam_result");
				foreach($examApplyObj->getChildren("exam_result") as $examResObj) {
					$resultsRowNode = $this->template->appendFileById("view_exam_result.html", "div", "results_rows", false, $result_node);
					$page->setForm($examResObj, $resultsRowNode);
				}
			}
    }

    public function canApproveExamApplicants() {
        $user = new I2CE_User();
        if($user->role === "registrar") {
            return true;
        } else {
            return false;
        }
    }
    public function canApproveExamResults() {
        $user = new I2CE_User();
        if($user->role === "registrar") {
            return true;
        } else {
            return false;
        }
    }

    public function canApplyExam() {
        $factory = I2CE_FormFactory::instance();
        $can_apply = false;
        $person = LBBoards_PageView::$personid;
        $where = array(
            "operator" => "FIELD_LIMIT",
            "field" => "parent",
            "style" => "equals",
            "data" => array("value" => $person)
        );
        $overall_exam_assessment = I2CE_FormStorage::search("overall_exam_assessment", false, $where, array("-created"), true);
        if($overall_exam_assessment) {
            $overallAssObj = $factory->createContainer("overall_exam_assessment|".$overall_exam_assessment);
            $overallAssObj->populate();
            $status = $overallAssObj->getField("exam_assessment")->getDBValue();
        }
        $exam_apply = I2CE_FormStorage::search("exam_apply", false, $where, array("-created"), true);
        if($exam_apply) {
            $examApplyObj = $factory->createContainer("exam_apply|".$exam_apply);
            $examApplyObj->populate();
            $uploaded = $examApplyObj->getField("results_uploaded")->getValue();
            if(!$uploaded) {
                $can_apply = false;
            }
            if($uploaded && $status == "exam_assessment|fail") {
                $can_apply = true;
            }
        } else {
            $can_apply = true;
        }

        if(!$can_apply) {
            return false;
        }

        $user = new I2CE_User();
        if($user->role === "personnel" || $user->role === "registration_officer" || $user->role === "data_officer" || $user->role === "admin") {
            $can_apply = true;
        } else {
            $can_apply = false;
        }
        return $can_apply;
    }
    public function canAddExamResults() {
        $user = new I2CE_User();
        if($user->role === "registration_officer" || $user->role === "data_officer" || $user->role === "admin") {
            return true;
        } else {
            return false;
        }
    }

    public static function getSubjectsFromResults($exam_apply, $approved_only = false)
    {
        $factory = I2CE_FormFactory::instance();
        $examApplyObj = $factory->createContainer($exam_apply);
        $examApplyObj->populateChildren("exam_result");
        $exam_subjects = array();
        foreach ($examApplyObj->getChildren("exam_result") as $examResObj) {
            $approval = $examResObj->getField("result_app_status")->getDBValue();
            if ($approval != "exam_app_status|accepted" && $approved_only) {
                continue;
            }
            $exam_subjects[] = $examResObj->getField("exam_subject")->getDBValue();
        }
        return $exam_subjects;
    }
    static function getRetakingSubjects($originalApplication, $currentAppliation)
    {
        $factory = I2CE_FormFactory::instance();
        $failed_exams = array();

        $examApplyObj = $factory->createContainer($originalApplication);
        $examApplyObj->populate();
        $person = $examApplyObj->getParent();
        $personObj = $factory->createContainer($person);
        $personObj->populate();
        $cadre = $personObj->getField("cadre")->getDBValue();
        $cadreObj = $factory->createContainer($cadre);
        $cadreObj->populate();
        $total_fail_to_resit_all = $cadreObj->getField("total_fail_to_resit_all")->getValue();

        // $resit is an instance of exam_apply
        // get failed exams from first sit
        $res_subjs = LBBoards_Module_Exams::getSubjectsFromResults($originalApplication);
        foreach ($res_subjs as $subject) {
            $isPassed = LBBoards_Module_Exams::isPassed($subject, $originalApplication);
            if (!$isPassed) {
                $failed_exams[] = $subject;
            }
        }
        // if failed exams is greater than 2 then resit all subjectes
        if(count($failed_exams) >= $total_fail_to_resit_all) {
            $failed_exams = $res_subjs;
        }
        // get and remove subjects that has passed from other resit apart from the current resit
        $all_applications = LBBoards_Module_Exams::getAllApplications($person);
        foreach ($all_applications as $application) {
            // ignore the current resit and original sit
            if ($application == $originalApplication || $application == $currentAppliation) {
                continue;
            }
            $res_subjs = LBBoards_Module_Exams::getSubjectsFromResults($application);
            foreach ($res_subjs as $subject) {
                $isPassed = LBBoards_Module_Exams::isPassed($subject, $application);
                if ($isPassed) {
                    // remove from a list of failed subjects
                    $failed_exams = array_diff($failed_exams, array($subject));
                }
            }
            if(count($failed_exams) >= $total_fail_to_resit_all) {
                $failed_exams = $res_subjs;
            }
        }
        return $failed_exams;
    }

    static function getSubjetsFromSchedule($exam_schedule)
    {
        $factory = I2CE_FormFactory::instance();
        $examScheduleObj = $factory->CreateContainer($exam_schedule);
        $examScheduleObj->populate();
        $cadres[] = $examScheduleObj->getField("cadre")->getDBValue();
        $where = array(
            "operator" => "FIELD_LIMIT",
            "field" => "cadre",
            "style" => "in",
            "data" => array("value" => $cadres),
        );
        $searchExamSubject = I2CE_FormStorage::search("exam_subject", false, $where);
        return $searchExamSubject;
    }

    static function getAllApplications($person)
    {
        $factory = I2CE_FormFactory::instance();
        $personObj = $factory->createContainer($person);
        $personObj->populateChildren("exam_apply");
        $applications = array();
        foreach ($personObj->getChildren("exam_apply") as $examApplyObj) {
            $applications[] = "exam_apply|" . $examApplyObj->getID();
        }
        return $applications;
    }

    static function isPassed($exam_subject, $examApply) {
        $factory = I2CE_FormFactory::instance();
        $examApplyObj = $factory->createContainer($examApply);
        $examApplyObj->populate();
        $examApplyObj->populateChildren("exam_result");
        foreach ($examApplyObj->getChildren("exam_result") as $examResObj) {
            $this_subject = $examResObj->getField("exam_subject")->getDBValue();
            if ($this_subject != $exam_subject) {
                continue;
            }
            $assessment = $examResObj->getField("exam_assessment")->getDBValue();
            if ($assessment == "exam_assessment|pass") {
                return true;
            } else {
                return false;
            }
        }
    }

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
