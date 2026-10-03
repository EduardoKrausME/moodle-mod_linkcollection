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
 * metadata_fetcher_test.php
 *
 * @package   mod_linkcollection
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_linkcollection;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class metadata_fetcher_test.
 *
 * @covers \mod_linkcollection\metadata_fetcher
 */
#[CoversClass(metadata_fetcher::class)]
final class metadata_fetcher_test extends advanced_testcase {
    /**
     * Method test_parse_open_graph_metadata.
     *
     * @return void Return value.
     * @covers \\mod_linkcollection\\metadata_fetcher::parse_html
     */
    public function test_parse_open_graph_metadata(): void {
        $fetcher = new metadata_fetcher();
        $html = '<html><head>'
            . '<title>Fallback title</title>'
            . '<meta property="og:title" content="Example title">'
            . '<meta property="og:description" content="Example description">'
            . '<meta property="og:image" content="/images/card.jpg">'
            . '</head><body></body></html>';

        $result = $fetcher->parse_html($html, "https://example.com/path/page.html");

        $this->assertSame("Example title", $result["title"]);
        $this->assertSame("Example description", $result["description"]);
        $this->assertSame("example.com", $result["domain"]);
        $this->assertSame("https://example.com/images/card.jpg", $result["imageurl"]);
    }

    /**
     * Method test_parse_title_and_description_fallbacks.
     *
     * @return void Return value.
     * @covers \\mod_linkcollection\\metadata_fetcher::parse_html
     */
    public function test_parse_title_and_description_fallbacks(): void {
        $fetcher = new metadata_fetcher();
        $html = '<html><head>'
            . '<title>Simple page</title>'
            . '<meta name="description" content="Simple description">'
            . '</head><body></body></html>';

        $result = $fetcher->parse_html($html, "https://docs.example.org/guide/start");

        $this->assertSame("Simple page", $result["title"]);
        $this->assertSame("Simple description", $result["description"]);
        $this->assertSame("docs.example.org", $result["domain"]);
        $this->assertSame("", $result["imageurl"]);
    }

    /**
     * Ensure a successful HTTP image response is accepted.
     *
     * curl::mock_response() sets the mocked HTTP status to 200 but does not provide a Content-Type,
     * so this also verifies the byte-based MIME fallback.
     *
     * @return void
     * @covers \\mod_linkcollection\\metadata_fetcher::fetch_image
     */
    public function test_fetch_image_accepts_successful_mocked_response(): void {
        global $CFG;

        require_once($CFG->libdir . "/filelib.php");

        $image = base64_decode(
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="
        );
        \curl::mock_response($image);

        $result = (new metadata_fetcher())->fetch_image("https://example.com/image.png");

        $this->assertNotNull($result);
        $this->assertSame($image, $result["content"]);
        $this->assertSame("image/png", $result["mimetype"]);
        $this->assertSame("png", $result["extension"]);
    }

}
