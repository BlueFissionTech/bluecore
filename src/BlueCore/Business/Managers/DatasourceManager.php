<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;
use BlueFission\Collections\Collection;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Data\Storage\Storage;
use BlueFission\Utils\File;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class DatasourceManager extends Service {

	private $_deltaDir = '';
	private $_generatorDir = '';
	private $_db = null;

	public function __construct( MySQLLink $link, Storage $storage )
    {
		parent::__construct();
		$link->open();
		$this->_deltaDir = resolve_path('datasources/structure/');
		$this->_generatorDir = resolve_path('datasources/generator/');
		$this->_db = $storage;
		$this->_db->config('name', 'migrations');
		$this->_db->activate();
	}

	public function setDeltaDirectory( $directory )
	{
		$this->_deltaDir = $directory;
	}

	public function setGeneratorDirectory( $directory )
	{
		$this->_generatorDir = $directory;
	}

	public function runMigrations($batch = null)
	{
		$batch = $batch ?: 'opus';

		if ( !file_exists($this->_deltaDir) ) {
			return;
		}

		$deltas = $this->loadDeltas();
		$iteration = 0;
		$storedDeltas = $this->getDeltasFromDB($iteration);
		$iteration++;

		$deltas = Arr::make($deltas)
			->diff($storedDeltas)
			->values()
			->toArray();

		$dbActive = false;
		if ( MySQLLink::tableExists('migrations') ) {
			$dbActive = true;
		}

		foreach ( $deltas as $delta ) {
			$classname = '';
			if ( strpos($delta, '.') != 0 ) {
				$classname = $this->findClassName( $this->_deltaDir . $delta );
				if ( $classname ) {
					include_once($this->_deltaDir . $delta);
					$this->_db->clear();
					$this->_db->assign([
						'name' => $delta,
						'batch' => $batch,
						'iteration' => $iteration,
						'status' => 1
					])->write();
					$this->_db->id($this->_db->lastRow());
					$status = 2;
					$object = \App::makeInstance($classname);
					try {
						call_user_func([$object, 'change']);
					} catch ( \Exception $e ) {
						$status = 3;
					}
					if ( !$dbActive ) {
						$this->_db->activate();
						$dbActive = true;
					}
					$this->_db->assign([
						'name' => $delta,
						'batch' => $batch,
						'iteration' => $iteration,
						'status' => $status
					])->write();
				}
			}
		}
	}

	public function revertMigrations()
	{
		$iteration = 1;
		$deltas = $this->getDeltasFromDB($iteration);

		if ( empty($deltas) && !file_exists($this->_deltaDir) ) {
			return;
		}

		if ( empty($deltas) ) {
			$deltas = $this->loadDeltas(1);
		}

		foreach ( $deltas as $delta ) {
			$classname = '';
			if ( strpos($delta, '.') != 0 ) {
				$classname = $this->findClassName( $this->_deltaDir . $delta );
				if ( $classname ) {
					include_once($this->_deltaDir . $delta);
					// $object = new $classname();
					$object = \App::makeInstance($classname);
					$this->_db->clear();
					try {
						call_user_func([$object, 'revert']);
					} catch ( \Exception $e ) {
						throw new Exception("Error reverting migration: {$delta}. " . $e->getMessage());
					} finally {
						$this->_db->assign([
							'name' => $delta,
							'iteration' => $iteration,
						])->delete();
					}
				}
			}
		}
	}

	public function revertBatch($batch)
	{
		$this->_db->config('name', 'migrations');
		$this->_db->clear();
		$this->_db->where('batch', $batch)
			->order('iteration', 'DESC')
			->read();

		$iteration = $this->_db->result()->first()->iteration ?: 1;
		$deltas = $this->_db->result()->map(function($row) {
			return $row->delta;
		})->toArray();

		foreach ( $deltas as $delta ) {
			$classname = '';
			if ( strpos($delta, '.') != 0 ) {
				$classname = $this->findClassName( $this->_deltaDir . $delta );
				if ( $classname ) {
					include_once($this->_deltaDir . $delta);
					// $object = new $classname();
					$object = \App::makeInstance($classname);
					try {
						call_user_func([$object, 'revert']);
					} catch ( \Exception $e ) {
						throw new Exception("Error reverting migration: {$delta}. " . $e->getMessage());
					} finally {
						$this->_db->assign([
							'name' => $delta,
							'iteration' => $iteration,
						])->delete();
					}
				}
			}
		}
	}

	public function populate( $auto = false )
	{
		if (!file_exists($this->_generatorDir)) {
			return;
		}

		$generators = $this->loadGenerators();
		if ( Arr::has($generators, 'RootSeeder.php', true) ) {
			$classname = $this->findClassName( $this->_generatorDir . 'RootSeeder.php' );
			if ( $classname ) {
				include_once($this->_generatorDir . 'RootSeeder.php');
				$object = \App::makeInstance($classname);
				if ( method_exists($object, 'seeders') ) {
					$generators = Arr::make(call_user_func([$object, 'seeders']))
						->map(fn ($generator) => Str::endsWith($generator, '.php') ? $generator : $generator . '.php')
						->toArray();
				}
			}
		}

		foreach ( $generators as $generator ) {
			$classname = '';

			if ( strpos($generator, '.') !== 0 ) {
				$generator = Str::endsWith($generator, '.php') ? $generator : $generator . '.php';
				
				$classname = $this->findClassName( $this->_generatorDir . $generator );
				if ( $classname ) {
					include_once($this->_generatorDir . $generator);
					$object = \App::makeInstance($classname);
					call_user_func([$object, 'populate'], $auto);
				}
			}
		}
	}

	private function getDeltasFromDB( &$iteration ): array
	{
		if ( !MySQLLink::tableExists('migrations') ) {
			return [];
		}

		$this->_db->activate();
		$this->_db->clear();
		$this->_db->order('iteration', 'DESC')
			->order('migration_id', 'DESC')
			->read();

		return $this->migrationHistory($this->_db->result()->toArray(), $iteration);
	}

	protected function migrationHistory(array $rows, &$iteration): array
	{
		$records = new Collection($rows);
		$iterations = $records
			->map(fn($row) => (int)$this->migrationField($row, 'iteration', 0))
			->toArray();

		if (Arr::isNotEmpty($iterations)) {
			$iteration = Arr::max($iterations);
		}

		$names = $records
			->filter(fn($row) => (int)$this->migrationField($row, 'status', 0) === 2)
			->map(fn($row) => $this->migrationField($row, 'name'))
			->filter(fn($name) => Val::isNotEmpty($name))
			->toArray();

		return Arr::make($names)
			->unique()
			->values()
			->toArray();
	}

	private function migrationField(mixed $row, string $field, mixed $default = null): mixed
	{
		if (Arr::is($row)) {
			return Arr::getPath($row, $field, $default);
		}

		if ($row instanceof \ArrayAccess && $row->offsetExists($field)) {
			return $row[$field];
		}

		if (is_object($row)) {
			return $row->{$field} ?? $default;
		}

		return $default;
	}

	private function loadGenerators()
	{
		return scandir($this->_generatorDir);
	}

	private function loadDeltas( $reverse = SCANDIR_SORT_ASCENDING )
	{
		return scandir($this->_deltaDir, $reverse);
	}

	protected function findClassName($file): ?string
	{
		if (!(new File())->isReachable($file)) {
			return null;
		}

		$tokens = token_get_all(File::readContents($file));
		$namespace = '';
		$previousToken = null;

		foreach ($tokens as $index => $token) {
			if (!Arr::is($token)) {
				continue;
			}

			$tokenType = Arr::getPath($token, '0');
			if ($tokenType === T_NAMESPACE) {
				$namespace = $this->namespaceFromTokens($tokens, $index + 1);
			}

			if ($tokenType === T_CLASS && !Arr::has([T_NEW, T_DOUBLE_COLON], $previousToken, true)) {
				$class = $this->classFromTokens($tokens, $index + 1);
				if (Val::isNotEmpty($class)) {
					return Str::trim(
						Val::isEmpty($namespace) ? $class : "{$namespace}\\{$class}",
						'\\'
					);
				}
			}

			if (!Arr::has([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], $tokenType, true)) {
				$previousToken = $tokenType;
			}
		}

		return null;
	}

	private function namespaceFromTokens(array $tokens, int $offset): string
	{
		$namespace = '';
		for ($index = $offset, $size = Arr::size($tokens); $index < $size; $index++) {
			$token = $tokens[$index];
			if ($token === ';' || $token === '{') {
				break;
			}

			if (Arr::is($token) && Arr::has([T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], $token[0], true)) {
				$namespace .= $token[1];
			}
		}

		return Str::trim($namespace, '\\');
	}

	private function classFromTokens(array $tokens, int $offset): ?string
	{
		for ($index = $offset, $size = Arr::size($tokens); $index < $size; $index++) {
			$token = $tokens[$index];
			if ($token === '{') {
				break;
			}

			if (Arr::is($token) && $token[0] === T_STRING) {
				return $token[1];
			}
		}

		return null;
	}
}
