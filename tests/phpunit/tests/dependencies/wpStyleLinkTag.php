<?php

/**
 * Test wp_get_style_link_tag() and wp_print_style_link_tag().
 *
 * @group dependencies
 * @group styles
 */
class Tests_Dependencies_wpStyleLinkTag extends WP_UnitTestCase {

	/**
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_escapes_uri_and_supports_boolean_attributes() {
		$expected = <<<'HTML'
<link
	rel="stylesheet"
	href="https://example.com/style.css?one=1&amp;two=2"
	integrity="sha384-example"
	crossorigin
/>

HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_style_link_tag(
				array(
					'rel'         => 'stylesheet',
					'href'        => 'https://example.com/style.css?one=1&two=2',
					'integrity'   => 'sha384-example',
					'crossorigin' => true,
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_attribute_value_types() {
		$expected = <<<'HTML'
<link
	enabled
	null-value
	empty-value=""
	zero-string="0"
/>

HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_style_link_tag(
				array(
					'enabled'     => true,
					'disabled'    => false,
					'null-value'  => null,
					'empty-value' => '',
					'zero-string' => '0',
				)
			)
		);
	}

	/**
	 * Case-insensitive duplicate attributes should keep the first value.
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_ignores_duplicate_attributes() {
		$this->assertEqualHTML(
			"<link test=\"first\" />\n",
			wp_get_style_link_tag(
				array(
					'test'     => 'first',
					'TEST'     => 'second',
					'disabled' => false,
					'DISABLED' => true,
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_applies_wp_style_attributes_filter() {
		$filter = static function ( $attributes ) {
			$attributes['nonce'] = 'filtered-nonce';
			return $attributes;
		};

		add_filter( 'wp_style_attributes', $filter );

		$tag = wp_get_style_link_tag( array( 'rel' => 'stylesheet' ) );

		remove_filter( 'wp_style_attributes', $filter );

		$this->assertSame(
			"<link rel=\"stylesheet\" nonce=\"filtered-nonce\" />\n",
			$tag
		);
	}

	/**
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_prints_attributes_in_the_order_given() {
		$this->assertSame(
			"<link rel=\"preload\" as=\"style\" id=\"test-css\" href=\"https://example.com/style.css?one=1&#038;two=2\" media=\"print\" crossorigin />\n",
			wp_get_style_link_tag(
				array(
					'rel'         => 'preload',
					'as'          => 'style',
					'id'          => 'test-css',
					'href'        => 'https://example.com/style.css?one=1&two=2',
					'media'       => 'print',
					'crossorigin' => true,
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_style_link_tag
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_get_style_link_tag_rejects_invalid_attribute_names() {
		$this->assertSame(
			"<link rel=\"stylesheet\" />\n",
			wp_get_style_link_tag(
				array(
					'rel'                => 'stylesheet',
					'onload="alert(1)"'  => true,
					'x><script>alert(1)' => 'y',
					"crossorigin\n"      => true,
					"onload\n"           => 'alert(1)',
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_keeps_the_order_of_unusual_attribute_names() {
		$this->assertSame(
			"<link rel=\"stylesheet\" @load=\"ready\" x-bind:media=\"query\" href=\"https://example.com/style.css\" />\n",
			wp_get_style_link_tag(
				array(
					'rel'          => 'stylesheet',
					'@load'        => 'ready',
					'x-bind:media' => 'query',
					'href'         => 'https://example.com/style.css',
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_get_style_link_tag_rejects_disallowed_href_protocols() {
		$tag       = wp_get_style_link_tag(
			array(
				'rel'  => 'stylesheet',
				'href' => 'javascript:alert(1)',
			)
		);
		$processor = new WP_HTML_Tag_Processor( $tag );

		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertNull( $processor->get_attribute( 'href' ), 'A rejected URL should not leave an empty href attribute.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: Closure, 1: string }>
	 */
	public function data_style_attributes_filter_changing_id(): array {
		return array(
			'changed'           => array(
				static function ( array $attributes ): array {
					$attributes['id'] = 'changed-css';
					return $attributes;
				},
				"<link rel=\"stylesheet\" id=\"test-css\" href=\"https://example.com/style.css\" />\n",
			),
			'removed'           => array(
				static function ( array $attributes ): array {
					unset( $attributes['id'] );
					return $attributes;
				},
				"<link rel=\"stylesheet\" href=\"https://example.com/style.css\" id=\"test-css\" />\n",
			),
			'lexical_variation' => array(
				static function ( array $attributes ): array {
					unset( $attributes['id'] );
					$attributes['ID'] = 'changed-css';
					return $attributes;
				},
				"<link rel=\"stylesheet\" href=\"https://example.com/style.css\" id=\"test-css\" />\n",
			),
		);
	}

	/**
	 * The `id` attribute may be used to locate the tag, as the user profile screen does for `colors-css`.
	 *
	 * @covers ::wp_get_style_link_tag
	 *
	 * @dataProvider data_style_attributes_filter_changing_id
	 *
	 * @param Closure $filter   Filter callback for the attributes.
	 * @param string  $expected Expected markup.
	 */
	public function test_style_attributes_filter_cannot_change_id( Closure $filter, string $expected ) {
		$this->setExpectedIncorrectUsage( 'wp_get_style_link_tag' );

		add_filter( 'wp_style_attributes', $filter );

		$this->assertSame(
			$expected,
			wp_get_style_link_tag(
				array(
					'rel'  => 'stylesheet',
					'id'   => 'test-css',
					'href' => 'https://example.com/style.css',
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_style_attributes_filter_can_add_id() {
		add_filter(
			'wp_style_attributes',
			static function ( array $attributes ): array {
				$attributes['id'] = 'added-css';
				return $attributes;
			}
		);

		$this->assertSame(
			"<link rel=\"stylesheet\" id=\"added-css\" />\n",
			wp_get_style_link_tag( array( 'rel' => 'stylesheet' ) )
		);
	}

	/**
	 * A lexical variation of the `id` name added alongside the supplied ID is dropped without a notice.
	 *
	 * @covers ::wp_get_style_link_tag
	 */
	public function test_style_attributes_filter_cannot_override_id_with_lexical_variation() {
		add_filter(
			'wp_style_attributes',
			static function ( array $attributes ): array {
				$attributes['ID'] = 'changed-css';
				return $attributes;
			}
		);

		$this->assertSame(
			"<link rel=\"stylesheet\" id=\"test-css\" />\n",
			wp_get_style_link_tag(
				array(
					'rel' => 'stylesheet',
					'id'  => 'test-css',
				)
			)
		);
	}

	/**
	 * @covers ::wp_print_style_link_tag
	 */
	public function test_print_style_link_tag_prints_get_style_link_tag() {
		$attributes = array(
			'rel'  => 'stylesheet',
			'href' => 'https://example.com/style.css',
		);

		$this->assertSame(
			wp_get_style_link_tag( $attributes ),
			get_echo( 'wp_print_style_link_tag', array( $attributes ) )
		);
	}
}
