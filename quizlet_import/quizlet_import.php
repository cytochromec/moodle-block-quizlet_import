<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines the import questions form.
 *
 * @package    block_quizlet_import
 * @subpackage questionbank
 * @copyright  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/import_form.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/editlib.php');

list($thispageurl, $contexts, $cmid, $cm, $module, $pagevars) =
        question_edit_setup('import', '/question/import.php', false, false);

// get display strings
$txt = new stdClass();
$txt->importerror = get_string('importerror', 'question');
$txt->importquestions = get_string('importquestions', 'question');

list($catid, $catcontext) = explode(',', $pagevars['cat']);
if (!$category = $DB->get_record("question_categories", array('id' => $catid))) {
    print_error('nocategory', 'question');
}

$categorycontext = context::instance_by_id($category->contextid);
$category->context = $categorycontext;

//GET THE QUIZLET DATA
echo $CFG->tempdir;
mkdir($CFG->tempdir.'/quizlet_import');
$file = fopen($CFG->tempdir.'/quizlet_import/tempQuizlet.txt',"w");

$url = $_GET["quizleturl"];
$id1 = $_GET["courseid"];
$sectionNum = $_GET["section"];
if($sectionNum == '' || !is_numeric($sectionNum)){
    $sectionNum=0;
}

//Get the unique number out of the Quizlet URL no matter which actual quizlet page the url was copied from 
$newurl = explode('/',$url);
$exporturl = "http://quizlet.com/" . $newurl[3] . '/export';


//get the export page and pull out the JSON object
$content = file_get_contents($exporturl);
$find = "var word";
$position1 = strpos($content,$find);
$find = "}];";
$position2 = strpos($content,$find);
$JSON = substr($content, $position1+12, $position2-$position1-10);

//get the title
$find = "<title>";
$position1 = strpos($content,$find);
$find = "</title>";
$position2 = strpos($content,$find);
$title = substr($content, $position1+14, $position2-$position1-24);
$CategoryName = $title.'Quizlet';

//write the category
fwrite($file,'$CATEGORY: ' . $CategoryName . "\n\n");
$result = json_decode($JSON, true);

//do the actual writing of questions to file
$Points = 0;
foreach ($result as &$row){
    $Points++;
    $line = "::" . $row['word'] . "::\n" . $row['definition'] . " {=" . $row['word'] . "}\n\n";
    fwrite($file,$line);
  
}
fclose($file);
//DONE GETTING THE DATA from quizlet


$PAGE->set_url($thispageurl);

//import the data using the gift format
$form = new question_import_form($thispageurl, array('contexts'=>$contexts->having_one_edit_tab_cap('import'),
                                                    'defaultcategory'=>$pagevars['cat']));
$form->format = 'gift';


//==========
// PAGE HEADER
//==========
$PAGE->set_title($txt->importquestions);
$PAGE->set_heading($COURSE->fullname);
echo $OUTPUT->header();

$realfilename = $CFG->tempdir."/quizlet_import/tempQuizlet.txt";
$importfile = $CFG->tempdir."/quizlet_import/tempQuizlet.txt";
$formatfile = $CFG->dirroot.'/question/format/gift/format.php';
if (!is_readable($formatfile)) {
    throw new moodle_exception('formatnotfound', 'question', '', $form->format);
}
require_once($formatfile);

$classname = 'qformat_' . $form->format;
$qformat = new $classname();

// load data into class
$qformat->setCategory($category);
$qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
$qformat->setCourse($COURSE);
$qformat->setFilename($importfile);
$qformat->setRealfilename($realfilename);
$qformat->setMatchgrades(true);
$qformat->setCatfromfile(true);
$qformat->setContextfromfile(true);
$qformat->setStoponerror(true);

// Do anything before that we need to
if (!$qformat->importpreprocess()) {
    print_error('cannotimport', '', $thispageurl->out());
}

// Process the uploaded file
if (!$qformat->importprocess($category)) {
    print_error('cannotimport', '', $thispageurl->out());
}

// In case anything needs to be done after
if (!$qformat->importpostprocess()) {
    print_error('cannotimport', '', $thispageurl->out());
}

$params = $thispageurl->params() + array(
    'category' => $qformat->category->id . ',' . $qformat->category->contextid);

//now import random sa matching using the xml import

$file = fopen($CFG->tempdir.'/quizlet_import/xmlimport.xml',"w");

//the text of the xml file
$xmlimport =
 '<?xml version="1.0" encoding="UTF-8"?>
