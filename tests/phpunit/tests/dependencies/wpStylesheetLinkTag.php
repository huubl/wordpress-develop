<?php

/**
 * Test wp_get_stylesheet_link_tag() and wp_print_stylesheet_link_tag().
 *
 * @group dependencies
 * @group styles
 */
class Tests_Dependencies_wpStylesheetLinkTag extends WP_UnitTestCase {

	/**
	 * @covers ::wp_get_stylesheet_link_tag
	 */
	public function test_get_stylesheet_link_tag_escapes_uri_and_supports_boolean_attributes() {
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
			wp_get_stylesheet_link_tag(
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
	 * @covers ::wp_get_stylesheet_link_tag
	 */
	public function test_get_stylesheet_link_tag_attribute_value_types() {
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
			wp_get_stylesheet_link_tag(
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
	 * @covers ::wp_get_stylesheet_link_tag
	 */
	public function test_get_stylesheet_link_tag_ignores_duplicate_attributes() {
		$this->assertEqualHTML(
			"<link test=\"first\" />\n",
			wp_get_stylesheet_link_tag(
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
	 * @covers ::wp_get_stylesheet_link_tag
	 */
	public function test_get_stylesheet_link_tag_applies_wp_style_attributes_filter() {
		$filter = static function ( $attributes ) {
			$attributes['nonce'] = 'filtered-nonce';
			return $attributes;
		};

		add_filter( 'wp_style_attributes', $filter );

		$tag = wp_get_stylesheet_link_tag( array( 'rel' => 'stylesheet' ) );

		remove_filter( 'wp_style_attributes', $filter );

		$this->assertSame(
			"<link rel=\"stylesheet\" nonce=\"filtered-nonce\" />\n",
			$tag
		);
	}

	/**
	 * @covers ::wp_get_stylesheet_link_tag
	 */
	public function test_get_stylesheet_link_tag_prints_attributes_in_the_order_given() {
		$this->assertSame(
			"<link rel=\"preload\" as=\"style\" id=\"test-css\" href=\"https://example.com/style.css?one=1&#038;two=2\" media=\"print\" crossorigin />\n",
			wp_get_stylesheet_link_tag(
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
	 * @covers ::wp_get_stylesheet_link_tag
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_get_stylesheet_link_tag_rejects_invalid_attribute_names() {
		$this->assertSame(
			"<link rel=\"stylesheet\" />\n",
			wp_get_stylesheet_link_tag(
				array(
					'rel'                => 'stylesheet',
					'onload="alert(1)"'  => true,
					'x><script>alert(1)' => 'y',
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_stylesheet_link_tag
	 */
	public function test_get_stylesheet_link_tag_rejects_disallowed_href_protocols() {
		$tag       = wp_get_stylesheet_link_tag(
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
	 * @covers ::wp_print_stylesheet_link_tag
	 */
	public function test_print_stylesheet_link_tag_prints_get_stylesheet_link_tag() {
		$attributes = array(
			'rel'  => 'stylesheet',
			'href' => 'https://example.com/style.css',
		);

		$this->assertSame(
			wp_get_stylesheet_link_tag( $attributes ),
			get_echo( 'wp_print_stylesheet_link_tag', array( $attributes ) )
		);
	}
}
