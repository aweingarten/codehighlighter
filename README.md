# Code Highlighter

- [Introduction](#introduction)
- [Installation](#installation)
- [Usage](#usage)
- [Plugins](#plugins)
- [Architecture](#architecture)
## Introduction

Code Highlighter provides a facility for creating and processing arbitrary
BBCode-like tags. It was modeled after the [WordPress Shortcode API]
(http://codex.wordpress.org/Shortcode_API), from which much of its parsing code
was adapted. (See [shortcodes.php]
(https://github.com/WordPress/WordPress/blob/4.0/wp-includes/shortcodes.php).)

## Installation

Shortcode is installed in the usual way. See [Installing modules (Drupal 7)]
(https://drupal.org/documentation/install/modules-themes/modules-7).

## Usage

To define a new shortcode, you must implement `hook_shortcode_info()` and
`callback_shortcode_process()`. See shortcode.api.php for details. To
parse a shortcode, or expand the shortcodes in a given string of text, use
`shortcode_process_shortcodes()`, e.g.,

```php
$input = 'Some text with a [shortcode foo="bar"/].';
$output = shortcode_process_shortcodes($input);
```

To shortcode-enable a content format, use the included Shortcode Filter module.


Use short-code syntax to provide an abstraction layer
Filter interperits it and extracts the common values
passes it to plugin which expands it.