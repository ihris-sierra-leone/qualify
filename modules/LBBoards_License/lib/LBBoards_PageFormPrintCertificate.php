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

require_once('fpdf/fpdf.php');
require_once('fpdi/src/autoload.php');

class LBBoards_PageFormPrintCertificate extends I2CE_PageForm{



    /**
     * Create and load data for the objects used for this form.
     *
     * Create the list object and if this is a form submission load
     * the data from the form data.
     */
    protected function loadObjects() {
      $this->factory = I2CE_FormFactory::instance();
      if ($this->isPost()) {
        $primary = $this->factory->createContainer( "print_certificate" );
        $license = $this->factory->createContainer( "license" );
        $person = $this->factory->createContainer( "person" );
        $primary->load( $this->post );
        $license->load( $this->post );
        $person->load( $this->post );
        $license_number = $license->getField("license_number")->getValue();
        $fullname = $person->getField("firstname")->getValue();
        $fullname .= " ". $person->getField("othername")->getValue();
        $fullname .= " ". $person->getField("surname")->getValue();
        $award_date = $primary->getField("award_date")->getDisplayValue();


        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->AddPage();

        //Set the source PDF file
        $pagecount = $pdf->setSourceFile("../modules/LBBoards_License/lib/file.pdf");

        //Import the first page of the file
        $tpl = $pdf->importPage(1);
        //Use this page as template
        $pdf->useTemplate($tpl, 10, 10, 200);
        $pdf->SetFontSize('10'); // set font size
        #Print Hello World at the bottom of the page

        //Go to 1.5 cm from bottom
        $pdf->SetXY(150,59);
        //Select Arial italic 8
        $pdf->SetFont('Arial','I',20);
        //Print centered cell with a text in it
        $pdf->write(5, $license_number);

        $pdf->SetXY(40,70);
        //Select Arial italic 8
        $pdf->SetFont('Arial','I',20);
        //Print centered cell with a text in it
        $pdf->write(5, $fullname);

        $pdf->SetXY(67,86.5);
        //Select Arial italic 8
        $pdf->SetFont('Arial','I',16);
        //Print centered cell with a text in it
        $pdf->write(5, $award_date);


        $pdf->Output("F", "../modules/LBBoards_License/lib/output/certificate.pdf");
        $file = "../modules/LBBoards_License/lib/output/certificate.pdf";
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
        
      }
      else {
        $primary = $this->factory->createContainer( "print_certificate" );
        $licenseObj = $this->factory->createContainer( $this->get("license") );
        $parentObj = $this->factory->createContainer( $this->get("parent") );
        $parentObj->populate();
        $licenseObj->populate();
        $primary->populate();
      }
      $this->setObject( $primary, I2CE_PageForm::EDIT_PRIMARY);
      $this->setObject( $licenseObj, I2CE_PageForm::EDIT_SECONDARY);
      $this->setObject( $parentObj, I2CE_PageForm::EDIT_PARENT);
    }
    /**
     * Save the objects to the database.
     *
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {
      //parent::save();
      $primary = $this->factory->createContainer($this->getPrimary()->getNameId());
      $primary->populate();
      $primary->load($this->post);
      $this->userMessage("Exam Application Activated Successfully");
      $this->setRedirect(  "view?id=" . $this->getPrimary()->getNameId() );
      $primary->cleanup();
    }

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
