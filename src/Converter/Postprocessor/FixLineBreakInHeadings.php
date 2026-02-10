<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixLineBreakInHeadings implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $markdown ): string {
		// Match any Markdown heading (# to ######) and remove <br /> and newlines from it
		$markdown = preg_replace_callback(
			'/^(#{1,6})\s+(.*)$/m',
			static function ( $matches ) {
				$hashes = $matches[1];
				$headingText = $matches[2];
				// Remove <br /> tags and newlines from heading text
				$cleanHeadingText = str_replace( [ "<br />", "<br/>", "<br>", "\n" ], ' ', $headingText );
				$cleanHeadingText = trim( $cleanHeadingText );
				return $hashes . ' ' . $cleanHeadingText;
			},
			$markdown
		);
		return $markdown;
	}
}
