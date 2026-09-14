# mod_linkcollection

A simple Moodle activity for organising several external references inside one course activity.

Teachers can create sections such as "Weekly material" and add multiple links. When a link is saved, the plugin can fetch the remote page title, description and social image metadata. The image is cached in Moodle file storage and the student view renders the links as a compact search-results-style list with the thumbnail on the left.

## Main features

- Multiple ordered sections inside one activity.
- Multiple ordered links in each section.
- Automatic metadata extraction from `title`, `description`, Open Graph and Twitter card tags.
- Cached thumbnails in Moodle file storage.
- Manual title and description overrides.
- One-click metadata refresh for teachers.
- Moodle cURL security checks are respected for the page and image URLs.
- Activity completion by view.
- Backup and restore support.
- No renderer class; the student view uses a Mustache template.

## Compatibility

Moodle 4.5 or newer.

## Installation

Copy the `linkcollection` directory to `mod/linkcollection` and complete the Moodle upgrade process.
