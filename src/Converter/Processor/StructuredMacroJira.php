<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MediaWiki\Lib\WikiText\Template;

class StructuredMacroJira extends StructuredMacroProcessorBase {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'jira';
	}

	/**
	 * @param \DOMElement $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$params = $this->readParams( $node );

		// Convert to Markdown link format
		$jiraKey = $params['key'] ?? '';
		$jiraServer = $params['server'] ?? '';
		$serverId = $params['serverId'] ?? '';

		// Build JIRA URL - if no server is specified, use a placeholder
		$jiraUrl = $jiraServer ?: 'https://jira.example.com';
		$jiraUrl = rtrim( $jiraUrl, '/' );

		if ( !empty( $jiraKey ) ) {
			$markdownLink = "[$jiraKey]($jiraUrl/browse/$jiraKey)";
		} else {
			$markdownLink = "<!-- JIRA macro: no key specified -->";
		}

		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( $markdownLink ),
			$node
		);
	}

	protected function getWikiTextTemplateName(): string {
		return 'Jira';
	}

	/**
	 * @param \DOMElement $node
	 * @return array
	 */
	protected function readParams( \DOMElement $node ): array {
		$params = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				$paramValue = $childNode->nodeValue;
				$params[$paramName] = $paramValue;
			}
		}
		return $params;
	}

}
