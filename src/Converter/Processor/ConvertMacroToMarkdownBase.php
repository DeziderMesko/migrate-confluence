<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Base class for converting Confluence macros to Markdown blockquotes
 *
 * Wiki.js supports styled blockquotes using the syntax:
 * > content
 * {.is-info}
 *
 * This base class converts Confluence macros like info, note, tip, warning
 * into HTML blockquote elements with appropriate CSS classes that will be
 * processed by Pandoc into Markdown.
 *
 * Example input:
 * <ac:structured-macro ac:name="info">
 *   <ac:parameter ac:name="title">Important</ac:parameter>
 *   <ac:rich-text-body><p>Content here</p></ac:rich-text-body>
 * </ac:structured-macro>
 *
 * Example output (before Pandoc):
 * <blockquote class="is-info" data-title="Important">
 *   <p>Content here</p>
 * </blockquote>
 *
 * Pandoc will convert this to:
 * > Content here
 * {.is-info}
 */
abstract class ConvertMacroToMarkdownBase implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = $dom->getElementsByTagName( 'structured-macro' );
		$requiredMacroName = $this->getMacroName();
		$blockquoteClass = $this->getBlockquoteClass();

		// Collect all DOMElements in a non-live list
		$actualMacros = [];
		foreach ( $macros as $macro ) {
			$macroName = $macro->getAttribute( 'ac:name' );
			if ( $macroName !== $requiredMacroName ) {
				continue;
			}
			$actualMacros[] = $macro;
		}

		foreach ( $actualMacros as $actualMacro ) {
			$parentNode = $actualMacro->parentNode;

			// Create blockquote element
			$blockquote = $dom->createElement( 'blockquote' );
			$blockquote->setAttribute( 'class', $blockquoteClass );

			// Extract and add title parameter if present
			$parameterEls = $actualMacro->getElementsByTagName( 'parameter' );
			$title = null;
			foreach ( $parameterEls as $parameterEl ) {
				$paramName = $parameterEl->getAttribute( 'ac:name' );
				if ( $paramName === 'title' ) {
					$title = trim( $parameterEl->nodeValue );
					break;
				}
			}

			// If there's a title, add it as a strong element at the start
			if ( $title !== null && $title !== '' ) {
				$titlePara = $dom->createElement( 'p' );
				$titleStrong = $dom->createElement( 'strong' );
				$titleText = $dom->createTextNode( $title );
				$titleStrong->appendChild( $titleText );
				$titlePara->appendChild( $titleStrong );
				$blockquote->appendChild( $titlePara );
			}

			// Extract rich text bodies and move their children into the blockquote
			/** @var DOMNodeList $richTextBodies */
			$richTextBodies = $actualMacro->getElementsByTagName( 'rich-text-body' );
			$richTextBodyEls = [];
			foreach ( $richTextBodies as $richTextBody ) {
				$richTextBodyEls[] = $richTextBody;
			}

			foreach ( $richTextBodyEls as $richTextBodyEl ) {
				// For some odd reason, iterating `$richTextBodyEl->childNodes` directly
				// will give children of `$dom->firstChild`.
				// Using `iterator_to_array` as a workaround here.
				$childNodes = iterator_to_array( $richTextBodyEl->childNodes );
				foreach ( $childNodes as $richTextBodyChildEl ) {
					if ( $richTextBodyChildEl === $actualMacro ) {
						continue;
					}
					$blockquote->appendChild( $richTextBodyChildEl );
				}
			}

			// Replace the macro with the blockquote
			$parentNode->replaceChild( $blockquote, $actualMacro );
		}
	}

	/**
	 * Get the Confluence macro name to process
	 *
	 * @return string The macro name (e.g., "info", "note", "tip", "warning")
	 */
	abstract protected function getMacroName(): string;

	/**
	 * Get the CSS class for the blockquote element
	 *
	 * This should return one of the Wiki.js blockquote classes:
	 * - "is-info" for informational boxes (blue)
	 * - "is-warning" for warning boxes (orange)
	 * - "is-danger" for error/critical boxes (red)
	 * - "is-success" for success/tip boxes (green)
	 *
	 * @return string The CSS class name
	 */
	abstract protected function getBlockquoteClass(): string;
}
