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
 * lib.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * linkcollection_supports
 *
 * @param $feature
 * @return string|true|null
 */
function linkcollection_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        default => null,
    };
}

/**
 * linkcollection_add_instance
 *
 * @param stdClass $data
 * @param $mform
 * @return int
 * @throws coding_exception
 * @throws dml_exception
 */
function linkcollection_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    $id = $DB->insert_record("linkcollection", $data);

    $section = (object) [
        "linkcollectionid" => $id,
        "name" => get_string("defaultsection", "mod_linkcollection"),
        "sortorder" => 10,
        "timecreated" => $now,
        "timemodified" => $now,
    ];
    $DB->insert_record("linkcollection_sections", $section);

    return $id;
}

/**
 * linkcollection_update_instance
 *
 * @param stdClass $data
 * @param $mform
 * @return bool
 * @throws dml_exception
 */
function linkcollection_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record("linkcollection", $data);
}

/**
 * linkcollection_delete_instance
 *
 * @param int $id
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function linkcollection_delete_instance(int $id): bool {
    global $DB;

    $instance = $DB->get_record("linkcollection", ["id" => $id]);
    if (!$instance) {
        return false;
    }

    $cm = get_coursemodule_from_instance("linkcollection", $id, $instance->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, "mod_linkcollection", "thumbnail");
    }

    $DB->delete_records("linkcollection_links", ["linkcollectionid" => $id]);
    $DB->delete_records("linkcollection_sections", ["linkcollectionid" => $id]);
    $DB->delete_records("linkcollection", ["id" => $id]);
    return true;
}

/**
 * linkcollection_pluginfile
 *
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param array $options
 * @return false|void
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 * @throws required_capability_exception
 */
function linkcollection_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== "thumbnail") {
        return false;
    }

    require_login($course, true, $cm);
    require_capability("mod/linkcollection:view", $context);

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? "/" . implode("/", $args) . "/" : "/";

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, "mod_linkcollection", "thumbnail", $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, false, $options);
}