<quiz>
<!-- question: 0  -->
  <question type="category">
    <category>
        <text>$course$/'.$CategoryName.'</text>

    </category>
  </question>

  <question type="randomsamatch">
    <name>
      <text>Random short-answer matching</text>
    </name>
    <questiontext format="html">
      <text><![CDATA[<p>Match the words with the definitions<br></p>]]></text>
    </questiontext>
    <generalfeedback format="html">
      <text></text>
    </generalfeedback>
    <defaultgrade>'.$Points.'</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <correctfeedback format="html">
      <text>Your answer is correct.</text>
    </correctfeedback>
    <partiallycorrectfeedback format="html">
      <text>Your answer is partially correct.</text>
    </partiallycorrectfeedback>
    <incorrectfeedback format="html">
      <text>Your answer is incorrect.</text>
    </incorrectfeedback>
    <shownumcorrect/>
    <choose>'.$Points.'</choose>
    <subcats>0</subcats>
  </question>
</quiz>';

fwrite($file,$xmlimport);
fclose($file);


//now import random sa matching using the xml import
$classname = 'qformat_xml';
$qformat2 = new $classname();
$realfilename = $CFG->tempdir."/quizlet_import/xmlimport.xml";
$importfile = $CFG->tempdir."/quizlet_import/xmlimport.xml";
$form->format = 'xml';
$formatfile = $CFG->dirroot.'/question/format/xml/format.php';
$qformat2->setCategory($category);
$qformat2->setContexts($contexts->having_one_edit_tab_cap('import'));
$qformat2->setCourse($COURSE);
$qformat2->setFilename($importfile);
$qformat2->setRealfilename($realfilename);
$qformat2->setMatchgrades(true);
$qformat2->setCatfromfile(true);
$qformat2->setContextfromfile(true);
$qformat2->setStoponerror(true);

// Do anything before that we need to
if (!$qformat2->importpreprocess()) {
    print_error('cannotimport', '', $thispageurl->out());
}

// Process the uploaded file
if (!$qformat2->importprocess($category)) {
    print_error('cannotimport', '', $thispageurl->out());
}

// In case anything needs to be done after
if (!$qformat2->importpostprocess()) {
    print_error('cannotimport', '', $thispageurl->out());
}

//create an object with all of the neccesary information to build a quiz
$myQuiz = new stdClass();
$myQuiz->modulename='quiz';
$myQuiz->name = $title;
$myQuiz->introformat = 0;
$myQuiz->quizpassword = '';
$myQuiz->course = $id1;
$myQuiz->section = $sectionNum;
$myQuiz->timeopen = 0;
$myQuiz->timeclose = 0;
$myQuiz->timelimit = 0;
$myQuiz->grade = $Points;
$myQuiz->sumgrades = $Points;
$myQuiz->gradeperiod = 0;
$myQuiz->attempts = 1;
$myQuiz->preferredbehaviour = 'deferredfeedback';
$myQuiz->attemptonlast = 0;
$myQuiz->shufflequestions = 0;
$myQuiz->grademethod = 1;
$myQuiz->questiondecimalpoints = 2;
$myQuiz->visible = 1;
$myQuiz->questionsperpage = 1;
$myQuiz->introeditor = array('text' => 'A matching quiz','format' => 1);

//all of the review options
$myQuiz->attemptduring=1;
$myQuiz->correctnessduring=1;
$myQuiz->marksduring=1;
$myQuiz->specificfeedbackduring=1;
$myQuiz->generalfeedbackduring=1;
$myQuiz->rightanswerduring=1;
$myQuiz->overallfeedbackduring=1;

$myQuiz->attemptimmediately=1;
$myQuiz->correctnessimmediately=1;
$myQuiz->marksimmediately=1;
$myQuiz->specificfeedbackimmediately=1;
$myQuiz->generalfeedbackimmediately=1;
$myQuiz->rightanswerimmediately=1;
$myQuiz->overallfeedbackimmediately=1;

$myQuiz->marksopen=1;

$myQuiz->attemptclosed=1;
$myQuiz->correctnessclosed=1;
$myQuiz->marksclosed=1;
$myQuiz->specificfeedbackclosed=1;
$myQuiz->generalfeedbackclosed=1;
$myQuiz->rightanswerclosed=1;
$myQuiz->overallfeedbackclosed=1;

//actually make the quiz using the function from course/lib.php

$myQuiz2 = create_module($myQuiz);
//print_object($myQuiz2);

//get the last added random short answer matching question (which will likely be the one we just added)
$result = $DB->get_records('question',array('qtype'=>'randomsamatch'));
$keys = array_keys($result);
$count = count($keys);

//add the quiz question
quiz_add_quiz_question($result[$keys[$count-1]]->id, $myQuiz2, $page = 0, $maxmark = null);


//go to the course page and edit the newly added quiz
echo $OUTPUT->continue_button(new moodle_url('../../course/modedit.php?update='.$myQuiz2->coursemodule));

//or uncomment out to have the button just return to the homepage
//echo $OUTPUT->continue_button(new moodle_url('../../course/view.php?id='.$id1.'#section-'.$sectionNum));
echo $OUTPUT->footer();
