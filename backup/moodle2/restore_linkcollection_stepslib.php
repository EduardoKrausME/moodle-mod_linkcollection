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
 * restore_linkcollection_stepslib.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_linkcollection_activity_structure_step extends restore_activity_structure_step {
    /**
     * Method define_structure.
     *
     * @return array Return value.
     */
    protected function define_structure(): array {
        return $this->prepare_activity_structure([
            new restore_path_element("linkcollection", "/activity/linkcollection"),
            new restore_path_element("linkcollection_section", "/activity/linkcollection/sections/section"),
            new restore_path_element("linkcollection_link", "/activity/linkcollection/sections/section/links/link"),
        ]);
    }

    /**
     * Method process_linkcollection.
     *
     * @param mixed $data Parameter data.
     * @return void Return value.
     */
    protected function process_linkcollection($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record("linkcollection", $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping("linkcollection", $oldid, $newitemid, true);
    }

    /**
     * Method process_linkcollection_section.
     *
     * @param mixed $data Parameter data.
     * @return void Return value.
     */
    protected function process_linkcollection_section($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->linkcollectionid = $this->get_new_parentid("linkcollection");
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record("linkcollection_sections", $data);
        $this->set_mapping("linkcollection_section", $oldid, $newitemid, true);
    }

    /**
     * Method process_linkcollection_link.
     *
     * @param mixed $data Parameter data.
     * @return void Return value.
     */
    protected function process_linkcollection_link($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->linkcollectionid = $this->get_new_parentid("linkcollection");
        $data->sectionid = $this->get_new_parentid("linkcollection_section");
        $data->metadatafetched = $data->metadatafetched ? $this->apply_date_offset($data->metadatafetched) : 0;
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record("linkcollection_links", $data);
        $this->set_mapping("linkcollection_link", $oldid, $newitemid, true);
        $this->add_related_files("mod_linkcollection", "thumbnail", "linkcollection_link", null, $oldid);
    }

    /**
     * Method after_execute.
     *
     * @return void Return value.
     */
    protected function after_execute(): void {
        $this->add_related_files("mod_linkcollection", "intro", null);
    }
}
