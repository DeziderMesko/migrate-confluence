<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MediaWiki\Lib\Migration\ConverterBase;
use SplFileInfo;

/**
 * Converts HTML to Markdown using Pandoc
 *
 * This converter uses Pandoc to transform HTML into GitHub Flavored Markdown (GFM)
 * suitable for Wiki.js import.
 */
class PandocMarkdown extends ConverterBase {

	/**
	 * @inheritDoc
	 */
	protected function doConvert( SplFileInfo $file ): string {
		$path = $file->getPathname();
		// Use GitHub Flavored Markdown (gfm) for Wiki.js compatibility
		// GFM includes tables, task lists, strikethrough, and other GitHub extensions
		$command = "pandoc -f html -t gfm $path";
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.escapeshellcmd
		$escapedCommand = escapeshellcmd( $command );
		$result = [];
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( $escapedCommand, $result );

		$markdown = implode( "\n", $result );

		return $markdown;
	}
}
