<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;

class UserLink extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'user';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessLink( DOMNode $node ): void {
		$linkParts = [];

		if ( $node instanceof DOMElement ) {
			$isBrokenLink = false;
			$userKey = $node->getAttribute( 'ri:userkey' );

			if ( !empty( $userKey ) ) {
				$username = $this->dataLookup->getUsernameFromUserKey( $userKey );
				$linkParts[] = 'User:' . $username;
				$linkParts[] = $username;
			} else {
				$linkParts[] = 'NULL';
				$linkParts[] = 'NULL';
				$isBrokenLink = true;
			}

			$this->getLinkBody( $node, $linkParts );

			$replacement = $this->getBrokenLinkReplacement();

			if ( !empty( $linkParts ) ) {
				$replacement = $this->makeLink( $linkParts );
			}

			if ( $isBrokenLink ) {
				$replacement .= '<!-- Broken user link -->';
			}

			$this->replaceLink( $node, $replacement );
		}
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	public function makeLink( array $linkParts ): string {
		$linkParts = array_map( 'trim', $linkParts );

		// linkParts[0] is "User:username" format
		// linkParts[1] is the username
		// linkParts[2] (if present) is custom link text

		// For Wiki.js, we can either create a user profile link or just use plain text
		// Since Wiki.js doesn't have a standard user namespace like MediaWiki,
		// we'll create a simple link to /user/username or just use plain text

		if ( count( $linkParts ) >= 2 ) {
			$username = $linkParts[1];
			$label = count( $linkParts ) > 2 ? $linkParts[2] : $username;

			// Option 1: Link to user profile (if Wiki.js has user profiles at /user/username)
			// return '[' . $label . '](/user/' . strtolower( $username ) . ')';

			// Option 2: Just use plain text with @ mention style
			return '@' . $username;
		}

		return implode( ' ', $linkParts );
	}
}
