<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2012  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ======================================================================== */


$TBL_EXERCISE_QUESTION = 'exercise_with_questions';
$TBL_EXERCISE = 'exercise';
$TBL_QUESTION = 'exercise_question';
$TBL_ANSWER = 'exercise_answer';
$TBL_RECORDS = 'exercise_user_record';

include('exercise.class.php');
include('question.class.php');
include('answer.class.php');

$require_current_course = TRUE;
$guest_allowed = true;
include '../../include/baseTheme.php';
require_once 'include/lib/textLib.inc.php';

$nameTools = $langExercicesResult;
$navigation[] = array("url" => "index.php?course=$course_code", "name" => $langExercices);

// picture path
$picturePath = "courses/$course_code/image";
//Identifying ajax request
if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && $is_editor) {
    $grade = $_POST['question_grade'];
    $question_id = $_POST['question_id'];
    $eurid = $_GET['eurId'];
    Database::get()->query("UPDATE exercise_answer_record SET weight = ?d WHERE eurid = ?d AND question_id = ?d", $grade, $eurid, $question_id);
    $ungraded = Database::get()->querySingle("SELECT COUNT(*) AS count FROM exercise_answer_record WHERE eurid = ?d AND weight IS NULL", $eurid)->count;
    if ($ungraded == 0) {
        Database::get()->query("UPDATE exercise_user_record SET attempt_status = ?d, total_score = total_score + ?d WHERE eurid = ?d", ATTEMPT_COMPLETED, $grade, $eurid);
    }
    exit();
}
require_once 'include/lib/modalboxhelper.class.php';
require_once 'include/lib/multimediahelper.class.php';
ModalBoxHelper::loadModalBox();

load_js('tools.js');
load_js('jquery');
$head_content .= "<script type='text/javascript'>                             
    		$(document).ready(function(){
                    $('.questionGradeBox').keyup(function (e) {
                        if (e.keyCode == 13) {
                            grade = parseInt($(this).val());
                            var element_name = $(this).attr('name');
                            var questionId = parseInt(element_name.substring(14,element_name.length - 1));
                            questionMaxGrade = parseInt($(this).next().val());
                            if (grade > questionMaxGrade) {
                                alert('$langGradeTooBig');
                            } else {
                                $.ajax({
                                  type: 'POST',
                                  url: '',
                                  data: {question_grade: grade, question_id: questionId},
                                });
                                $(this).parent().prev().hide();
                                $(this).prop('disabled', true);
                                prev_grade = parseInt($('span#total_score').html());
                                updated_grade = prev_grade + grade;
                                $('span#total_score').html(updated_grade);
                            }
                        }
                    });
    		});
                </script>";

if (isset($_GET['eurId'])) {
    $eurid = $_GET['eurId'];
    $exercise_user_record = Database::get()->querySingle("SELECT * FROM exercise_user_record WHERE eurid = ?d", $eurid);
    $exercise_question_ids = Database::get()->queryArray("SELECT DISTINCT question_id FROM exercise_answer_record WHERE eurid = ?d", $eurid);

    if (!$exercise_user_record) {
        //No record matches with thiw exercise user record id
        Session::set_flashdata($langExerciseNotFound, 'alert1');
        redirect_to_home_page('modules/exercise/index.php?course='.$course_code);
    }
    if (!$is_editor && $exercise_user_record->uid != $uid) {
       // user is not allowed to view other people's exercise results
       redirect_to_home_page('modules/exercise/index.php?course='.$course_code);
    }
    $objExercise = new Exercise();
    $objExercise->read($exercise_user_record->eid);
} else {
    //exercise user recird id is not set
    redirect_to_home_page('modules/exercise/index.php?course='.$course_code);
}

$exerciseTitle = $objExercise->selectTitle();
$exerciseDescription = $objExercise->selectDescription();
$exerciseDescription_temp = nl2br(make_clickable($exerciseDescription));
$exerciseDescription_temp = mathfilter($exerciseDescription_temp, 12, "../../courses/mathimg/");
$displayResults = $objExercise->selectResults();
$displayScore = $objExercise->selectScore();

