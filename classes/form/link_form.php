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
 * link_form.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_linkcollection\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/formslib.php");

/**
 * Class link_form.
 */
class link_form extends \moodleform {
    /**
     * Method definition.
     *
     * @return void Return value.
     */
    public function definition(): void {
        $mform = $this->_form;
        $sections = $this->_customdata["sections"] ?? [];

        $mform->addElement("hidden", "cmid");
        $mform->setType("cmid", PARAM_INT);
        $mform->addElement("hidden", "id");
        $mform->setType("id", PARAM_INT);

        $mform->addElement("select", "sectionid", get_string("section", "mod_linkcollection"), $sections);
        $mform->addRule("sectionid", null, "required", null, "client");

        $mform->addElement("text", "url", get_string("url", "mod_linkcollection"), ["size" => 80]);
        $mform->setType("url", PARAM_URL);
        $mform->addRule("url", null, "required", null, "client");

        $mform->addElement("text", "customtitle", get_string("customtitle", "mod_linkcollection"), ["size" => 80]);
        $mform->setType("customtitle", PARAM_TEXT);
        $mform->addRule("customtitle", get_string("maximumchars", "", 255), "maxlength", 255, "client");
        $mform->addHelpButton("customtitle", "customtitle", "mod_linkcollection");

        $mform->addElement("textarea", "customdescription", get_string("customdescription", "mod_linkcollection"),
            ["rows" => 4, "cols" => 80]);
        $mform->setType("customdescription", PARAM_TEXT);
        $mform->addHelpButton("customdescription", "customdescription", "mod_linkcollection");

        $mform->addElement("advcheckbox", "fetchmetadata", get_string("fetchmetadata", "mod_linkcollection"));
        $mform->setDefault("fetchmetadata", 1);
        $mform->addHelpButton("fetchmetadata", "metadata", "mod_linkcollection");

        $this->add_action_buttons();
    }

    /**
     * Method validation.
     *
     * @param mixed $data Parameter data.
     * @param mixed $files Parameter files.
     * @return array Return value.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $url = trim((string) ($data["url"] ?? ""));
        if (filter_var($url, FILTER_VALIDATE_URL) === false ||
            !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ["http", "https"], true)) {
            $errors["url"] = get_string("invalidurl", "mod_linkcollection");
        }
        return $errors;
    }
}
