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
 * view.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$cm = get_coursemodule_from_id("linkcollection", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$instance = $DB->get_record("linkcollection", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability("mod/linkcollection:view", $context);

$PAGE->set_url(new moodle_url("/mod/linkcollection/view.php", ["id" => $cm->id]));
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

$event = \mod_linkcollection\event\course_module_viewed::create([
    "objectid" => $instance->id,
    "context" => $context,
]);
$event->add_record_snapshot("course", $course);
$event->add_record_snapshot("course_modules", $cm);
$event->add_record_snapshot("linkcollection", $instance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$manager = new \mod_linkcollection\manager($instance, $cm, $context);
$data = $manager->get_view_data();
$data["emptycollection"] = get_string("emptycollection", "mod_linkcollection");
$data["emptysection"] = get_string("emptysection", "mod_linkcollection");
$data["openlink"] = get_string("openlink", "mod_linkcollection");

if (has_capability("mod/linkcollection:manage", $context)) {
    $PAGE->set_button($OUTPUT->single_button(
        new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]),
        get_string("managecollection", "mod_linkcollection"),
        "get"
    ));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));
if (trim((string) $instance->intro) !== "") {
    echo $OUTPUT->box(format_module_intro("linkcollection", $instance, $cm->id), "generalbox mod_introbox");
}
echo $OUTPUT->render_from_template("mod_linkcollection/view", $data);
echo $OUTPUT->footer();
