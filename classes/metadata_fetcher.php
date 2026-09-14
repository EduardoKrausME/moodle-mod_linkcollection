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
 * metadata_fetcher.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_linkcollection;

/**
 * Class metadata_fetcher.
 */
class metadata_fetcher {
    /** @var int */
    private const PAGE_MAX_BYTES = 524288;

    /** @var int */
    private const IMAGE_MAX_BYTES = 2097152;

    /**
     * Method fetch.
     *
     * @param string $url Parameter url.
     * @return array Return value.
     */
    public function fetch(string $url): array {
        global $CFG;

        require_once($CFG->libdir . "/filelib.php");

        $url = trim($url);
        if (!$this->is_valid_url($url)) {
            throw new \moodle_exception("invalidurl", "mod_linkcollection");
        }

        $securityhelper = new \core\files\curl_security_helper();
        if ($securityhelper->url_is_blocked($url)) {
            throw new \moodle_exception("blockedurl", "mod_linkcollection");
        }

        $curl = new \curl();
        $curl->setHeader([
            "Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8",
            "Accept-Language: " . str_replace("_", "-", current_language()) . ",en;q=0.5",
        ]);
        $options = [
            "CURLOPT_TIMEOUT" => 8,
            "CURLOPT_CONNECTTIMEOUT" => 4,
            "CURLOPT_FOLLOWLOCATION" => 1,
            "CURLOPT_MAXREDIRS" => 5,
            "CURLOPT_RANGE" => "0-" . (self::PAGE_MAX_BYTES - 1),
        ];
        $html = $curl->get($url, [], $options);
        $info = $curl->get_info();

        if ($curl->get_errno() || $html === false || empty($info["http_code"]) || (int) $info["http_code"] >= 400) {
            throw new \moodle_exception("remotefetchfailed", "mod_linkcollection");
        }

        $contenttype = strtolower((string) ($info["content_type"] ?? ""));
        if ($contenttype !== "" &&
            !str_contains($contenttype, "text/html") &&
            !str_contains($contenttype, "application/xhtml+xml")) {
            return $this->fallback_metadata($url);
        }

        if (strlen($html) > self::PAGE_MAX_BYTES) {
            $html = substr($html, 0, self::PAGE_MAX_BYTES);
        }

        $finalurl = !empty($info["url"]) && $this->is_valid_url((string) $info["url"]) ? (string) $info["url"] : $url;
        return $this->parse_html($html, $finalurl);
    }

    /**
     * Method fetch_image.
     *
     * @param string $url Parameter url.
     * @return ?array Return value.
     */
    public function fetch_image(string $url): ?array {
        global $CFG;

        require_once($CFG->libdir . "/filelib.php");

        if (!$this->is_valid_url($url)) {
            return null;
        }

        $securityhelper = new \core\files\curl_security_helper();
        if ($securityhelper->url_is_blocked($url)) {
            return null;
        }

        $curl = new \curl();
        $curl->setHeader(["Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8"]);
        $data = $curl->get($url, [], [
            "CURLOPT_TIMEOUT" => 8,
            "CURLOPT_CONNECTTIMEOUT" => 4,
            "CURLOPT_FOLLOWLOCATION" => 1,
            "CURLOPT_MAXREDIRS" => 5,
            "CURLOPT_RANGE" => "0-" . (self::IMAGE_MAX_BYTES - 1),
        ]);
        $info = $curl->get_info();

        if ($curl->get_errno() || $data === false || $info["http_code"] ?? 0 >= 400 || strlen($data) > self::IMAGE_MAX_BYTES) {
            return null;
        }

        $mime = strtolower(trim(explode(";", (string) ($info["content_type"] ?? ""))[0]));
        $allowed = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/gif" => "gif",
            "image/webp" => "webp",
        ];
        if (!isset($allowed[$mime])) {
            return null;
        }
        if (function_exists("getimagesizefromstring") && @getimagesizefromstring($data) === false) {
            return null;
        }

