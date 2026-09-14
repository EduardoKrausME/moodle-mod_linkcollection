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
 * editlink.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$cmid = required_param("cmid", PARAM_INT);
$id = optional_param("id", 0, PARAM_INT);
$sectionid = optional_param("sectionid", 0, PARAM_INT);

$cm = get_coursemodule_from_id("linkcollection", $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$instance = $DB->get_record("linkcollection", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability("mod/linkcollection:manage", $context);

$PAGE->set_url(new moodle_url("/mod/linkcollection/editlink.php", ["cmid" => $cm->id, "id" => $id, "sectionid" => $sectionid]));
$PAGE->set_title(get_string($id ? "editlink" : "addlink", "mod_linkcollection"));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

$manager = new \mod_linkcollection\manager($instance, $cm, $context);
$sectionoptions = [];
foreach ($manager->get_sections() as $section) {
    $sectionoptions[$section->id] = format_string($section->name);
}
if (!$sectionoptions) {
    redirect(new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]), get_string("nosections", "mod_linkcollection"));
}

$form = new \mod_linkcollection\form\link_form(null, ["sections" => $sectionoptions]);
if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]));
}

if ($data = $form->get_data()) {
    $linkid = $manager->save_link($data);
    $link = $manager->get_link($linkid);
    $message = !empty($data->fetchmetadata) && empty($link->metadatafetched)
        ? get_string("metadatafetchfailed", "mod_linkcollection")
        : get_string("linkssaved", "mod_linkcollection");
    $type = !empty($data->fetchmetadata) && empty($link->metadatafetched)
        ? \core\output\notification::NOTIFY_WARNING
        : \core\output\notification::NOTIFY_SUCCESS;
    redirect(new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]), $message, null, $type);
}

$defaults = (object) [
    "cmid" => $cm->id,
    "id" => 0,
    "sectionid" => $sectionid ?: (int) array_key_first($sectionoptions),
    "url" => "",
    "customtitle" => "",
    "customdescription" => "",
    "fetchmetadata" => 1,
];
if ($id) {
    $link = $manager->get_link($id);
    $defaults->id = $link->id;
    $defaults->sectionid = $link->sectionid;
    $defaults->url = $link->url;
    $defaults->customtitle = !empty($link->titlemanual) ? $link->title : "";
    $defaults->customdescription = !empty($link->descriptionmanual) ? $link->description : "";
    $defaults->fetchmetadata = 0;
}
$form->set_data($defaults);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? "editlink" : "addlink", "mod_linkcollection"));
$form->display();
echo $OUTPUT->footer();
