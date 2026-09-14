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
 * editsection.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$cmid = required_param("cmid", PARAM_INT);
$id = optional_param("id", 0, PARAM_INT);

$cm = get_coursemodule_from_id("linkcollection", $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$instance = $DB->get_record("linkcollection", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability("mod/linkcollection:manage", $context);

$PAGE->set_url(new moodle_url("/mod/linkcollection/editsection.php", ["cmid" => $cm->id, "id" => $id]));
$PAGE->set_title(get_string($id ? "editsection" : "addsection", "mod_linkcollection"));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

$manager = new \mod_linkcollection\manager($instance, $cm, $context);
$form = new \mod_linkcollection\form\section_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]));
}

if ($data = $form->get_data()) {
    $manager->save_section($data);
    redirect(new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]), get_string("linkssaved", "mod_linkcollection"));
}

$defaults = (object) ["cmid" => $cm->id, "id" => 0, "name" => ""];
if ($id) {
    $section = $manager->get_section($id);
    $defaults->id = $section->id;
    $defaults->name = $section->name;
}
$form->set_data($defaults);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? "editsection" : "addsection", "mod_linkcollection"));
$form->display();
echo $OUTPUT->footer();