        return [
            "content" => $data,
            "mimetype" => $mime,
            "extension" => $allowed[$mime],
        ];
    }

    /**
     * Method parse_html.
     *
     * @param string $html Parameter html.
     * @param string $baseurl Parameter baseurl.
     * @return array Return value.
     */
    public function parse_html(string $html, string $baseurl): array {
        $metadata = $this->fallback_metadata($baseurl);
        if (trim($html) === "") {
            return $metadata;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $metadata;
        }

        $xpath = new \DOMXPath($document);
        $values = [];
        foreach ($xpath->query("//meta[@content]") as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $key = strtolower(trim($node->getAttribute("property")));
            if ($key === "") {
                $key = strtolower(trim($node->getAttribute("name")));
            }
            if ($key === "") {
                $key = strtolower(trim($node->getAttribute("itemprop")));
            }
            $content = trim($node->getAttribute("content"));
            if ($key !== "" && $content !== "" && !isset($values[$key])) {
                $values[$key] = $content;
            }
        }

        $title = $values["og:title"] ?? $values["twitter:title"] ?? "";
        if ($title === "") {
            $titlenodes = $document->getElementsByTagName("title");
            if ($titlenodes->length > 0) {
                $title = trim($titlenodes->item(0)->textContent);
            }
        }

        $description = $values["og:description"] ?? $values["twitter:description"] ?? $values["description"] ?? "";
        $image = $values["og:image:secure_url"] ??
            $values["og:image"] ??
            $values["twitter:image"] ??
            $values["twitter:image:src"] ??
            $values["image"] ?? "";

        if ($image === "") {
            foreach ($xpath->query("//link[@href]") as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $rel = strtolower(trim($node->getAttribute("rel")));
                if (in_array("image_src", preg_split("/\s+/", $rel), true)) {
                    $image = trim($node->getAttribute("href"));
                    break;
                }
            }
        }

        if ($image !== "") {
            $image = $this->resolve_url($baseurl, html_entity_decode($image, ENT_QUOTES | ENT_HTML5, "UTF-8"));
        }

        $metadata["title"] = $this->clean_text($title, 255) ?: $metadata["title"];
        $metadata["description"] = $this->clean_text($description, 1000);
        $metadata["imageurl"] = $this->is_valid_url($image) ? $image : "";
        return $metadata;
    }

    /**
     * Method fallback_metadata.
     *
     * @param string $url Parameter url.
     * @return array Return value.
     */
    private function fallback_metadata(string $url): array {
        $parts = parse_url($url);
        $domain = strtolower((string) ($parts["host"] ?? ""));
        $path = trim((string) ($parts["path"] ?? ""), "/");
        $title = $path !== "" ? urldecode(basename($path)) : $domain;
        if ($title === "") {
            $title = $url;
        }

        return [
            "title" => $this->clean_text($title, 255),
            "description" => "",
            "domain" => $domain,
            "imageurl" => "",
        ];
    }

    /**
     * Method clean_text.
     *
     * @param string $value Parameter value.
     * @param int $maxlength Parameter maxlength.
     * @return string Return value.
     */
    private function clean_text(string $value, int $maxlength): string {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, "UTF-8");
        $value = preg_replace("/\\s+/u", " ", trim($value));
        return \core_text::substr((string) $value, 0, $maxlength);
    }

    /**
     * Method is_valid_url.
     *
     * @param string $url Parameter url.
     * @return bool Return value.
     */
    private function is_valid_url(string $url): bool {
        if ($url === "" || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ["http", "https"], true);
    }

    /**
     * Method resolve_url.
     *
     * @param string $baseurl Parameter baseurl.
     * @param string $candidate Parameter candidate.
     * @return string Return value.
     */
    private function resolve_url(string $baseurl, string $candidate): string {
        $candidate = trim($candidate);
        if ($candidate === "") {
            return "";
        }
        if (str_starts_with($candidate, "//")) {
            $scheme = parse_url($baseurl, PHP_URL_SCHEME) ?: "https";
            return $scheme . ":" . $candidate;
        }
        if ($this->is_valid_url($candidate)) {
            return $candidate;
        }

        $base = parse_url($baseurl);
        if (empty($base["scheme"]) || empty($base["host"])) {
            return "";
        }
        $origin = $base["scheme"] . "://" . $base["host"] . (isset($base["port"]) ? ":" . $base["port"] : "");
        if (str_starts_with($candidate, "/")) {
            return $origin . $this->normalise_path($candidate);
        }

        $basepath = (string) ($base["path"] ?? "/");
        $directory = rtrim(str_replace("\\", "/", dirname($basepath)), "/");
        return $origin . $this->normalise_path(($directory === "" ? "" : $directory) . "/" . $candidate);
    }

    /**
     * Method normalise_path.
     *
     * @param string $path Parameter path.
     * @return string Return value.
     */
    private function normalise_path(string $path): string {
        $query = "";
        if (str_contains($path, "?")) {
            [$path, $querypart] = explode("?", $path, 2);
            $query = "?" . $querypart;
        }
        $segments = [];
        foreach (explode("/", $path) as $segment) {
            if ($segment === "" || $segment === ".") {
                continue;
            }
            if ($segment === "..") {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        return "/" . implode("/", $segments) . $query;
    }
}
