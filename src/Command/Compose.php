<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\WikiJsComposer;
use SplFileInfo;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Compose extends CommandCompose {

	/**
	 *
	 * @inheritDoc
	 */
	protected function configure() {
		$config = parent::configure();

		/** @var InputDefinition */
		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'config',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the config yaml file'
			)
		);

		return $config;
	}

	/**
	 * @param array $config
	 * @return Compose
	 */
	public static function factory( $config ): Compose {
		return new static( $config );
	}

	/**
	 * @return bool
	 */
	protected function processFiles() {
		$this->readConfigFile( $this->config );

		// Override to use Wiki.js composer instead of MediaWiki XML builder
		$this->ensureTargetDirs();
		$this->workspace = new Workspace( new SplFileInfo( $this->src ) );

		$this->buckets = new DataBuckets( [
			'files',
			'revision-contents',
			'title-attachments',
			'title-metadata',
			'title-revisions',
			'page-id-to-title-map',
			'space-id-to-prefix-map'
		] );
		$this->buckets->loadFromWorkspace( $this->workspace );

		// Create Wiki.js composer
		$composer = new WikiJsComposer( $this->config, $this->workspace, $this->buckets );
		$composer->setOutput( $this->output );

		// Generate Markdown files
		$composer->compose();
	}

	/**
	 * Ensure target directories exist
	 */
	private function ensureTargetDirs() {
		$path = "{$this->dest}/result/uploads";
		if ( !file_exists( $path ) ) {
			mkdir( $path, 0755, true );
		}
	}

	/**
	 * @param array &$config
	 * @return void
	 */
	private function readConfigFile( &$config ): void {
		$filename = $this->input->getOption( 'config' );
		if ( is_file( $filename ) ) {
			$content = file_get_contents( $filename );
			if ( $content ) {
				try {
					$yaml = Yaml::parse( $content );
					$config = array_merge( $config, $yaml );
				} catch ( ParseException $e ) {
					$this->output->writeln( 'Invalid config file provided' );
					exit( true );
				}
			}
		}
	}
}
