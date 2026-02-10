<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Converts Confluence note macros to Wiki.js warning blockquotes
 *
 * <ac:structured-macro ac:name="note" ac:schema-version="1" ac:macro-id="448329ba-06ad-4845-b3bf-2fd9a75c0d51">
 *	<ac:parameter ac:name="title">/api/Device/devices</ac:parameter>
 *	<ac:rich-text-body>
 *		<p class="title">...</p>
 *		<p>...</p>
 *	</ac:rich-text-body>
 * </ac:structured-macro>
 *
 * Becomes:
 * > **title**
 * > content
 * {.is-warning}
 */
class ConvertNoteMacro extends ConvertMacroToMarkdownBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'note';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBlockquoteClass(): string {
		return 'is-warning';
	}
}
