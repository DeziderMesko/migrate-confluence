<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class FixImagesWithExternalUrl implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $markdown ): string {
		// Fix Markdown images with external URLs: ![alt](http://...){attrs}
		$markdown = preg_replace_callback(
			"/!\[([^\]]*)\]\((http[s]?:\/\/[^\)]+)\)(\{[^\}]+\})?/",
			static function ( $matches ) {
				$alt = $matches[1];
				$url = $matches[2];
				$attrs = $matches[3] ?? '';

				if ( !empty( $attrs ) ) {
					// Convert {height=150} style attributes to HTML attributes
					return '<img src="' . $url . '" alt="' . htmlspecialchars( $alt ) . '" ' . $attrs . ' />';
				}

				return '<img src="' . $url . '" alt="' . htmlspecialchars( $alt ) . '" />';
			},
			$markdown
		);

		return $markdown;
	}
}
