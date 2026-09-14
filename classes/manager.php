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
 * manager.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_linkcollection;

/**
 * Class manager.
 */
class manager {
    /**
     * Property instance.
     *
     * @var \stdClass
     */
    private \stdClass $instance;
    /**
     * Property cm.
     *
     * @var \cm_info|\stdClass
     */
    private \cm_info|\stdClass $cm;
    /**
     * Property context.
     *
     * @var \context_module
     */
    private \context_module $context;

    /**
     * Method __construct.
     *
     * @param \stdClass $instance Parameter instance.
     * @param mixed $cm Parameter cm.
     * @param \context_module $context Parameter context.
     */
    public function __construct(\stdClass $instance, $cm, \context_module $context) {
        $this->instance = $instance;
        $this->cm = $cm;
        $this->context = $context;
    }

    /**
     * Method get_sections.
     *
     * @return array Return value.
     */
    public function get_sections(): array {
        global $DB;
        return array_values($DB->get_records("linkcollection_sections", ["linkcollectionid" => $this->instance->id],
            "sortorder ASC, id ASC"));
    }

    /**
     * Method get_section.
     *
     * @param int $id Parameter id.
     * @return \stdClass Return value.
     */
    public function get_section(int $id): \stdClass {
        global $DB;
        return $DB->get_record("linkcollection_sections", ["id" => $id, "linkcollectionid" => $this->instance->id],
            "*", MUST_EXIST);
    }

