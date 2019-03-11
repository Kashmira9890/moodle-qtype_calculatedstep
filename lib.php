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
 * Serve question type files
 *
 * @since      Moodle 2.0
 * @package    qtype_calculatedsimple
 * @copyright  Dongsheng Cai <dongsheng@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Checks file access for calculated step questions.
 *
 * @package  qtype_calculatedstep
 * @category files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function qtype_calculatedstep_pluginfile($course, $cm, $context, $filearea,
        $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_calculatedstep', $filearea,
            $args, $forcedownload, $options);
}

function generate_scadans_value($answeritem){
    $formattedanswer = qtype_calculated_calculate_answer(
            $answeritem->answer, $answeritem->data, $tolerance = 0,
            $tolerancetype = 0, $answerlength = 9,
            $answerformat = 0);


    //             $scad_ansvalue = qtype_calculated_calculate_answer(  // covered above in code from comment..()
    //                     $answer, $data, $tolerance,
    //                     $tolerancetype, $correctanswerlength,
    //                     $correctanswerformat);

    //             $datasetitem->value = $kformattedanswer->answer;
    return $formattedanswer->answer;
    //         }
}
/*
function generate_scadans_value($answeritem) {
    // see for $data value for evaluation of below functn

    //         if($defid == "1-0-scadans1") {

    // Either this .. if possible checkout
    //         $comment = $this->qtypeobj->comment_on_datasetitems(
    //                 $this->qtypeobj, $this->question->id,
            //                 $this->question->questiontext, $this->nonemptyanswer,
            //                 $data, $itemnumber);

            // OR this .. code from comment_on_dsitems(..) ..
            //======================================================================
    //         $kanswers = fullclone($fromform->answer);   // OR
    //         $kanswers1 = fullclone($fromform->answer[0]);
            $kanswers = fullclone($fromform->answer);

            //         echo '<br> kanswers ';
            //         print_object($kanswers);

            //         $delimiter = ': ';
            //         $virtualqtype =  $this->qtypeobj->get_virtual_qtype();
            foreach ($kanswers as $key => $kanswer) {
                $kdata = array();

                //             if ($kkey == 0) {
                //                     $error = qtype_calculated_find_formula_errors($kanswer->answer);
                $error = qtype_calculated_find_formula_errors($kanswer);

                //             if ($error) {
                //                 $comment->stranswers[$key] = $error;
                //                 continue;
                //             }

                // Calc $data
                if (!empty($datasetdefs)) {
                    //                 $j = $this->noofitems * count($this->datasetdefs);
                    //                 for ($itemnumber = $this->noofitems; $itemnumber >= 1; $itemnumber--) {
                    //                 for ($itemnumber = 1; $itemnumber >= 0; $itemnumber--) {
                        foreach ($datasetdefs as $kdefid => $kdatasetdef) {
                            //                         echo '<br> datasetdef ';
                            //                         print_object($kdatasetdef);
                            if (isset($kdatasetdef->items[$itemnumber])) {
                                $kdata[$kdatasetdef->name] = $kdatasetdef->items[$itemnumber]->value;
                            }
                        }

                        // Tolerance parameters are not used in Moodle 3.5.
                        // Answer format = 0 implies significant digits of answer length.
                        // The maximum precision of the scadans is stored in the database.
                        $formattedanswer = qtype_calculated_calculate_answer(
                                $answeritem->answer, $answeritem->data, $tolerance = 0,
                                $tolerancetype = 0, $answerlength = 9,
                                $answerformat = 0);
                        break;
                }
}

//             $scad_ansvalue = qtype_calculated_calculate_answer(  // covered above in code from comment..()
//                     $answer, $data, $tolerance,
//                     $tolerancetype, $correctanswerlength,
//                     $correctanswerformat);

//             $datasetitem->value = $kformattedanswer->answer;
return $formattedanswer->answer;
//         }
}*/
