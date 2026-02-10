<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreCode implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $markdown ): string {
		$markdown = preg_replace_callback(
			'#<pre class="PRESERVESYNTAXHIGHLIGHT"(.*?)>(.*?)</pre>#si', function ( $matches ) {
				$attribs = $this->getAttributes( $matches[1] );

				if ( isset( $attribs['data-broken-macro'] ) ) {
					// For broken macros, add a comment with the category
					$category = $attribs['data-broken-macro'];
					return "<!-- Broken code macro: $category -->\n```\n```";
				}

				$code = base64_decode( $matches[2] );
				$lang = $attribs['lang'] ?? '';

				// Create fenced code block with language specifier
				// Format: ```lang\ncode\n```
				return '```' . $lang . "\n" . $code . "\n" . '```';
			},
			$markdown
		);

		return $markdown;
	}

	/**
	 * @param string $params
	 * @return array
	 */
	private function getAttributes( string $params ): array {
		$matches = [];
		preg_match_all( '#\s(.*?)="(.*?)"#', $params, $matches );

		$attribs = [];
		for ( $index = 0; $index < count( $matches[1] ); $index++ ) {
			$name = $matches[1][$index];
			$attribs[$name] = $matches[2][$index];
		}

		return $attribs;
	}

	/**
	 * @param array $attribs
	 * @return string
	 */
	private function buildAttributes( array $attribs ): string {
		$params = '';
		foreach ( $attribs as $key => $value ) {
			$params .= ' ' . $key . '="' . $value . '"';
		}
		return $params;
	}
}