$tool_content .= "
  <table class='tbl_border' width='99%'>
  <tr class='odd'>
    <td colspan='2'><b>" . q(stripslashes($exerciseTitle)) . "</b>
    <br/>" . standard_text_escape(stripslashes($exerciseDescription_temp)) . "
    </td>
  </tr>
  </table>";


$i = 0;

// for each question
if (count($exercise_question_ids)>0){
    foreach ($exercise_question_ids as $row) {

        // creates a temporary Question object
        $objQuestionTmp = new Question();
        $objQuestionTmp->read($row->question_id);
        // gets the student choice for this question
        $choice = $objQuestionTmp->get_answers_record($eurid);
        $questionName = $objQuestionTmp->selectTitle();
        $questionDescription = $objQuestionTmp->selectDescription();
        $questionDescription_temp = nl2br(make_clickable($questionDescription));
        $questionDescription_temp = mathfilter($questionDescription_temp, 12, "../../courses/mathimg/");
        $questionWeighting = $objQuestionTmp->selectWeighting();
        $answerType = $objQuestionTmp->selectType();
        // destruction of the Question object
        unset($objQuestionTmp);

        if ($answerType == UNIQUE_ANSWER || $answerType == MULTIPLE_ANSWER || $answerType == TRUE_FALSE) {
            $colspan = 4;
        } elseif ($answerType == MATCHING) {
            $colspan = 2;
        } else {
            $colspan = 1;
        }
        $iplus = $i + 1;
        $tool_content .= "
            <br/>
            <table width='100%' class='tbl_alt'>
            <tr class='odd'>
              <td colspan='${colspan}'><b><u>$langQuestion</u>: $iplus</b></td>
            </tr>
            <tr>
              <td class='even' colspan='${colspan}'>
                <b>" . q($questionName) . "</b>
                <br />" .
                standard_text_escape($questionDescription_temp)
                . "<br/><br/>
              </td>
            </tr>";
        if (file_exists($picturePath . '/quiz-' . $row->question_id)) {
            $tool_content .= "here
                      <tr class='even'>
                        <td class='center' colspan='${colspan}'><img src='../../" . ${'picturePath'} . "/quiz-" . ${'questionId'} . "'></td>
                      </tr>";
        }
        $questionScore = 0;

        if ($displayResults == 1 || $is_editor) {
            if ($answerType == UNIQUE_ANSWER || $answerType == MULTIPLE_ANSWER || $answerType == TRUE_FALSE) {
                $tool_content .= "
                            <tr class='even'>
                              <td width='50' valign='top'><b>$langChoice</b></td>
                              <td width='50' class='center' valign='top'><b>$langExpectedChoice</b></td>
                              <td valign='top'><b>$langAnswer</b></td>
                              <td valign='top'><b>$langComment</b></td>
                            </tr>";
            } elseif ($answerType == FILL_IN_BLANKS || $answerType == FREE_TEXT) {
                $tool_content .= "
                            <tr>
                              <td class='even'><b>$langAnswer</b></td>
                            </tr>";       
            } else {
                $tool_content .= "
                            <tr class='even'>
                              <td><b>$langElementList</b></td>
                              <td><b>$langCorrespondsTo</b></td>
                            </tr>";
            }
        }
        if ($answerType != FREE_TEXT) { // if NOT FREE TEXT (i.e. question has answers) 
            // construction of the Answer object
            $objAnswerTmp = new Answer($row->question_id);
            $nbrAnswers = $objAnswerTmp->selectNbrAnswers();

            for ($answerId = 1; $answerId <= $nbrAnswers; $answerId++) {
                $answer = $objAnswerTmp->selectAnswer($answerId);
                $answerComment = $objAnswerTmp->selectComment($answerId);
                $answerCorrect = $objAnswerTmp->isCorrect($answerId);
                $answerWeighting = $objAnswerTmp->selectWeighting($answerId);

                // support for math symbols
                $answer = mathfilter($answer, 12, "../../courses/mathimg/");
                $answerComment = mathfilter($answerComment, 12, "../../courses/mathimg/");

                switch ($answerType) {
                    // for unique answer
                    case UNIQUE_ANSWER : $studentChoice = ($choice == $answerId) ? 1 : 0;
                        if ($studentChoice) {
                            $questionScore+=$answerWeighting;
                        }
                        break;
                    // for multiple answers
                    case MULTIPLE_ANSWER : $studentChoice = @$choice[$answerId];
                        if ($studentChoice) {
                            $questionScore+=$answerWeighting;
                        }
                        break;
                    // for fill in the blanks
                    case FILL_IN_BLANKS : // splits text and weightings that are joined with the char '::'
                        list($answer, $answerWeighting) = explode('::', $answer);
                        // splits weightings that are joined with a comma
                        $answerWeighting = explode(',', $answerWeighting);
                        // we save the answer because it will be modified
                        $temp = $answer;
                        $answer = '';
                        $j = 1;
                        // the loop will stop at the end of the text
                        while (1) {
                            // quits the loop if there are no more blanks
                            if (($pos = strpos($temp, '[')) === false) {
                                // adds the end of the text
                                $answer.=$temp;
                                break;
                            }
                            // adds the piece of text that is before the blank and ended by [
                            $answer.=substr($temp, 0, $pos + 1);
                            $temp = substr($temp, $pos + 1);
                            // quits the loop if there are no more blanks
                            if (($pos = strpos($temp, ']')) === false) {
                                // adds the end of the text
                                $answer.=$temp;
                                break;
                            }
                            $choice[$j] = trim(stripslashes($choice[$j]));
                            // if the word entered is the same as the one defined by the professor
                            if (strtolower(substr($temp, 0, $pos)) == strtolower($choice[$j])) {
                                // gives the related weighting to the student
                                $questionScore+=$answerWeighting[$j-1];
                                // increments total score
                                // adds the word in green at the end of the string
                                $answer.=$choice[$j];
                            }
                            // else if the word entered is not the same as the one defined by the professor
                            elseif (!empty($choice[$j])) {
                                // adds the word in red at the end of the string, and strikes it
                                $answer.='<font color="red"><s>' . $choice[$j] . '</s></font>';
                            } else {
                                // adds a tabulation if no word has been typed by the student
                                $answer.='&nbsp;&nbsp;&nbsp;';
                            }
                            // adds the correct word, followed by ] to close the blank
                            $answer.=' / <font color="green"><b>' . substr($temp, 0, $pos) . '</b></font>]';
                            $j++;
                            $temp = substr($temp, $pos + 1);
                        }
                        break;
                    // for matching
                    case MATCHING : if ($answerCorrect) {
                            if ($answerCorrect == $choice[$answerId]) {
                                $questionScore += $answerWeighting;
                                $choice[$answerId] = $matching[$choice[$answerId]];
                            } elseif (!$choice[$answerId]) {
                                $choice[$answerId] = '&nbsp;&nbsp;&nbsp;';
                            } else {
                                $choice[$answerId] = '<font color="red">
                                                                <s>' . $matching[$choice[$answerId]] . '</s>
                                                                </font>';
                            }
                        } else {
                            $matching[$answerId] = $answer;
                        }
                        break;
                    case TRUE_FALSE : $studentChoice = ($choice == $answerId) ? 1 : 0;
                        if ($studentChoice) {
                            $questionScore += $answerWeighting;
                        }
                        break;
                } // end switch()
                if ($displayResults == 1 || $is_editor) {
                    if ($answerType != MATCHING || $answerCorrect) {
                        if ($answerType == UNIQUE_ANSWER || $answerType == MULTIPLE_ANSWER || $answerType == TRUE_FALSE) {
                            $tool_content .= "
                                                <tr class='even'>
                                                  <td>
                                                  <div align='center'><img src='$themeimg/";
                            if ($answerType == UNIQUE_ANSWER || $answerType == TRUE_FALSE) {
                                $tool_content .= "radio";
                            } else {
                                $tool_content .= "checkbox";
                            }
                            if ($studentChoice) {
                                $tool_content .= "_on";
                            } else {
                                $tool_content .= "_off";
                            }

                            $tool_content .= ".png' /></div>
                                                </td>
                                                <td><div align='center'>";

                            if ($answerType == UNIQUE_ANSWER || $answerType == TRUE_FALSE) {
                                $tool_content .= "<img src='$themeimg/radio";
                            } else {
                                $tool_content .= "<img src='$themeimg/checkbox";
                            }
                            if ($answerCorrect) {
                                $tool_content .= "_on";
                            } else {
                                $tool_content .= "_off";
                            }
                            $tool_content .= ".png' /></div>";
                            $tool_content .= "
                                                </td>
                                                <td>" . standard_text_escape($answer) . "</td>
                                                <td>";
                            if ($studentChoice) {
                                $tool_content .= standard_text_escape(nl2br(make_clickable($answerComment)));
                            } else {
                                $tool_content .= '&nbsp;';
                            }
                            $tool_content .= "</td></tr>";
                        } elseif ($answerType == FILL_IN_BLANKS) {
                            $tool_content .= "
                                                <tr class='even'>
                                                  <td>" . standard_text_escape(nl2br($answer)) . "</td>
                                                </tr>";          
                        } else {
                            $tool_content .= "
                                                <tr class='even'>
                                                  <td>" . standard_text_escape($answer) . "</td>
                                                  <td>${choice[$answerId]} / <font color='green'><b>${matching[$answerCorrect]}</b></font></td>
                                                </tr>";
                        }
                    }
                } // end of if
            } // end for()
        } else { // If FREE TEXT type           
            $tool_content .= "<tr class='even'>
                                 <td>" . purify($choice) . "</td>
                              </tr>";
        }
        $tool_content .= "<tr class='even'>
                            <th colspan='$colspan' class='odd'>";        
        if ($answerType == FREE_TEXT) {
            $choice = purify($choice);
            if (!empty($choice)) {
                $question_weight = Database::get()->querySingle("SELECT weight FROM exercise_answer_record WHERE question_id = ?d AND eurid =?d", $row->question_id, $eurid)->weight;
                $question_graded = is_null($question_weight) ? FALSE : TRUE; 
                if (!$question_graded) {
                    $tool_content .= "<span style='color:red;'>$langAnswerUngraded</span>";   
                } else {
                    $questionScore = $question_weight;
                }
            }
        }        
        if ($displayScore == 1 || $is_editor) {
            if (intval($questionScore) == $questionScore) {
                $questionScore = intval($questionScore);
            }
            if (intval($questionWeighting) == $questionWeighting) {
                $questionWeighting = intval($questionWeighting);
            }
            if ($answerType == FREE_TEXT && $is_editor && isset($question_graded) && !$question_graded) {
             //show input field
             $tool_content .= "<span style='float:right;'>
                               $langQuestionScore: <input type='text' class='questionGradeBox' maxlength='3' size='3' name='questionScore[$row->question_id]'>
                               <input type='hidden' name='questionMaxGrade' value='$questionWeighting'>    
                               <b>/$questionWeighting</b></span>";               
            } else {
            $tool_content .= "<span style='float:right;'>
                                $langQuestionScore: <b>$questionScore/$questionWeighting</b></span>";
            }
        }
        $tool_content .= "</th></tr></table>";
        // destruction of Answer
        unset($objAnswerTmp);
        $i++;
    } // end foreach()
} else {
    redirect_to_home_page('modules/exercise/index.php?course='.$course_code);
}

if ($displayScore == 1 || $is_editor) {
    $tool_content .= "
    <br/>
    <table width='100%' class='tbl_alt'>
    <tr class='odd'>
	<td class='right'><b>$langYourTotalScore: <span id='total_score'>$exercise_user_record->total_score</span> / $exercise_user_record->total_weighting</b>
      </td>
    </tr>
    </table>";
}
$tool_content .= "
  <br/>
  <form method='GET' action='index.php'><input type='hidden' name='course' value='$course_code'/>
  <div align='center'><input type='submit' value='$langReturn' /></div>
  <br />
  </form><br />";

draw($tool_content, 2, null, $head_content);
