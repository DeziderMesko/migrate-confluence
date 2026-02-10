<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class NestedHeadings implements IPostprocessor {

	/** @var string - Matches Markdown headings like "* # Heading" or "** ## Heading" */
	private $regEx = '/^(\*{1,6})\s?(#{1,6})\s+(.*)$/m';

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$lines = explode( "\n", $wikiText );

		for ( $index = 0; $index < count( $lines ); $index++ ) {
			if ( strpos( $lines[$index], '*', 0 ) === 0 ) {
				$hasNextLine = ( ( $index + 1 ) < count( $lines ) ) ? true : false;

				$nextLineIsListItem = false;
				if ( $hasNextLine ) {
					$nextLineIsListItem = ( strpos( $lines[$index + 1], '*', 0 ) === 0 ) ? true : false;
				}

				if ( $hasNextLine && $nextLineIsListItem ) {
					$nextIndex = $this->processList( $lines, $index );
					$index = $nextIndex;
				} else {
					$this->processHeading( $lines, $index );
				}
			}
		}

		$wikiText = implode( "\n", $lines );

		return $wikiText;
	}

	/**
	 * @param array &$lines
	 * @param int $index
	 */
	private function processHeading( &$lines, $index ) {
		$matches = [];

		$line = $lines[$index];
		preg_match( $this->regEx, $line, $matches );

		if ( count( $matches ) > 0 ) {
			$headingMarker = $matches[2]; // e.g., "##"
			$text = trim( $matches[3] );

			$lines[$index] = $this->getHeadingReplacement( $headingMarker, $text );
		}
	}

	/**
	 * @param array &$lines
	 * @param int $index
	 * @return int
	 */
	private function processList( &$lines, $index ): int {
		$matches = [];

		$line = $lines[$index];
		preg_match( $this->regEx, $line, $matches );
		while ( count( $matches ) > 0 ) {
			$listMarker = $matches[1]; // e.g., "*" or "**"
			$text = trim( $matches[3] );

			$lines[$index] = $this->getListReplacement( $listMarker, $text );

			$index++;
			if ( $index >= count( $lines ) ) {
				$matches = 0;
			}

			$line = $lines[$index];
			preg_match( $this->regEx, $line, $matches );
		}

		return $index;
	}

	/**
	 * @param string $markup
	 * @param string $text
	 * @return string
	 */
	private function getListReplacement( $markup, $text ): string {
		return $markup . ' ' . $text;
	}

	/**
	 * @param string $markup - Markdown heading marker like "##"
	 * @param string $text
	 * @return string
	 */
	private function getHeadingReplacement( $markup, $text ): string {
		return $markup . ' ' . $text;
	}
}
