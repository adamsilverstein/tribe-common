<?php

namespace TEC\Common\Site_Health;

use stdClass;
use TEC\Common\Site_Health\Fields\Generic_Info_Field;

/**
 * Class Abstract_Info_Section
 *
 * @link    https://developer.wordpress.org/reference/hooks/debug_information/
 *
 * @since   TBD
 *
 * @package TEC\Common\Site_Health
 */
abstract class Info_Section_Abstract implements Info_Section_Interface {
	/**
	 * Slug for the section.
	 *
	 * @since TBD
	 *
	 * @var string $slug
	 */
	protected static string $slug;

	/**
	 * Label for the section.
	 *
	 * @since TBD
	 *
	 * @var string $label
	 */
	protected string $label;

	/**
	 * If we should show the count of fields in the site health info page.
	 *
	 * @since TBD
	 *
	 * @var bool $show_count
	 */
	protected bool $show_count = true;

	/**
	 * If this section is private.
	 *
	 * @since TBD
	 *
	 * @var bool $is_private
	 */
	protected bool $is_private = false;

	/**
	 * Description for the section.
	 *
	 * @since TBD
	 *
	 * @var string $description
	 */
	protected string $description;

	/**
	 * Which fields are stored on this section.
	 *
	 * @since TBD
	 *
	 * @var array<string, Info_Field_Abstract> $fields
	 */
	protected array $fields = [];

	/**
	 * @inheritDoc
	 */
	public static function get_slug(): string {
		return static::$slug;
	}

	/**
	 * @inheritDoc
	 */
	public function to_array(): array {
		$fields = [];
		foreach ( $this->get_fields() as $key => $field ) {
			$fields[ $key ] = $field->to_array();
		}

		return [
			'label'       => $this->filter_param( 'label', $this->get_label() ),
			'description' => $this->filter_param( 'description', $this->get_description() ),
			'private'     => $this->filter_param( 'private', $this->is_private() ),
			'show_count'  => $this->filter_param( 'show_count', $this->get_show_count() ),
			'fields'      => $this->filter_param( 'fields', $fields ),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * @inheritDoc
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * @inheritDoc
	 */
	public function get_fields(): array {
		return $this->fields;
	}

	/**
	 * @inheritDoc
	 */
	public function get_show_count(): bool {
		return $this->show_count;
	}

	/**
	 * @inheritDoc
	 */
	public function is_private(): bool {
		return $this->is_private;
	}

	/**
	 * @inheritDoc
	 */
	public function has_field( $field ): bool {
		// Assume field is a key first.
		$id = $field;

		// When it's a field use its ID.
		if ( $field instanceof Info_Field_Abstract ) {
			$id = $field->get_id();
		}

		// Keys can only be strings.
		if ( ! is_string( $id ) ) {
			return false;
		}

		$fields = $this->get_fields();

		return isset( $fields[ $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function get_field( string $id ): ?Info_Field_Abstract {
		if ( ! $this->has_field( $id ) ) {
			return null;
		}

		return $this->get_fields()[ $id ];
	}

	/**
	 * @inheritDoc
	 */
	public function add_field( Info_Field_Abstract $field, bool $overwrite = false ): bool {
		// Allow the adding of a field to overwrite existing fields.
		if ( ! $overwrite && $this->has_field( $field ) ) {
			return false;
		}

		$this->fields[ $field->get_id() ] = $field;

		$this->sort_fields();

		return true;
	}

	/**
	 * Internal method to the Section class, to allow filtering of specific values.
	 *
	 * @since TBD
	 *
	 * @param string $param
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	protected function filter_param( string $param, $value = null ) {
		$section_slug = static::get_slug();
		/**
		 * Filters the get of a particular param for all sections.
		 *
		 * @since TBD
		 *
		 * @param mixed                 $value   Value of the field.
		 * @param Info_Section_Abstract $section Current Section.
		 */
		$value = apply_filters( "tec_debug_info_section_get_{$param}", $value, $this );

		/**
		 * Filters the get of a particular param for a specific section.
		 *
		 * @since TBD
		 *
		 * @param mixed                 $value   Value of the field.
		 * @param Info_Section_Abstract $section Current Section.
		 */
		return apply_filters( "tec_debug_info_section_{$section_slug}_get_{$param}", $value, $this );
	}

	/**
	 * Sorts the fields stored on this section, will retain keys.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function sort_fields(): void {
		uasort( $this->fields, static function ( $field_a, $field_b ) {
			$a = $field_a->get_priority();
			$b = $field_b->get_priority();

			if ( $a === $b ) {
				return 0;
			}

			return ( $a < $b ) ? - 1 : 1;
		} );
	}

	/**
	 * Converts a post status count object to an array in the format
	 *            [ (string) status_slug => (int) count]
	 *
	 * @since TBD
	 *
	 * @param stdClass $obj The object returned from wp_count_posts().
	 *
	 * @return array<string,int> An array of stati (key) with counts (value).
	 */
	public function clean_post_status_counts( string $post_type ): array {
		$obj = wp_count_posts( $post_type );
		$stati = [
			'publish',
			'future',
			'draft',
			'pending',
		];

		/**
		 * Allows other plugins to add/remove stati to track.
		 *
		 * @param array<string|bool> $stati An array of stati to track.
		 */
		apply_filters( 'tec_site_heath_event_stati', $stati );

		$keys = array_keys( $obj );
		foreach( $keys as $key ) {
			if ( ! in_array( $key, $stati ) ) {
				unset( $obj[ $key ] );
			}
		}

		return $obj;
	}
}
