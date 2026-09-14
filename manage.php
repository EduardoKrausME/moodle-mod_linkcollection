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
 * manage.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);
$sectionid = optional_param("sectionid", 0, PARAM_INT);
$linkid = optional_param("linkid", 0, PARAM_INT);
$direction = optional_param("direction", "", PARAM_ALPHA);
$confirm = optional_param("confirm", 0, PARAM_BOOL);

$cm = get_coursemodule_from_id("linkcollection", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$instance = $DB->get_record("linkcollection", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability("mod/linkcollection:manage", $context);

$PAGE->set_url(new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]));
$PAGE->set_title(get_string("managecollection", "mod_linkcollection"));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->navbar->add(get_string("managecollection", "mod_linkcollection"));

$manager = new \mod_linkcollection\manager($instance, $cm, $context);
$manageurl = new moodle_url("/mod/linkcollection/manage.php", ["id" => $cm->id]);

if ($action !== "") {
    require_sesskey();
    switch ($action) {
        case "movesection":
            $manager->move_section($sectionid, $direction);
            redirect($manageurl);
            break;
        case "movelink":
            $manager->move_link($linkid, $direction);
            redirect($manageurl);
            break;
        case "refresh":
            $ok = $manager->refresh_metadata($linkid);
            redirect(
                $manageurl,
                get_string($ok ? "metadatarefreshed" : "metadatafetchfailed", "mod_linkcollection"),
                null,
                $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
            );
            break;
        case "deletelink":
            $link = $manager->get_link($linkid);
            if (!$confirm) {
                echo $OUTPUT->header();
                echo $OUTPUT->heading(get_string("deletelink", "mod_linkcollection"));
                $yes = new moodle_url($manageurl, [
                    "action" => "deletelink",
                    "linkid" => $link->id,
                    "confirm" => 1,
                    "sesskey" => sesskey(),
                ]);
                echo $OUTPUT->confirm(get_string("confirmdeletelink", "mod_linkcollection"), $yes, $manageurl);
                echo $OUTPUT->footer();
                exit;
            }
            $manager->delete_link($link->id);
            redirect($manageurl, get_string("linkdeleted", "mod_linkcollection"));
            break;
        case "deletesection":
            $section = $manager->get_section($sectionid);
            if (!$confirm) {
                echo $OUTPUT->header();
                echo $OUTPUT->heading(get_string("deletesection", "mod_linkcollection"));
                $yes = new moodle_url($manageurl, [
                    "action" => "deletesection",
                    "sectionid" => $section->id,
                    "confirm" => 1,
                    "sesskey" => sesskey(),
                ]);
                echo $OUTPUT->confirm(get_string("confirmdeletesection", "mod_linkcollection"), $yes, $manageurl);
                echo $OUTPUT->footer();
                exit;
            }
            $manager->delete_section($section->id);
            redirect($manageurl, get_string("sectiondeleted", "mod_linkcollection"));
            break;
    }
}

$sections = $manager->get_sections();
$linksbysection = [];
foreach ($sections as $section) {
    $linksbysection[$section->id] = array_values($DB->get_records(
        "linkcollection_links",
        ["sectionid" => $section->id],
        "sortorder ASC, id ASC"
    ));
}

$PAGE->set_button($OUTPUT->single_button(
    new moodle_url("/mod/linkcollection/view.php", ["id" => $cm->id]),
    get_string("backtocollection", "mod_linkcollection"),
    "get"
));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("managecollection", "mod_linkcollection"));

echo html_writer::start_div("mb-4 d-flex gap-2 flex-wrap");
echo $OUTPUT->single_button(
    new moodle_url("/mod/linkcollection/editsection.php", ["cmid" => $cm->id]),
    get_string("addsection", "mod_linkcollection"),
    "get",
    ["class" => "btn-primary"]
);
echo html_writer::end_div();

if (!$sections) {
    echo $OUTPUT->notification(get_string("nosections", "mod_linkcollection"), "info");
}

