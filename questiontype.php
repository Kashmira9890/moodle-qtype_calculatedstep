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
 * Question type class for the calculated step question type.
 *
 * @package    qtype
 * @subpackage calculatedstep
 * @copyright  2009 Pierre Pichet
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/calculated/questiontype.php');


/**
 * The simple calculated question type.
 *
 * @copyright  2009 Pierre Pichet
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedstep extends qtype_calculated {

    // Used by the function custom_generator_tools.
    public $wizard_pages_number = 1;

    public function save_question_options($question) {
        global $CFG, $DB;
        $context = $question->context;

        // Make it impossible to save bad formulas anywhere.
        $this->validate_question_data($question);

        // Get old versions of the objects.
        if (!$oldanswers = $DB->get_records('question_answers',
                array('question' => $question->id), 'id ASC')) {
            $oldanswers = array();
        }

        if (!$oldoptions = $DB->get_records('question_calculated',
                array('question' => $question->id), 'answer ASC')) {
            $oldoptions = array();
        }

        // Save the units.
        $virtualqtype = $this->get_virtual_qtype();
        $result = $virtualqtype->save_units($question);
        if (isset($result->error)) {
            return $result;
        } else {
            $units = &$result->units;
        }
        // Insert all the new answers.
        foreach ($question->answer as $key => $answerdata) {
            if (is_array($answerdata)) {
                $answerdata = $answerdata['text'];
            }
            if (trim($answerdata) == '') {
                continue;
            }

            // Update an existing answer if possible.
            $answer = array_shift($oldanswers);
            if (!$answer) {
                $answer = new stdClass();
                $answer->question = $question->id;
                $answer->answer   = '';
                $answer->feedback = '';
                $answer->id       = $DB->insert_record('question_answers', $answer);
            }

            $answer->answer   = trim($answerdata);
            $answer->fraction = $question->fraction[$key];
            $answer->feedback = $this->import_or_save_files($question->feedback[$key],
                    $context, 'question', 'answerfeedback', $answer->id);
            $answer->feedbackformat = $question->feedback[$key]['format'];

            $DB->update_record("question_answers", $answer);

            // Set up the options object.
            if (!$options = array_shift($oldoptions)) {
                $options = new stdClass();
            }
            $options->question            = $question->id;
            $options->answer              = $answer->id;
            $options->tolerance           = trim($question->tolerance[$key]);
            $options->tolerancetype       = trim($question->tolerancetype[$key]);
            $options->correctanswerlength = trim($question->correctanswerlength[$key]);
            $options->correctanswerformat = trim($question->correctanswerformat[$key]);

            // Save options.
            if (isset($options->id)) {
                // Reusing existing record.
                $DB->update_record('question_calculated', $options);
            } else {
                // New options.
                $DB->insert_record('question_calculated', $options);
            }
        }

        // Delete old answer records.
        if (!empty($oldanswers)) {
            foreach ($oldanswers as $oa) {
                $DB->delete_records('question_answers', array('id' => $oa->id));
            }
        }

        // Delete old answer records.
        if (!empty($oldoptions)) {
            foreach ($oldoptions as $oo) {
                $DB->delete_records('question_calculated', array('id' => $oo->id));
            }
        }

        if (isset($question->import_process) && $question->import_process) {
            $this->import_datasets($question);
        } else {
            // Save datasets and datatitems from form i.e in question.
            $question->dataset = $question->datasetdef;

            // Save datasets.
            $datasetdefinitions = $this->get_dataset_definitions($question->id, $question->dataset);
            $tmpdatasets = array_flip($question->dataset);
            $defids = array_keys($datasetdefinitions);
            $datasetdefs = array();
            foreach ($defids as $defid) {
                $datasetdef = &$datasetdefinitions[$defid];
                if (isset($datasetdef->id)) {
                    if (!isset($tmpdatasets[$defid])) {
                        // This dataset is not used any more, delete it.
                        $DB->delete_records('question_datasets', array('question' => $question->id,
                                'datasetdefinition' => $datasetdef->id));
                        $DB->delete_records('question_dataset_definitions',
                                array('id' => $datasetdef->id));
                        $DB->delete_records('question_dataset_items',
                                array('definition' => $datasetdef->id));
                    }
                    // This has already been saved or just got deleted.
                    unset($datasetdefinitions[$defid]);
                    continue;
                }
                $datasetdef->id = $DB->insert_record('question_dataset_definitions', $datasetdef);
                $datasetdefs[] = clone($datasetdef);
                $questiondataset = new stdClass();
                $questiondataset->question = $question->id;
                $questiondataset->datasetdefinition = $datasetdef->id;
                $DB->insert_record('question_datasets', $questiondataset);
                unset($datasetdefinitions[$defid]);
            }
            // Remove local obsolete datasets as well as relations
            // to datasets in other categories.
            if (!empty($datasetdefinitions)) {
                foreach ($datasetdefinitions as $def) {
                    $DB->delete_records('question_datasets', array('question' => $question->id,
                            'datasetdefinition' => $def->id));
                    if ($def->category == 0) { // Question local dataset.
                        $DB->delete_records('question_dataset_definitions',
                                array('id' => $def->id));
                        $DB->delete_records('question_dataset_items',
                                array('definition' => $def->id));
                    }
                }
            }
            $datasetdefs = $this->get_dataset_definitions($question->id, $question->dataset);
            // Handle adding and removing of dataset items.
            $i = 1;
            ksort($question->definition);
            foreach ($question->definition as $key => $defid) {
                $addeditem = new stdClass();
                $addeditem->definition = $datasetdefs[$defid]->id;
                $addeditem->value = $question->number[$i];
                $addeditem->itemnumber = ceil($i / count($datasetdefs));
                if (empty($question->makecopy) && $question->itemid[$i]) {
                    // Reuse any previously used record.
                    $addeditem->id = $question->itemid[$i];
                    $DB->update_record('question_dataset_items', $addeditem);
                } else {
                    $DB->insert_record('question_dataset_items', $addeditem);
                }
                $i++;
            }
            $maxnumber = -1;
            if (isset($addeditem->itemnumber) && $maxnumber < $addeditem->itemnumber) {
                $maxnumber = $addeditem->itemnumber;
                foreach ($datasetdefs as $key => $newdef) {
                    if (isset($newdef->id) && $newdef->itemcount <= $maxnumber) {
                        $newdef->itemcount = $maxnumber;
                        // Save the new value for options.
                        $DB->update_record('question_dataset_definitions', $newdef);
                    }
                }
            }
        }

        $this->save_hints($question);

        // Report any problems.
        if (!empty($question->makecopy) && !empty($question->convert)) {
            $DB->set_field('question', 'qtype', 'calculated', array('id' => $question->id));
        }

        $result = $virtualqtype->save_unit_options($question);
        if (isset($result->error)) {
            return $result;
        }

        if (!empty($result->notice)) {
            return $result;
        }

        return true;
    }

    public function save_question($question, $fromform) {
        global $CFG, $DB;
        echo '<br><br><br> in step save dsi';
        echo '<br>==================this=================';
        print_object($this);
        echo '<br>==================fromform=================';
        print_object($fromform);

        // Get the old datasets for this question.
        $datasetdefs = $this->get_dataset_definitions($question->id, array());
        echo '<br>==================datasetdefs=================';
        print_object($datasetdefs);

        $i = 1;
        $fromformdefinition = $fromform->definition;
        $fromformnumber = $fromform->number;// This parameter will be validated in the form.
        $fromformitemid = $fromform->itemid;
        ksort($fromformdefinition);

//         $addeditem = new stdClass();
        foreach ($fromformdefinition as $key => $defid) {
            $addeditem = new stdClass();
            $addeditem->id = $fromformitemid[$i];
            $addeditem->value = $fromformnumber[$i];
            $addeditem->itemnumber = ceil($i / count($datasetdefs));
            $datasetdefs[$defid]->items[$addeditem->itemnumber] = $addeditem;
            $datasetdefs[$defid]->itemcount = $i;
            $i++;
        }

        // Handle generator options...
//         $olddatasetdefs = fullclone($datasetdefs);
//         $datasetdefs = $this->update_dataset_options($datasetdefs, $fromform);
        $maxnumber = -1;
        foreach ($datasetdefs as $defid => $datasetdef) {
//             if (isset($datasetdef->id)
//                     && $datasetdef->options != $olddatasetdefs[$defid]->options) {
//                         // Save the new value for options.
//                         $DB->update_record('question_dataset_definitions', $datasetdef);

//                     }
            if($defid == "1-0-scadans1") {
                    // Get maxnumber.
                    if ($maxnumber == -1 || $datasetdef->itemcount < $maxnumber) {
                        $maxnumber = $datasetdef->itemcount;
                    }
            }
        }
//         // Handle adding and removing of dataset items.
//         $i = 1;
//         if ($maxnumber > parent::MAX_DATASET_ITEMS) {
//             $maxnumber = parent::MAX_DATASET_ITEMS;
//         }

        ksort($fromform->definition);
        $k = 1;
        foreach ($fromform->definition as $key => $defid) {

            if ($k > count($datasetdefs)*$maxnumber) {
//             if ($key > $maxnumber) {
                break;
            }

                // call to our function ..
                //                         echo '<br>============fromformdef==================';
                //                         print_object($fromformdefinition);

                if($defid == "1-0-scadans1") {
                    $itemnumber = ceil($k / count($datasetdefs));
//                     $itemnumber = ceil($key / count($datasetdefs));
                    $scadansvalue = $this->generate_scadans1_value($itemnumber, $fromform, $datasetdefs);

                    //                             $addeditem = new stdClass();
                    //                             $addeditem->id =  $fromformitemid[$k];
                    //                             $addeditem->value = $scadansvalue;
                    $fromform->number[$k]= $scadansvalue;
                    //                             $addeditem->itemnumber = $itemnumber;

                    $datasetdefs[$defid]->items[$itemnumber]->value = $scadansvalue;
                    //OR
                    //$this->datasetdefs[$defid]->items[$addeditem->itemnumber] = $addeditem;
                    //$this->datasetdefs[$defid]->itemcount = $k;
                }
                $k++;
            }
            // }
            parent::save_question($question, $fromform);
    }

    //Copy of comment_on_datasetitems($qtypeobj, $questionid, $questiontext,
    //        $answers, $data, $number) {...}
    protected function generate_scadans1_value($itemnumber, $fromform, $datasetdefs) {
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
                            //                 }
                            //                 }

                            // Requires data ..
                            //                 $kformula = $this->substitute_variables($kanswer->answer, $kdata);   // subs_var is a func of qtype_calc and not this class
                            //                 $this->qtypeobj->

                            //                 echo '<br> kanswer ';
                            //                 print_object($kanswer);
//                             $kformattedanswer = qtype_calculated_calculate_answer(
//                                     $kanswer->answer, $kdata, $kanswer->tolerance,
//                                     $kanswer->tolerancetype, $kanswer->correctanswerlength,
//                                     $kanswer->correctanswerformat);
                            $kformattedanswer = qtype_calculated_calculate_answer(
                                    $kanswer, $kdata, $fromform->tolerance[$key],
                                    $fromform->tolerancetype[$key], $fromform->correctanswerlength[$key],
                                    $fromform->correctanswerformat[$key]);
                            break;
                    }
    }

    //             $scad_ansvalue = qtype_calculated_calculate_answer(  // covered above in code from comment..()
    //                     $answer, $data, $tolerance,
    //                     $tolerancetype, $correctanswerlength,
    //                     $correctanswerformat);

    //             $datasetitem->value = $kformattedanswer->answer;
    return $kformattedanswer->answer;
    //         }
}

public function construct_answer_object($fromform) {
    $nonemptyanswer = array();
    if ($fromform->answer) {

        foreach ($fromform->answer as $key => $answer) {
            if (trim($answer) != '') {  // Just look for non-empty.
                $answerobj[$key] = new stdClass();
                $answerobj[$key]->answer = $answer;
                $answerobj[$key]->fraction = $fromform->fraction[$key];
                $answerobj[$key]->tolerance = $fromform->tolerance[$key];
                $answerobj[$key]->tolerancetype = $fromform->tolerancetype[$key];
                $answerobj[$key]->correctanswerlength = $fromform->correctanswerlength[$key];
                $answerobj[$key]->correctanswerformat = $fromform->correctanswerformat[$key];
                $nonemptyanswer[]= $answerobj[$key];
            }
        }
    }
    return $nonemptyanswer;
}

public function construct_data_object($fromform) {
    $data = array();
}

    public function finished_edit_wizard($form) {
        return true;
    }

    public function wizard_pages_number() {
        return 1;
    }

    public function custom_generator_tools_part($mform, $idx, $j) {

        $minmaxgrp = array();
        $minmaxgrp[] = $mform->createElement('text', "calcmin[{$idx}]",
                get_string('calcmin', 'qtype_calculated'));
        $minmaxgrp[] = $mform->createElement('text', "calcmax[{$idx}]",
                get_string('calcmax', 'qtype_calculated'));
        $mform->addGroup($minmaxgrp, 'minmaxgrp',
                get_string('minmax', 'qtype_calculated'), ' - ', false);
        $mform->setType("calcmin[{$idx}]", PARAM_FLOAT);
        $mform->setType("calcmax[{$idx}]", PARAM_FLOAT);

        $precisionoptions = range(0, 10);
        $mform->addElement('select', "calclength[{$idx}]",
                get_string('calclength', 'qtype_calculated'), $precisionoptions);

        $distriboptions = array('uniform' => get_string('uniform', 'qtype_calculated'),
                'loguniform' => get_string('loguniform', 'qtype_calculated'));
        $mform->addElement('hidden', "calcdistribution[{$idx}]", 'uniform');
        $mform->setType("calcdistribution[{$idx}]", PARAM_INT);
    }

    public function comment_header($answers) {
        $strheader = "";
        $delimiter = '';

        foreach ($answers as $key => $answer) {
            $ans = shorten_text($answer->answer, 17, true);
            $strheader .= $delimiter.$ans;
            $delimiter = '<br/><br/><br/>';
        }
        return $strheader;
    }

    public function tolerance_types() {
        return array(
            '1'  => get_string('relative', 'qtype_numerical'),
            '2'  => get_string('nominal', 'qtype_numerical'),
        );
    }

    public function dataset_options($form, $name, $mandatory = true, $renameabledatasets = false) {
        // Takes datasets from the parent implementation but
        // filters options that are currently not accepted by calculated.
        // It also determines a default selection
        // $renameabledatasets not implemented anywhere.
        list($options, $selected) = $this->dataset_options_from_database(
                $form, $name, '', 'qtype_calculated');

        foreach ($options as $key => $whatever) {
            if (!preg_match('~^1-~', $key) && $key != '0') {
                unset($options[$key]);
            }
        }
        if (!$selected) {
            if ($mandatory) {
                $selected =  "1-0-{$name}"; // Default.
            } else {
                $selected = "0"; // Default.
            }
        }
        return array($options, $selected);
    }
}
