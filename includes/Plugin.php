<?php

namespace WP_Forge\WPUpdateHandler;

/**
 * Class Plugin
 *
 * Get information about a plugin.
 *
 * @method author
 * @method author_name
 * @method author_uri
 * @method description
 * @method domain_path
 * @method license
 * @method license_uri
 * @method name
 * @method network
 * @method requires_wp
 * @method requires_php
 * @method text_domain
 * @method title
 * @method uri
 * @method version
 */
class Plugin implements \ArrayAccess {

	/**
	 * A collection of valid WordPress plugin file headers.
	 *
	 * @var array
	 */
	const HEADERS = array(
		'author'       => 'Author',
		'author_name'  => 'AuthorName',
		'author_uri'   => 'AuthorURI',
		'description'  => 'Description',
		'domain_path'  => 'DomainPath',
		'license'      => 'License',
		'license_uri'  => 'LicenseURI',
		'name'         => 'Name',
		'network'      => 'Network',
		'requires_wp'  => 'RequiresWP',
		'requires_php' => 'RequiresPHP',
		'text_domain'  => 'TextDomain',
		'title'        => 'Title',
		'uri'          => 'PluginURI',
		'version'      => 'Version',
	);

	/**
	 * The absolute path to the plugin file.
	 *
	 * @var string
	 */
	protected $file;

	/**
	 * Plugin file headers.
	 *
	 * @var array
	 */
	protected $file_headers;

	/**
	 * Constructor.
	 *
	 * @param string $file The plugin basename, or absolute path to the plugin file.
	 */
	public function __construct( $file ) {

		// If plugin basename is provided, convert to full file path
		if ( 0 !== strpos( $file, '/' ) ) {
			$file = WP_PLUGIN_DIR . '/' . $file;
		}

		$this->file = $file;
	}

	/**
	 * Get the plugin basename.
	 *
	 * @return string
	 */
	public function basename() {
		return plugin_basename( $this->file );
	}

	/**
	 * Get the absolute path to the plugin file.
	 *
	 * @return string
	 */
	public function file() {
		return $this->file;
	}

	/**
	 * Get the plugin slug.
	 *
	 * @return string
	 */
	public function slug() {
		return basename( plugin_dir_path( $this->file ) );
	}

	/**
	 * Get a specific plugin file header.
	 *
	 * @param string $name The plugin file header name.
	 *
	 * @return string
	 */
	protected function get_file_header( $name ) {
		$file_headers = $this->get_file_headers();

		return (string) isset( $file_headers[ $name ] ) ? $file_headers[ $name ] : '';
	}

	/**
	 * Get all plugin file headers.
	 *
	 * @return array
	 */
	protected function get_file_headers() {

		if ( isset( $this->file_headers ) ) {
			return $this->file_headers;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require wp_normalize_path( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		$this->file_headers = get_plugin_data( $this->file );

		return $this->file_headers;
	}

	/**
	 * Magic method for fetching data from plugin file headers.
	 *
	 * @param string $name The method name.
	 * @param array  $args The method parameters.
	 *
	 * @return string
	 */
	public function __call( $name, $args ) {
		$value = '';
		if ( $this->offsetExists( $name ) ) {
			$value = $this->offsetGet( $name );
		}

		return $value;
	}

	/**
	 * Check if array offset exists.
	 *
	 * @param string $offset Array key
	 *
	 * @return bool
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ) {
		return array_key_exists( $offset, self::HEADERS ) || in_array( $offset, array( 'basename', 'file', 'slug' ), true );
	}

	/**
	 * Get array offset.
	 *
	 * @param string $offset Array key
	 *
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		if ( method_exists( $this, $offset ) ) {
			return $this->{$offset}();
		}
		if ( array_key_exists( $offset, self::HEADERS ) ) {
			return $this->get_file_header( self::HEADERS[ $offset ] );
		}

		return null;
	}

	/**
	 * Set array value.
	 *
	 * @param string $offset Array key
	 * @param mixed  $value Value to set
	 *
	 * @throws \Exception If called.
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {
		throw new \Exception( 'Setting plugin values is not allowed!' );
	}

	/**
	 * Unset array value.
	 *
	 * @param string $offset Array key
	 *
	 * @throws \Exception If called.
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ) {
		throw new \Exception( 'Unsetting plugin values is not allowed!' );
	}

	/**
	 * Convert class instance into an array.
	 *
	 * @return array
	 */
	public function toArray() {
		$keys = array_merge( array_keys( self::HEADERS ), array( 'basename', 'slug' ) );
		asort( $keys );

		$values = array();
		foreach ( $keys as $key ) {
			$values[ $key ] = $this->offsetGet( $key );
		}

		return $values;
	}

}
