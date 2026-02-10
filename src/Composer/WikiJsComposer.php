<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Yaml\Yaml;

/**
 * Composer for Wiki.js Markdown output
 *
 * This composer creates individual Markdown files with YAML frontmatter
 * for Wiki.js import, instead of MediaWiki XML.
 *
 * Output structure:
 * - result/pages/*.md - Markdown files with YAML frontmatter
 * - result/uploads/ - All attachments and images
 */
class WikiJsComposer implements IOutputAwareInterface {

	/**
	 * @var array
	 */
	private $config;

	/**
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * @var DataBuckets
	 */
	private $dataBuckets;

	/**
	 * @var Output
	 */
	private $output = null;

	/**
	 * @var array
	 */
	private $advancedConfig = [];

	/**
	 * @var string
	 */
	private $resultDir;

	/**
	 * @var string
	 */
	private $pagesDir;

	/**
	 * @var string
	 */
	private $uploadsDir;

	/**
	 * @var string
	 */
	private $workspacePath;

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		$this->config = $config;
		$this->workspace = $workspace;

		$this->dataBuckets = new DataBuckets( [
			'space-id-homepages',
			'title-attachments',
			'title-revisions',
			'files',
			'additional-files',
			'page-id-to-title-map',
			'space-id-to-prefix-map',
			'page-revision-history'
		] );

		$this->dataBuckets->loadFromWorkspace( $this->workspace );

		if ( isset( $config['config'] ) ) {
			$this->advancedConfig = $config['config'];
		}

		// Set up output directories using reflection to access private workspaceDir
		$reflection = new \ReflectionClass( $this->workspace );
		$property = $reflection->getProperty( 'workspaceDir' );
		$property->setAccessible( true );
		$workspaceDir = $property->getValue( $this->workspace );
		$this->workspacePath = $workspaceDir->getPathname();

		$this->resultDir = $this->workspacePath . '/result';
		$this->pagesDir = $this->resultDir . '/pages';
		$this->uploadsDir = $this->resultDir . '/uploads';
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	/**
	 * Main compose method - creates Markdown files for Wiki.js
	 *
	 * @return void
	 */
	public function compose(): void {
		$this->output->writeln( "Starting Wiki.js Markdown composition..." );

		// Create output directories
		$this->createDirectories();

		// Copy attachments and images to uploads directory
		$this->copyUploads();

		// Process each converted page and create Markdown files
		$this->createMarkdownPages();

		// Process historical revisions
		$this->createHistoricalRevisions();

		$this->output->writeln( "\nComposition complete!" );
		$this->output->writeln( "Output location: " . $this->resultDir );
		$this->output->writeln( "- Pages: " . $this->pagesDir );
		$this->output->writeln( "- Uploads: " . $this->uploadsDir );
	}

	/**
	 * Create necessary output directories
	 */
	private function createDirectories(): void {
		if ( !is_dir( $this->resultDir ) ) {
			mkdir( $this->resultDir, 0755, true );
		}
		if ( !is_dir( $this->pagesDir ) ) {
			mkdir( $this->pagesDir, 0755, true );
		}
		if ( !is_dir( $this->uploadsDir ) ) {
			mkdir( $this->uploadsDir, 0755, true );
		}
	}

	/**
	 * Copy all attachments and images to the uploads directory
	 */
	private function copyUploads(): void {
		$this->output->writeln( "\nCopying uploads..." );

		$filesMap = $this->dataBuckets->getBucketData( 'files' );
		$additionalFiles = $this->dataBuckets->getBucketData( 'additional-files' );

		// Merge file lists
		$allFiles = array_merge( $filesMap ?? [], $additionalFiles ?? [] );

		$imagesSourceDir = $this->workspacePath . '/images';

		if ( !is_dir( $imagesSourceDir ) ) {
			$this->output->writeln( "No images directory found, skipping file copy." );
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $imagesSourceDir )
		);

