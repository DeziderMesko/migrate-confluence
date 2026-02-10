<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Converts Confluence info macros to Wiki.js info blockquotes
 *
 * <ac:structured-macro ac:name="info" ac:schema-version="1" ac:macro-id="448329ba-06ad-4845-b3bf-2fd9a75c0d51">
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
 * {.is-info}
 */
class ConvertInfoMacro extends ConvertMacroToMarkdownBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'info';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBlockquoteClass(): string {
		return 'is-info';
	}
}
