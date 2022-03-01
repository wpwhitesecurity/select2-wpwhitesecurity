# select2-for-wordpress

This library simplifies adding autocomplete form fields based on select2 library to WordPress plugins and themes.

## Installation

Library can be installed using composer.

First you need to add our repository to the list of recognized repositories in `composer.json`:
```
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/wpwhitesecurity/select2-wpwhitesecurity"
    }
]
```

Next add the library to your dependencies by running:
```
composer require wpwhitesecurity/select2-wpwhitesecurity
```

## Usage

First you need to make sure the library is loaded. Including the composer autoloader is sufficient:
```
// Require Composer autoloader if it exists.
if ( file_exists( YOUR_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
    require_once YOUR_PLUGIN_PATH . '/vendor/autoload.php';
}
```

Next step is initialising the library with a URL to the library folder. It will be used to load required JavaScript files and CSS stylesheets on the front-end.
```
if ( class_exists( '\S24WP' ) ) {
    \S24WP::init( YOUR_PLUGIN_BASE_URL . 'vendor/wpwhitesecurity/select2-wpwhitesecurity' );
}
```

The autocomplete input itself is rendering using function `\S24WP::insert()`. It accepts a single attribute, and array of configuration parameters:

- `name` (`string`) - Name of the select field. Needs to end with `[]` when multiple values are allowed.
- `id` (`string`) - ID of the select field. Optional, defaults to `wpw-select2-{number}`.
- `data` (`array`) - Data to populate the select field with. Each item needs to be an associative array with fields `id` and `text`. See [Select2 data format](https://select2.org/data-sources/formats) documentation.
- `data-type` (`string`) - This attribute can be used instead of `data`. Supported values are `user`, `role` or `post`. The items are either pre-loaded locally (for example roles) or fetched from a remote (AJAX) source (users and posts).
- `placeholder` (`string`) - Placeholder text.
- `width` (`int`) - Width of the element in pixels.
- `multiple` (`bool`) - If true, multiple values can be selected.
- `min_chars` (`int`) - Minimum number of characters to type before searching. Only applies to 
- `selected` (`string[]|int[]`) - Selected value or an array of values. Doesn't support comma separated list.
- `extra_js_callback` (`callable`) - PHP function to call after the JavaScript select2 init function is printed. It should print additional JS if necessary, using `s2` as reference to the current select2 instance.
- `remote_source_threshold` (`int`) - Only use when `data-type` is set to `user`. Users are loaded using AJAX only if number of users exceeds given threshold. Otherwise the users are pre-loaded locally.

### Example

```
\S24WP::insert(
    array(
        'placeholder'             => esc_html__( 'select user(s)', 'admin-notices-manager' ),
        'name'                    => 'visibility["show-users"][]',
        'width'                   => 500,
        'data-type'               => 'user',
        'multiple'                => true,
        'extra_js_callback'       => function ( $element_id ) {
            echo 'window.anm_settings.append_select2_events( s2 );';
        },
        'remote_source_threshold' => 300,
    )
);
```