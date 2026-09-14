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
 * restore_linkcollection_activity_task.class.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class restore_linkcollection_activity_task.
 */
class restore_linkcollection_activity_task extends restore_activity_task {
    /**
     * Method define_my_settings.
     *
     * @return void Return value.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Method define_my_steps.
     *
     * @return void Return value.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_linkcollection_activity_structure_step("linkcollection_structure", "linkcollection.xml"));
    }

    /**
     * Method define_decode_contents.
     *
     * @return array Return value.
     */
    public static function define_decode_contents(): array {
        return [];
    }

    /**
     * Method define_decode_rules.
     *
     * @return array Return value.
     */
    public static function define_decode_rules(): array {
        return [];
    }
}
