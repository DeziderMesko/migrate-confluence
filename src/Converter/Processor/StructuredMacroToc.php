<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroToc extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'toc';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		// Wiki.js auto-generates TOC from headings, so we remove the TOC macro entirely
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( "" ),
			$node
		);
	}
}
