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
 * backup_linkcollection_stepslib.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_linkcollection_activity_structure_step extends backup_activity_structure_step {
    /**
     * Method define_structure.
     *
     * @return mixed Return value.
     */
    protected function define_structure() {
        $linkcollection = new backup_nested_element("linkcollection", ["id"], [
            "name", "intro", "introformat", "timecreated", "timemodified",
        ]);
        $sections = new backup_nested_element("sections");
        $section = new backup_nested_element("section", ["id"], [
            "name", "sortorder", "timecreated", "timemodified",
        ]);
        $links = new backup_nested_element("links");
        $link = new backup_nested_element("link", ["id"], [
            "url", "title", "description", "domain", "imageurl", "titlemanual", "descriptionmanual",
            "sortorder", "metadatafetched", "timecreated", "timemodified",
        ]);

        $linkcollection->add_child($sections);
        $sections->add_child($section);
        $section->add_child($links);
        $links->add_child($link);

        $linkcollection->set_source_table("linkcollection", ["id" => backup::VAR_ACTIVITYID]);
        $section->set_source_table("linkcollection_sections", ["linkcollectionid" => backup::VAR_PARENTID]);
        $link->set_source_table("linkcollection_links", ["sectionid" => backup::VAR_PARENTID]);

        $linkcollection->annotate_files("mod_linkcollection", "intro", null);
        $link->annotate_files("mod_linkcollection", "thumbnail", "id");

        return $this->prepare_activity_structure($linkcollection);
    }
}
