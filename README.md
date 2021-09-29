# WordPress Update Handler

A WordPress package for updating custom plugins and themes based on an JSON REST API response from a custom update
server.

Check out the [WordPress GitHub Release API](https://github.com/wp-forge/worker-wp-github-release-api) repository to
learn how to quickly launch a custom update server that fetches releases from GitHub using Cloudflare Workers.

## Plugins

This package expects your custom plugin info API to respond with the same shape as
the [WordPress plugin info API](https://codex.wordpress.org/WordPress.org_API#Plugins). However, if your API response
has a different shape, you can map fields to those returned by your API.

### Usage

Basic example:

```php
<?php
/**
 * Plugin Name: My Plugin
 */

require __DIR__ . '/vendor/autoload.php';

use WP_Forge\WPUpdateHandler\PluginUpdater;

$url = 'https://my-update-api.com/plugins/plugin-name'; // Custom API GET endpoint

new PluginUpdater( __FILE__, $url );

```

Advanced example with data mapping and data overrides:

```php
<?php
/**
 * Plugin Name: My Plugin
 */

require __DIR__ . '/vendor/autoload.php';

use WP_Forge\WPUpdateHandler\PluginUpdater;

$file = __FILE__; // Can be absolute path to main plugin file, or the plugin basename.
$url = 'https://my-update-api.com/plugins/plugin-name'; // Custom API GET endpoint

$pluginUpdater = new PluginUpdater( $file, $url );

/*
 * Keys are the fields that WordPress is expecting (look at the WP Plugin Info API response).
 * Values are the keys returned by your custom API.
 * 
 * Use dot notation to map nested keys.
 */
$pluginUpdater->setDataMap(
  [
    'requires' => 'requires.wp',          
    'requires' => 'requires.php',
    'banners.2x' => 'banners.retina',          
  ]
);

/*
 *  Explicitly set specific values that will be provided to WordPress.
 */
$pluginUpdater->setDataOverrides(
  [
    'banners' => [
      '2x' => 'https://my.cdn.com/banner-123-retina.jpg',
      '1x' => 'https://my.cdn.com/banner-123.jpg',
    ],
    'icons' => [
      '2x' => 'https://my.cdn.com/icon-123-retina.jpg',
      '1x' => 'https://my.cdn.com/icon-123.jpg',
    ],        
  ]
);

```

## Themes

This package expects your custom theme info API to respond with the same shape as
the [WordPress theme info API](https://codex.wordpress.org/WordPress.org_API#Themes). However, if your API response has
a different shape, you can map fields to those returned by your API.

### Usage

Basic example:

```php
<?php
/**
 * Theme Name: My Theme
 */

require __DIR__ . '/vendor/autoload.php';

use WP_Forge\WPUpdateHandler\ThemeUpdater;

$url = 'https://my-update-api.com/theme/theme-name'; // Custom API GET endpoint

new ThemeUpdater( wp_get_theme('my-theme'), $url );

```

Advanced example with data mapping and data overrides:

```php
<?php
/**
 * Theme Name: My Theme
 */

require __DIR__ . '/vendor/autoload.php';

use WP_Forge\WPUpdateHandler\ThemeUpdater;

$theme = wp_get_theme('my-theme'); // Get the theme's WP_Theme instance.
$url = 'https://my-update-api.com/themes/theme-name'; // Custom API GET endpoint

$themeUpdater = new ThemeUpdater( $file, $url );

/*
 * Keys are the fields that WordPress is expecting (look at the WP Theme Info API response).
 * Values are the keys returned by your custom API.
 * 
 * Use dot notation to map nested keys.
 */
$themeUpdater->setDataMap(
  [
    'requires' => 'requires.wp',          
    'requires' => 'requires.php',
    'banners.2x' => 'banners.retina',          
  ]
);

/*
 *  Explicitly set specific values that will be provided to WordPress.
 */
$themeUpdater->setDataOverrides(
  [
    'banners' => [
      '2x' => 'https://my.cdn.com/banner-123-retina.jpg',
      '1x' => 'https://my.cdn.com/banner-123.jpg',
    ],
    'icons' => [
      '2x' => 'https://my.cdn.com/icon-123-retina.jpg',
      '1x' => 'https://my.cdn.com/icon-123.jpg',
    ],        
  ]
);

```
