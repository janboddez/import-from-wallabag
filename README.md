# Import From Wallabag
Automatically import wallabag entries into WordPress.

## Configuration
First, create a [wallabag API client](https://doc.wallabag.org/en/developer/api/oauth.html). Then head over to WP Admin's Settings > Import From Wallabag and fill out your client ID and secret, and your username and password, too.

Other options include: target Post Status, Post Type and Post Format, and an optional Tags setting. (Only items that match all of these tags will be imported.)

## Usage
Import From Wallabag will run twice a day and import the most recent 30 entries that match the provided tags.
