<?php

namespace WP_Forge\WPUpdateHandler;

/**
 * Class PluginUpdater.
 */
class PluginUpdater {

	/**
	 * Data mapping for API response.
	 *
	 * Expected fields:
	 *  - download_link
	 *  - last_updated
	 *  - requires
	 *  - requires_php
	 *  - tested
	 *  - version
	 *
	 * @var array
	 */
	protected $dataMap = array();

	/**
	 * Data to explicitly set.
	 *
	 * @var array
	 */
	protected $dataOverrides = array();

	/**
	 * Duration in seconds until cache expires.
	 *
	 * @var int
	 */
	protected $cacheExpiration = HOUR_IN_SECONDS * 6;

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Plugin update API URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * PluginUpdater constructor.
	 *
	 * @param string $file The plugin basename or absolute path to main plugin file.
	 * @param string $url  The plugin update API URL.
	 */
	public function __construct( $file, $url ) {
		$this->setPlugin( $file )->setUrl( $url )->registerHooks();
	}

	/**
	 * Set up plugin instance.
	 *
	 * @param string $file The plugin basename or absolute path to main plugin file.
	 *
	 * @return $this
	 */
	public function setPlugin( $file ) {
		$this->plugin = new Plugin( $file );

		return $this;
	}

	/**
	 * Set the plugin update API URL.
	 *
	 * @param string $url The plugin update API URL.
	 *
	 * @return $this
	 */
	public function setUrl( $url ) {
		$this->url = $url;

		return $this;
	}

	/**
	 * Set the cache expiration.
	 *
	 * @param int $expiration Duration in seconds until cache expires.
	 *
	 * @return $this
	 */
	public function setCacheExpiration( $expiration ) {
		$this->cacheExpiration = absint( $expiration );

		return $this;
	}

	/**
	 * Set data map.
	 *
	 * @param array $map A mapping of API response fields to the expected WP fields.
	 *
	 * @return $this
	 */
	public function setDataMap( array $map ) {
		$this->dataMap = $map;

		return $this;
	}

	/**
	 * Set data overrides.
	 *
	 * @param array $overrides A key-value store of fields to replace with specific values.
	 *
	 * @return $this
	 */
	public function setDataOverrides( array $overrides ) {
		$this->dataOverrides = $overrides;

		return $this;
	}

	/**
	 * Check if an update is available.
	 *
	 * @return bool
	 */
	public function hasUpdate() {
		$release = $this->getRelease();

		return isset( $release->version ) && version_compare( $release->version, $this->plugin->version(), '>' );
	}

	/**
	 * Fetch details on the latest plugin release.
	 *
	 * @return \stdClass The latest release data.
	 */
	public function getRelease() {
		$cache_key = 'wp_plugin_update_' . $this->plugin->slug();
		$payload   = get_transient( $cache_key );
		if ( ! $payload ) {
			$payload  = new \stdClass();
			$response = wp_remote_get( $this->url );

			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
				if ( $body ) {
					$data = json_decode( $body, true );
					if ( ! is_null( $data ) ) {
						$payload = $this->mapData( $data );
						set_transient( $cache_key, $payload, HOUR_IN_SECONDS * 6 );
					}
				}
			}
		}

		return $payload;
	}

	/**
	 * Normalize data from the API to the expected values.
	 *
	 * @param array $data A mapping of the local key to the remote key using dot notation.
	 *
	 * @return \stdClass
	 */
	public function mapData( array $data ) {

		$author_name = $this->plugin->author_name();
		$author_uri  = $this->plugin->author_uri();

		$author = ! empty( $author_uri ) ? "<a href=\"{$author_uri}\">{$author_name}</a>" : $author_name;

		$description = data_get( $data, 'description', $this->plugin->description() );

		$defaults = array(
			'author'            => $author,
			'author_name'       => $author_name,
			'author_uri'        => $author_uri,
			'description'       => $description,
			'download_link'     => data_get( $data, 'download_link' ),
			'homepage'          => data_get( $data, 'homepage', $this->plugin->uri() ),
			'id'                => $this->plugin->basename(),
			'last_updated'      => data_get( $data, 'last_updated' ),
			'name'              => $this->plugin->name(),
			'plugin'            => $this->plugin->basename(),
			'requires'          => data_get( $data, 'requires', $this->plugin->requires_wp() ),
			'requires_php'      => data_get( $data, 'requires_php', $this->plugin->requires_php() ),
			'sections'          => array(
				'description' => $description,
			),
			'short_description' => $description,
			'slug'              => $this->plugin->slug(),
			'tested'            => data_get( $data, 'tested' ),
			'version'           => data_get( $data, 'version' ),
		);

		$payload = $defaults;

		// Map selected fields
		foreach ( $this->dataMap as $key => $target ) {
			data_set( $payload, $key, data_get( $data, $target ) );
		}

		// Override selected fields
		foreach ( $this->dataOverrides as $key => $value ) {
			data_set( $payload, $key, $value );
		}

		$payload['new_version'] = $payload['version'];
		$payload['package']     = $payload['download_link'];
		$payload['url']         = $payload['homepage'];

		if ( isset( $payload['banners']['2x'] ) && ! isset( $payload['banners']['high'] ) ) {
			$payload['banners']['high'] = $payload['banners']['2x'];
		}

		if ( isset( $payload['banners']['1x'] ) && ! isset( $payload['banners']['low'] ) ) {
			$payload['banners']['low'] = $payload['banners']['1x'];
		}

		return (object) $payload;
	}

	/**
	 * Register hooks.
	 */
	protected function registerHooks() {

		add_action(
			'plugins_api',
			function ( $response, $action, $args ) {

				if ( isset( $args->slug ) && $args->slug === $this->plugin->slug() ) {
					return $this->getRelease();
				}

				return $response;
			},
			20,
			3
		);

		add_filter(
			'site_transient_update_plugins',
			function ( $transient ) {

				if ( empty( $transient ) || ! is_object( $transient ) ) {
					return $transient;
				}

				$release = $this->getRelease();

				if ( $this->hasUpdate() ) {
					$transient->response[ $this->plugin->basename() ] = $release;
				} else {
					$transient->no_update[ $this->plugin->basename() ] = $release;
				}

				return $transient;
			}
		);

	}

}
