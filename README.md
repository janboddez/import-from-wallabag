# Import From Wallabag
Automatically import [wallabag](https://wallabag.org/en) entries into WordPress. For those not in the know, wallabag is a free, self-hosted read-it-later app.

## Configuration
First, create a [wallabag API client](https://doc.wallabag.org/en/developer/api/oauth.html). Then head over to WP Admin's Settings > Import From Wallabag and fill out your client ID and secret, and your wallabag username and password, too.

Alternatively, add your username and password to your WordPress install's `config.php`:
```
define( 'IMPORT_FROM_WALLABAG_USER', '<your-wallabag-username>' );
define( 'IMPORT_FROM_WALLABAG_PASS', '<your-wallabag-password>' );
```

Other options include: target Post Status, Post Type and Post Format, and an optional Tags setting. (Only items that match all of these tags will be imported.)

## Usage
Import From Wallabag will automatically run twice a day and import the most recent 30 entries that match the provided tags and were added after it was run last.

## Filter Hooks
Use `import_from_wallabag_api_args` to filter the [API arguments](https://app.wallabag.it/api/doc#get--api-entries.{_format}):
```
add_filter( 'import_from_wallabag_api_args', function( $args ) {
  $args['perPage'] = 60;   // When 30 is not enough.
  unset( $args['since'] ); // Grab all last 60 items, regardless of when they were created.
  return $args;
}, 10, 2 );
```
The formatting of imported posts can be completely customized using `import_from_wallabag_post_args`:
```
add_filter( 'import_from_wallabag_post_args', function( $args, $entry ) {
  // Modify, e.g., `$args['post_content']`.
  return $args;
}, 10, 2 );
```