    /**
     * Method save_section.
     *
     * @param \stdClass $data Parameter data.
     * @return int Return value.
     */
    public function save_section(\stdClass $data): int {
        global $DB;

        $now = time();
        if (!empty($data->id)) {
            $record = $this->get_section((int) $data->id);
            $record->name = trim($data->name);
            $record->timemodified = $now;
            $DB->update_record("linkcollection_sections", $record);
            return (int) $record->id;
        }

        $sortorder = $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), 0) + 10 FROM {linkcollection_sections} WHERE linkcollectionid = :id",
            ["id" => $this->instance->id]
        );
        return $DB->insert_record("linkcollection_sections", (object) [
            "linkcollectionid" => $this->instance->id,
            "name" => trim($data->name),
            "sortorder" => $sortorder,
            "timecreated" => $now,
            "timemodified" => $now,
        ]);
    }

    /**
     * Method delete_section.
     *
     * @param int $id Parameter id.
     * @return void Return value.
     */
    public function delete_section(int $id): void {
        global $DB;

        $section = $this->get_section($id);
        $links = $DB->get_records("linkcollection_links", ["sectionid" => $section->id]);
        $fs = get_file_storage();
        foreach ($links as $link) {
            $fs->delete_area_files($this->context->id, "mod_linkcollection", "thumbnail", $link->id);
        }
        $DB->delete_records("linkcollection_links", ["sectionid" => $section->id]);
        $DB->delete_records("linkcollection_sections", ["id" => $section->id]);
    }

    /**
     * Method move_section.
     *
     * @param int $id Parameter id.
     * @param string $direction Parameter direction.
     * @return void Return value.
     */
    public function move_section(int $id, string $direction): void {
        $this->move_record("linkcollection_sections", $id, "linkcollectionid", $this->instance->id, $direction);
    }

    /**
     * Method get_link.
     *
     * @param int $id Parameter id.
     * @return \stdClass Return value.
     */
    public function get_link(int $id): \stdClass {
        global $DB;
        return $DB->get_record("linkcollection_links", ["id" => $id, "linkcollectionid" => $this->instance->id], "*", MUST_EXIST);
    }

    /**
     * Method save_link.
     *
     * @param \stdClass $data Parameter data.
     * @return int Return value.
     */
    public function save_link(\stdClass $data): int {
        global $DB;

        $section = $this->get_section((int) $data->sectionid);
        $now = time();
        $manualtitle = trim((string) ($data->customtitle ?? ""));
        $manualdescription = trim((string) ($data->customdescription ?? ""));

        $url = trim($data->url);
        if (!empty($data->id)) {
            $record = $this->get_link((int) $data->id);
            $sectionchanged = (int) $record->sectionid !== (int) $section->id;
            $urlchanged = $record->url !== $url;
            $wasmanualtitle = !empty($record->titlemanual);
            $wasmanualdescription = !empty($record->descriptionmanual);

            $record->sectionid = $section->id;
            $record->url = $url;
            $record->titlemanual = $manualtitle !== "" ? 1 : 0;
            $record->descriptionmanual = $manualdescription !== "" ? 1 : 0;

            if ($manualtitle !== "") {
                $record->title = $manualtitle;
            } else if ($urlchanged || $wasmanualtitle) {
                $record->title = $this->fallback_title($url);
            }
            if ($manualdescription !== "") {
                $record->description = $manualdescription;
            } else if ($urlchanged || $wasmanualdescription) {
                $record->description = "";
            }

            if ($urlchanged) {
                $record->domain = strtolower((string) parse_url($url, PHP_URL_HOST));
                $record->imageurl = "";
                $record->metadatafetched = 0;
                get_file_storage()->delete_area_files($this->context->id, "mod_linkcollection", "thumbnail", $record->id);
            }
            if ($sectionchanged) {
                $record->sortorder = $this->next_link_sortorder($section->id);
            }
            $record->timemodified = $now;
            $DB->update_record("linkcollection_links", $record);
            $id = (int) $record->id;
        } else {
            $id = $DB->insert_record("linkcollection_links", (object) [
                "linkcollectionid" => $this->instance->id,
                "sectionid" => $section->id,
                "url" => $url,
                "title" => $manualtitle !== "" ? $manualtitle : $this->fallback_title($url),
                "description" => $manualdescription,
                "domain" => strtolower((string) parse_url($url, PHP_URL_HOST)),
                "imageurl" => "",
                "titlemanual" => $manualtitle !== "" ? 1 : 0,
                "descriptionmanual" => $manualdescription !== "" ? 1 : 0,
                "sortorder" => $this->next_link_sortorder($section->id),
                "metadatafetched" => 0,
                "timecreated" => $now,
                "timemodified" => $now,
            ]);
        }

        if (!empty($data->fetchmetadata)) {
            $this->refresh_metadata($id);
        }
        return $id;
    }

    /**
     * Method refresh_metadata.
     *
     * @param int $id Parameter id.
     * @return bool Return value.
     */
    public function refresh_metadata(int $id): bool {
        global $DB;

        $record = $this->get_link($id);
        $fetcher = new metadata_fetcher();
        try {
            $metadata = $fetcher->fetch($record->url);
        } catch (\Throwable $e) {
            return false;
        }

        if (empty($record->titlemanual)) {
            $record->title = $metadata["title"];
        }
        if (empty($record->descriptionmanual)) {
            $record->description = $metadata["description"];
        }
        $record->domain = $metadata["domain"];
        $record->imageurl = $metadata["imageurl"];
        $record->metadatafetched = time();
        $record->timemodified = time();
        $DB->update_record("linkcollection_links", $record);

        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, "mod_linkcollection", "thumbnail", $record->id);
        if ($record->imageurl !== "") {
            $image = $fetcher->fetch_image($record->imageurl);
            if ($image) {
                $fs->create_file_from_string([
                    "contextid" => $this->context->id,
                    "component" => "mod_linkcollection",
                    "filearea" => "thumbnail",
                    "itemid" => $record->id,
                    "filepath" => "/",
                    "filename" => "thumbnail." . $image["extension"],
                    "mimetype" => $image["mimetype"],
                ], $image["content"]);
            }
        }
        return true;
    }

    /**
     * Method delete_link.
     *
     * @param int $id Parameter id.
     * @return void Return value.
     */
    public function delete_link(int $id): void {
        global $DB;
        $record = $this->get_link($id);
        get_file_storage()->delete_area_files($this->context->id, "mod_linkcollection", "thumbnail", $record->id);
        $DB->delete_records("linkcollection_links", ["id" => $record->id]);
    }

    /**
     * Method move_link.
     *
     * @param int $id Parameter id.
     * @param string $direction Parameter direction.
     * @return void Return value.
     */
    public function move_link(int $id, string $direction): void {
        $record = $this->get_link($id);
        $this->move_record("linkcollection_links", $id, "sectionid", $record->sectionid, $direction);
    }

    /**
     * Method get_view_data.
     *
     * @return array Return value.
     */
    public function get_view_data(): array {
        global $DB;

        $sections = [];
        foreach ($this->get_sections() as $section) {
            $items = [];
            $links = $DB->get_records("linkcollection_links", ["sectionid" => $section->id], "sortorder ASC, id ASC");
            foreach ($links as $link) {
                $thumb = $this->get_thumbnail_url($link->id);
                $items[] = [
                    "title" => $link->title !== "" ? $link->title : $link->url,
                    "url" => $link->url,
                    "domain" => $link->domain !== "" ? $link->domain : strtolower((string) parse_url($link->url, PHP_URL_HOST)),
                    "description" => trim((string) $link->description),
                    "hasdescription" => trim((string) $link->description) !== "",
                    "thumbnail" => $thumb ?: "",
                    "hasthumbnail" => $thumb !== null,
                ];
            }
            $sections[] = [
                "name" => $section->name,
                "links" => array_values($items),
                "haslinks" => !empty($items),
            ];
        }

        return [
            "sections" => $sections,
            "hassections" => !empty($sections),
        ];
    }

    /**
     * Method get_thumbnail_url.
     *
     * @param int $linkid Parameter linkid.
     * @return ?string Return value.
     */
    private function get_thumbnail_url(int $linkid): ?string {
        $files = get_file_storage()->get_area_files(
            $this->context->id,
            "mod_linkcollection",
            "thumbnail",
            $linkid,
            "id DESC",
            false
        );
        if (!$files) {
            return null;
        }
        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $this->context->id,
            "mod_linkcollection",
            "thumbnail",
            $linkid,
            $file->get_filepath(),
            $file->get_filename(),
            false
        )->out(false);
    }

    /**
     * Method fallback_title.
     *
     * @param string $url Parameter url.
     * @return string Return value.
     */
    private function fallback_title(string $url): string {
        $path = trim((string) parse_url($url, PHP_URL_PATH), "/");
        if ($path !== "") {
            $title = urldecode(basename($path));
            if ($title !== "") {
                return \core_text::substr($title, 0, 255);
            }
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        return $host !== "" ? $host : \core_text::substr($url, 0, 255);
    }

    /**
     * Method next_link_sortorder.
     *
     * @param int $sectionid Parameter sectionid.
     * @return int Return value.
     */
    private function next_link_sortorder(int $sectionid): int {
        global $DB;
        return $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), 0) + 10 FROM {linkcollection_links} WHERE sectionid = :id",
            ["id" => $sectionid]
        );
    }

    /**
     * Method move_record.
     *
     * @param string $table Parameter table.
     * @param int $id Parameter id.
     * @param string $groupfield Parameter groupfield.
     * @param int $groupid Parameter groupid.
     * @param string $direction Parameter direction.
     * @return void Return value.
     */
    private function move_record(string $table, int $id, string $groupfield, int $groupid, string $direction): void {
        global $DB;

        if (!in_array($direction, ["up", "down"], true)) {
            return;
        }
        $record = $DB->get_record($table, ["id" => $id, $groupfield => $groupid], "*", MUST_EXIST);
        $operator = $direction === "up" ? "<" : ">";
        $sort = $direction === "up" ? "sortorder DESC, id DESC" : "sortorder ASC, id ASC";
        $sql = "SELECT * FROM {{$table}} WHERE {$groupfield} = :groupid AND sortorder {$operator} :sortorder ORDER BY {$sort}";
        $others = $DB->get_records_sql($sql, ["groupid" => $groupid, "sortorder" => $record->sortorder], 0, 1);
        $other = $others ? reset($others) : false;
        if (!$other) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();
        $old = $record->sortorder;
        $record->sortorder = $other->sortorder;
        $other->sortorder = $old;
        $record->timemodified = time();
        $other->timemodified = time();
        $DB->update_record($table, $record);
        $DB->update_record($table, $other);
        $transaction->allow_commit();
    }
}