foreach ($sections as $section) {
    echo html_writer::start_div("card mb-4");
    echo html_writer::start_div("card-header d-flex justify-content-between align-items-center gap-3");
    echo html_writer::tag("h3", format_string($section->name), ["class" => "h5 mb-0"]);

    echo html_writer::start_div("d-flex gap-1 flex-wrap");
    $sectionactions = [
        [
            "editsection",
            new moodle_url("/mod/linkcollection/editsection.php", ["cmid" => $cm->id, "id" => $section->id]),
            "i/edit",
        ],
        [
            "moveup",
            new moodle_url($manageurl, [
                "action" => "movesection",
                "sectionid" => $section->id,
                "direction" => "up",
                "sesskey" => sesskey(),
            ]),
            "t/up",
        ],
        [
            "movedown",
            new moodle_url($manageurl, [
                "action" => "movesection",
                "sectionid" => $section->id,
                "direction" => "down",
                "sesskey" => sesskey(),
            ]),
            "t/down",
        ],
        [
            "deletesection",
            new moodle_url($manageurl, ["action" => "deletesection", "sectionid" => $section->id, "sesskey" => sesskey()]),
            "i/delete",
        ],
    ];
    foreach ($sectionactions as [$label, $url, $icon]) {
        echo $OUTPUT->action_icon($url, new pix_icon($icon, get_string($label, "mod_linkcollection")));
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div("card-body");
    echo $OUTPUT->single_button(
        new moodle_url("/mod/linkcollection/editlink.php", ["cmid" => $cm->id, "sectionid" => $section->id]),
        get_string("addlink", "mod_linkcollection"),
        "get"
    );

    $links = $linksbysection[$section->id] ?? [];
    if (!$links) {
        echo html_writer::div(get_string("emptysection", "mod_linkcollection"), "text-muted mt-3");
    } else {
        echo html_writer::start_tag("ul", ["class" => "list-group list-group-flush mt-3"]);
        foreach ($links as $link) {
            echo html_writer::start_tag("li", ["class" => "list-group-item px-0"]);
            echo html_writer::start_div("d-flex justify-content-between gap-3");
            echo html_writer::start_div("flex-grow-1 overflow-hidden");
            echo html_writer::tag("div", format_string($link->title !== "" ? $link->title : $link->url), ["class" => "fw-bold"]);
            echo html_writer::tag("div", s($link->url), ["class" => "small text-muted text-truncate"]);
            if (trim((string) $link->description) !== "") {
                echo html_writer::tag("div", s($link->description), ["class" => "small mt-1"]);
            }
            echo html_writer::end_div();

            echo html_writer::start_div("d-flex gap-1 align-items-start flex-wrap");
            $linkactions = [
                [
                    "editlink",
                    new moodle_url("/mod/linkcollection/editlink.php", ["cmid" => $cm->id, "id" => $link->id]),
                    "i/edit",
                ],
                [
                    "refreshmetadata",
                    new moodle_url($manageurl, ["action" => "refresh", "linkid" => $link->id, "sesskey" => sesskey()]),
                    "i/reload",
                ],
                [
                    "moveup",
                    new moodle_url($manageurl, [
                        "action" => "movelink",
                        "linkid" => $link->id,
                        "direction" => "up",
                        "sesskey" => sesskey(),
                    ]),
                    "t/up",
                ],
                [
                    "movedown",
                    new moodle_url($manageurl, [
                        "action" => "movelink",
                        "linkid" => $link->id,
                        "direction" => "down",
                        "sesskey" => sesskey(),
                    ]),
                    "t/down",
                ],
                [
                    "deletelink",
                    new moodle_url($manageurl, ["action" => "deletelink", "linkid" => $link->id, "sesskey" => sesskey()]),
                    "i/delete",
                ],
            ];
            foreach ($linkactions as [$label, $url, $icon]) {
                echo $OUTPUT->action_icon($url, new pix_icon($icon, get_string($label, "mod_linkcollection")));
            }
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_tag("li");
        }
        echo html_writer::end_tag("ul");
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
