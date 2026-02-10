<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;

class PageLink extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'page';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessLink( DOMNode $node ): void {
		if ( $node instanceof DOMElement ) {
			$isBrokenLink = false;
			$rawPageTitle = $node->getAttribute( 'ri:content-title' );
			$spaceId = $this->ensureSpaceId( $node );

			$confluencePageKey = $this->generatePageConfluenceKey( $spaceId, $rawPageTitle );

			$targetTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey( $confluencePageKey );
			if ( !empty( $targetTitle ) ) {
				$linkParts[] = $targetTitle;
			} else {
				// If not in migation data, save some info for manual post migration work
				$linkParts[] = $this->generateConfluenceKey( $spaceId, $rawPageTitle );
				$isBrokenLink = true;
			}

			$this->getLinkBody( $node, $linkParts );

			$replacement = $this->getBrokenLinkReplacement();

			if ( !empty( $linkParts ) ) {
				$replacement = $this->makeLink( $linkParts );
			}

			if ( $isBrokenLink ) {
				$replacement .= '<!-- Broken page link -->';
			}

			$this->replaceLink( $node, $replacement );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return int
	 */
	private function ensureSpaceId( DOMNode $node ): int {
		$spaceId = $this->currentSpaceId;
		$spaceKey = $node->getAttribute( 'ri:space-key' );

		if ( !empty( $spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpacePrefix( $spaceKey );
		}

		return $spaceId;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generatePageConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		$genericTitleBuilder = new GenericTitleBuilder( [] );
			$rawPageTitle = $genericTitleBuilder
				->appendTitleSegment( $rawPageTitle )->build();
			$rawPageTitle = str_replace( ' ', '_', $rawPageTitle );
		return "$spaceId---$rawPageTitle";
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
			$genericTitleBuilder = new GenericTitleBuilder( [] );
			$rawPageTitle = $genericTitleBuilder
				->appendTitleSegment( $rawPageTitle )->build();
			$rawPageTitle = str_replace( ' ', '_', $rawPageTitle );
		return "Confluence---$spaceId---$rawPageTitle";
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	public function makeLink( array $linkParts ): string {
		$linkParts = array_map( 'trim', $linkParts );

		// linkParts[0] is the target title (namespace:path/page format from MediaWiki)
		// linkParts[1] (if present) is the link text/label

		$target = $linkParts[0];

		// Convert MediaWiki-style title to Wiki.js path format
		// "NS:Prefix/Page_Title" -> "/ns/prefix/page-title"
		$path = $this->convertTitleToPath( $target );

		// Determine link label
		if ( count( $linkParts ) > 1 ) {
			// Use provided label
			$label = $linkParts[1];
		} else {
			// Extract label from the path (last segment, replace underscores/dashes with spaces)
			$pathParts = explode( '/', trim( $path, '/' ) );
			$label = array_pop( $pathParts );
			$label = str_replace( '_', ' ', $label );
			$label = str_replace( '-', ' ', $label );
		}

		// Create Markdown link: [label](/path)
		$replacement = '[' . $label . '](' . $path . ')';

		return $replacement;
	}

	/**
	 * Convert MediaWiki-style title to Wiki.js path
	 * "NS:Prefix/Page_Title" -> "/ns/prefix/page-title"
	 *
	 * @param string $title
	 * @return string
	 */
	private function convertTitleToPath( string $title ): string {
		// Replace colons with slashes
		$path = str_replace( ':', '/', $title );

		// Convert to lowercase
		$path = strtolower( $path );

		// Replace underscores with hyphens (Wiki.js convention)
		$path = str_replace( '_', '-', $path );

		// Ensure leading slash
		if ( substr( $path, 0, 1 ) !== '/' ) {
			$path = '/' . $path;
		}

		return $path;
	}

}
