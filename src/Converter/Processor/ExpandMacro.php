<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNodeList;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Converts Confluence expand macros to HTML details/summary elements
 *
 * <ac:structured-macro ac:name="expand">
 * 	<ac:parameter ac:name="title">click here to expand</ac:parameter>
 *     <ac:rich-text-body>
 *          <ul>
 *              <li>something
 *                  <ul>
 *                      <li>something more</li>
 *                  </ul>
 *              </li>
 *          </ul>
 *      </ac:rich-text-body>
 *  </ac:structured-macro>
 *
 * Becomes:
 * <details>
 *   <summary>click here to expand</summary>
 *   <ul><li>something...</li></ul>
 * </details>
 */
class ExpandMacro implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = $dom->getElementsByTagName( 'structured-macro' );

		// Collect all DOMElements in a non-live list
		$actualMacros = [];
		foreach ( $macros as $macro ) {
			$macroName = $macro->getAttribute( 'ac:name' );
			if ( $macroName !== 'expand' ) {
				continue;
			}
			$actualMacros[] = $macro;
		}

		foreach ( $actualMacros as $actualMacro ) {
			$parentNode = $actualMacro->parentNode;

			// Create details element
			$details = $dom->createElement( 'details' );

			// Extract title parameter and create summary
			$parameterEls = $actualMacro->getElementsByTagName( 'parameter' );
			$title = 'Details';
			foreach ( $parameterEls as $parameterEl ) {
				$paramName = $parameterEl->getAttribute( 'ac:name' );
				if ( $paramName === 'title' ) {
					$title = trim( $parameterEl->nodeValue );
					break;
				}
			}

			$summary = $dom->createElement( 'summary' );
			$summaryText = $dom->createTextNode( $title );
			$summary->appendChild( $summaryText );
			$details->appendChild( $summary );

			// Extract rich text bodies and move their children into the details
			/** @var DOMNodeList $richTextBodies */
			$richTextBodies = $actualMacro->getElementsByTagName( 'rich-text-body' );
			$richTextBodyEls = [];
			foreach ( $richTextBodies as $richTextBody ) {
				$richTextBodyEls[] = $richTextBody;
			}

			foreach ( $richTextBodyEls as $richTextBodyEl ) {
				$childNodes = iterator_to_array( $richTextBodyEl->childNodes );
				foreach ( $childNodes as $richTextBodyChildEl ) {
					if ( $richTextBodyChildEl === $actualMacro ) {
						continue;
					}
					$details->appendChild( $richTextBodyChildEl );
				}
			}

			// Replace the macro with the details element
			$parentNode->replaceChild( $details, $actualMacro );
		}
	}
}
