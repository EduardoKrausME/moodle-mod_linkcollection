# mod_linkcollection

Link Collection is a Moodle activity for organising several external references inside a single course activity.

## How it works

Teachers create ordered sections such as “Weekly material” and add multiple links to each section. When a link is saved,
the plugin can fetch the remote page title, description and social-image metadata, cache the thumbnail in Moodle file
storage and render the student view as a compact search-results-style list.

## Features

- multiple ordered sections inside one activity;
- multiple ordered links in each section;
- metadata extraction from title, description, Open Graph and Twitter Card tags;
- cached thumbnails in Moodle file storage;
- manual title and description overrides;
- one-click metadata refresh for teachers;
- Moodle cURL security checks for page and image URLs;
- activity completion by view;
- backup and restore of the activity.

The student interface is rendered with a Mustache template.