		$copiedCount = 0;
		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$filename = $file->getFilename();
				$destination = $this->uploadsDir . '/' . $filename;

				if ( copy( $file->getPathname(), $destination ) ) {
					$copiedCount++;
				}
			}
		}

		$this->output->writeln( "Copied $copiedCount files to uploads directory." );
	}

	/**
	 * Create Markdown files from converted wiki pages
	 */
	private function createMarkdownPages(): void {
		$this->output->writeln( "\nCreating Markdown pages..." );

		$pagesRevisions = $this->dataBuckets->getBucketData( 'title-revisions' );

		$convertedDir = $this->workspacePath . '/content/wikitext';

		if ( !is_dir( $convertedDir ) ) {
			$this->output->writeln( "No converted directory found!" );
			return;
		}

		$pageCount = 0;
		foreach ( $pagesRevisions as $pageTitle => $revisions ) {
			$this->output->writeln( "Processing: $pageTitle" );

			// Extract revision ID from the revision string (e.g., "9011735@177-20250310231236")
			if ( empty( $revisions ) || !isset( $revisions[0] ) ) {
				$this->output->writeln( "  Warning: No revision data for: $pageTitle" );
				continue;
			}

			$revisionString = $revisions[0];
			$revisionParts = explode( '@', $revisionString );
			$revisionId = $revisionParts[0];

			// Files are stored by revision ID, not page ID
			$wikiFile = $convertedDir . '/' . $revisionId . '.wiki';

			if ( !file_exists( $wikiFile ) ) {
				$this->output->writeln( "  Warning: File not found: $wikiFile" );
				continue;
			}

			// Read the converted Markdown content
			$markdown = file_get_contents( $wikiFile );

			// Parse revision info for frontmatter
			$pageRevision = $this->parseRevisionInfo( $revisionString );

			// Extract metadata for frontmatter
			$frontmatter = $this->buildFrontmatter( $pageTitle, $pageRevision );

			// Create the final Markdown file with YAML frontmatter
			$fullContent = $frontmatter . "\n" . $markdown;

			// Determine output path
			$outputPath = $this->getOutputPath( $pageTitle );

			// Ensure directory exists
			$outputDir = dirname( $outputPath );
			if ( !is_dir( $outputDir ) ) {
				mkdir( $outputDir, 0755, true );
			}

			// Write the file
			file_put_contents( $outputPath, $fullContent );

			$pageCount++;
		}

		$this->output->writeln( "Created $pageCount Markdown pages." );
	}

	/**
	 * Parse revision info string to extract timestamp
	 *
	 * @param string $revisionString Format: "9011735@177-20250310231236"
	 * @return array
	 */
	private function parseRevisionInfo( string $revisionString ): array {
		// Parse "9011735@177-20250310231236" format
		// Format: <revisionId>@<version>-<timestamp>
		$parts = explode( '@', $revisionString );
		if ( count( $parts ) < 2 ) {
			return [];
		}

		$versionTimestamp = $parts[1];
		$vtParts = explode( '-', $versionTimestamp );
		if ( count( $vtParts ) < 2 ) {
			return [];
		}

		$timestamp = $vtParts[1];
		// Convert timestamp format 20250310231236 to ISO 8601
		if ( strlen( $timestamp ) >= 14 ) {
			$year = substr( $timestamp, 0, 4 );
			$month = substr( $timestamp, 4, 2 );
			$day = substr( $timestamp, 6, 2 );
			$hour = substr( $timestamp, 8, 2 );
			$minute = substr( $timestamp, 10, 2 );
			$second = substr( $timestamp, 12, 2 );
			$isoDate = "$year-$month-$day" . "T" . "$hour:$minute:$second" . "Z";

			return [
				'timestamp' => $isoDate,
				'author' => '', // Not available in this format
			];
		}

		return [];
	}

	/**
	 * Build YAML frontmatter for a page
	 *
	 * @param string $pageTitle
	 * @param array $pageRevision
	 * @return string
	 */
	private function buildFrontmatter( string $pageTitle, array $pageRevision ): string {
		// Extract clean title (remove namespace prefix)
		$displayTitle = $this->getDisplayTitle( $pageTitle );

		// Build frontmatter data
		$frontmatterData = [
			'title' => $displayTitle,
			'description' => '',
		];

		// Add tags if configured
		$tags = $this->getTags( $pageTitle );
		if ( !empty( $tags ) ) {
			$frontmatterData['tags'] = $tags;
		}

		// Add author if available
		if ( isset( $pageRevision['author'] ) && !empty( $pageRevision['author'] ) ) {
			$frontmatterData['author'] = $pageRevision['author'];
		}

		// Add date if available
		if ( isset( $pageRevision['timestamp'] ) && !empty( $pageRevision['timestamp'] ) ) {
			$frontmatterData['date'] = $pageRevision['timestamp'];
		}

		// Convert to YAML
		$yaml = Yaml::dump( $frontmatterData, 2, 2 );

		return "---\n" . $yaml . "---";
	}

	/**
	 * Get display title from full page title
	 *
	 * @param string $pageTitle
	 * @return string
	 */
	private function getDisplayTitle( string $pageTitle ): string {
		// Remove namespace prefix (everything before last colon)
		if ( strpos( $pageTitle, ':' ) !== false ) {
			$parts = explode( ':', $pageTitle );
			$title = array_pop( $parts );
		} else {
			$title = $pageTitle;
		}

		// Replace underscores with spaces
		$title = str_replace( '_', ' ', $title );

		return $title;
	}

	/**
	 * Get tags for a page
	 *
	 * @param string $pageTitle
	 * @return array
	 */
	private function getTags( string $pageTitle ): array {
		$tags = [];

		// Add global categories from config
		if ( isset( $this->advancedConfig['categories'] ) ) {
			$tags = array_merge( $tags, $this->advancedConfig['categories'] );
		}

		// Extract namespace as a tag
		if ( strpos( $pageTitle, ':' ) !== false ) {
			$parts = explode( ':', $pageTitle );
			array_pop( $parts ); // Remove page name
			$namespace = implode( ':', $parts );
			if ( !empty( $namespace ) ) {
				$tags[] = $namespace;
			}
		}

		return array_unique( $tags );
	}

	/**
	 * Get output file path for a page
	 *
	 * @param string $pageTitle
	 * @return string
	 */
	private function getOutputPath( string $pageTitle ): string {
		// Convert "NS:Prefix/Page_Title" to "ns/prefix/page-title.md"
		$path = strtolower( $pageTitle );
		$path = str_replace( ':', '/', $path );
		$path = str_replace( '_', '-', $path );

		// Ensure .md extension
		if ( substr( $path, -3 ) !== '.md' ) {
			$path .= '.md';
		}

		return $this->pagesDir . '/' . $path;
	}

	/**
	 * Create Markdown files for historical revisions
	 */
	private function createHistoricalRevisions(): void {
		$this->output->writeln( "\nCreating historical revision files..." );

		$revisionHistory = $this->dataBuckets->getBucketData( 'page-revision-history' );
		if ( empty( $revisionHistory ) ) {
			$this->output->writeln( "No revision history data found." );
			return;
		}

		$convertedDir = $this->workspacePath . '/content/wikitext';
		$totalRevisionsExported = 0;

		foreach ( $revisionHistory as $pageTitle => $revisions ) {
			// Skip pages with only one revision (no history to export)
			if ( count( $revisions ) <= 1 ) {
				continue;
			}

			$exportedCount = 0;
			foreach ( $revisions as $revision ) {
				// Skip the current revision (already exported in main pages directory)
				if ( $revision['isCurrent'] ) {
					continue;
				}

				// Get the bodyContentIds (may have multiple, joined with '/')
				$bodyContentIds = $revision['bodyContentIds'];
				$bodyContentId = is_array( $bodyContentIds ) ? $bodyContentIds[0] : $bodyContentIds;

				// Find the .wiki file for this revision
				$wikiFile = $convertedDir . '/' . $bodyContentId . '.wiki';

				if ( !file_exists( $wikiFile ) ) {
					$this->output->writeln( "  Warning: File not found for revision: $wikiFile" );
					continue;
				}

				// Read the converted Markdown content
				$markdown = file_get_contents( $wikiFile );

				// Build frontmatter with revision metadata
				$frontmatter = $this->buildRevisionFrontmatter( $pageTitle, $revision );

				// Create the full content
				$fullContent = $frontmatter . "\n" . $markdown;

				// Determine output path for historical revision
				$outputPath = $this->getHistoricalRevisionPath( $pageTitle, $revision['sequentialNumber'] );

				// Ensure directory exists
				$outputDir = dirname( $outputPath );
				if ( !is_dir( $outputDir ) ) {
					mkdir( $outputDir, 0755, true );
				}

				// Write the file
				file_put_contents( $outputPath, $fullContent );

				$exportedCount++;
				$totalRevisionsExported++;
			}

			if ( $exportedCount > 0 ) {
				$this->output->writeln( "  Exported $exportedCount historical revision(s) for: $pageTitle" );
			}
		}

		$this->output->writeln( "Exported $totalRevisionsExported historical revisions total." );
	}

	/**
	 * Build YAML frontmatter for a historical revision
	 *
	 * @param string $pageTitle
	 * @param array $revision
	 * @return string
	 */
	private function buildRevisionFrontmatter( string $pageTitle, array $revision ): string {
		// Extract clean title (remove namespace prefix)
		$displayTitle = $this->getDisplayTitle( $pageTitle );

		// Build frontmatter data
		$frontmatterData = [
			'title' => $displayTitle,
			'description' => '',
			'revision' => $revision['sequentialNumber'],
			'version' => $revision['version'],
		];

		// Add tags if configured
		$tags = $this->getTags( $pageTitle );
		if ( !empty( $tags ) ) {
			$frontmatterData['tags'] = $tags;
		}

		// Convert timestamp to ISO 8601 format
		if ( isset( $revision['timestamp'] ) ) {
			$timestamp = $revision['timestamp'];
			// Convert timestamp format 20250310231236 to ISO 8601
			if ( strlen( $timestamp ) >= 14 ) {
				$year = substr( $timestamp, 0, 4 );
				$month = substr( $timestamp, 4, 2 );
				$day = substr( $timestamp, 6, 2 );
				$hour = substr( $timestamp, 8, 2 );
				$minute = substr( $timestamp, 10, 2 );
				$second = substr( $timestamp, 12, 2 );
				$isoDate = "$year-$month-$day" . "T" . "$hour:$minute:$second" . "Z";
				$frontmatterData['date'] = $isoDate;
			}
		}

		// Convert to YAML
		$yaml = Yaml::dump( $frontmatterData, 2, 2 );

		return "---\n" . $yaml . "---";
	}

	/**
	 * Get output path for a historical revision
	 *
	 * @param string $pageTitle
	 * @param int $sequentialNumber
	 * @return string
	 */
	private function getHistoricalRevisionPath( string $pageTitle, int $sequentialNumber ): string {
		// Convert "NS:Prefix/Page_Title" to path components
		$path = strtolower( $pageTitle );
		$path = str_replace( ':', '/', $path );
		$path = str_replace( '_', '-', $path );

		// Get the base filename without .md extension
		$filename = basename( $path, '.md' );
		$dirname = dirname( $path );

		// Build history path: pages/{namespace}/history/{page-slug}/{page-slug}-v{N}.md
		if ( $dirname === '.' ) {
			$historyPath = 'history/' . $filename . '/' . $filename . '-v' . $sequentialNumber . '.md';
		} else {
			$historyPath = $dirname . '/history/' . $filename . '/' . $filename . '-v' . $sequentialNumber . '.md';
		}

		return $this->pagesDir . '/' . $historyPath;
	}
}
