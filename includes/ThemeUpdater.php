<?php

namespace WP_Forge\WPUpdateHandler;

/**
 * Class ThemeUpdater.
 */
class ThemeUpdater {

	/**
	 * Data mapping for API response.
	 *
	 * Expected fields:
	 *  - download_link
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
	 * Theme instance.
	 *
	 * @var \WP_Theme
	 */
	protected $theme;

	/**
	 * Theme update API URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * ThemeUpdater constructor.
	 *
	 * @param \WP_Theme $theme The theme instance.
	 * @param string    $url   The theme update API URL.
	 */
	public function __construct( $theme, $url ) {
		$this->setTheme( $theme )->setUrl( $url )->registerHooks();
	}

	/**
	 * Set up theme instance.
	 *
	 * @param \WP_Theme $theme The theme instance.
	 *
	 * @return $this
	 */
	public function setTheme( \WP_Theme $theme ) {
		$this->theme = $theme;

		return $this;
	}

	/**
	 * Set the theme update API URL.
	 *
	 * @param string $url The theme update API URL.
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

		return isset( $release['version'] ) && version_compare( $release['version'], $this->theme->get( 'Version' ), '>' );
	}

	/**
	 * Fetch details on the latest theme release.
	 *
	 * @return array The latest release data.
	 */
	public function getRelease() {
		$cache_key = 'wp_theme_update_' . $this->theme->get_stylesheet();
		delete_transient( $cache_key );
		$payload = get_transient( $cache_key );
		if ( ! $payload ) {
			$payload  = array();
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
	 * @return array
	 */
	public function mapData( array $data ) {

		$author_name = $this->theme->get( 'Author' );
		$author_uri  = $this->theme->get( 'AuthorURI' );

		$author = ! empty( $author_uri ) ? "<a href=\"{$author_uri}\">{$author_name}</a>" : $author_name;

		$description = data_get( $data, 'description', $this->theme->get( 'Description' ) );

		$defaults = array(
			'author'        => $author,
			'author_name'   => $author_name,
			'author_uri'    => $author_uri,
			'description'   => $description,
			'download_link' => data_get( $data, 'download_link' ),
			'homepage'      => data_get( $data, 'homepage', $this->theme->get( 'ThemeURI' ) ),
			'name'          => $this->theme->get( 'Name' ),
			'theme'         => $this->theme->get_stylesheet(),
			'requires'      => data_get( $data, 'requires', $this->theme->get( 'RequiresWP' ) ),
			'requires_php'  => data_get( $data, 'requires_php', $this->theme->get( 'RequiresPHP' ) ),
			'slug'          => $this->theme->get_stylesheet(),
			'tested'        => data_get( $data, 'tested' ),
			'version'       => data_get( $data, 'version' ),
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

		return $payload;
	}

	/**
	 * Register hooks.
	 */
	protected function registerHooks() {

		add_filter(
			'site_transient_update_themes',
			function ( $transient ) {

				if ( empty( $transient ) || ! is_object( $transient ) ) {
					return $transient;
				}

				$release = $this->getRelease();

				if ( $this->hasUpdate() ) {
					$transient->response[ $this->theme->get_stylesheet() ] = $release;
				} else {
					$transient->no_update[ $this->theme->get_stylesheet() ] = $release;
				}

				return $transient;
			}
		);

	}

}
