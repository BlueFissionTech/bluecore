<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;
use BlueFission\Collections\Collection;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Data\Storage\Storage;
use BlueFission\Arr;
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
		if ( in_array('RootSeeder.php', $generators) ) {
			$classname = $this->findClassName( $this->_generatorDir . 'RootSeeder.php' );
			if ( $classname ) {
				include_once($this->_generatorDir . 'RootSeeder.php');
				$object = \App::makeInstance($classname);
				if ( method_exists($object, 'seeders') ) {
					$generators = call_user_func([$object, 'seeders']);
					array_walk($generators, function(&$generator) {
						$generator = $generator.'.php';
					});
				}
			}
		}

		foreach ( $generators as $generator ) {
			$classname = '';

			if ( strpos($generator, '.') !== 0 ) {
				$generator = strpos($generator, '.php') ? $generator : $generator . '.php';
				
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

	// https://stackoverflow.com/questions/7153000/get-class-name-from-file
	private function findClassName( $file )
	{
		$fp = fopen($file, 'r');
		$class = $namespace = $buffer = '';
		$i = 0;
		while (!$class) {
		    if (feof($fp)) break;

		    $buffer .= fread($fp, 512);
		    $tokens = token_get_all($buffer);

		    if (strpos($buffer, '{') === false) continue;

		    for (;$i<count($tokens);$i++) {
		        if ($tokens[$i][0] === T_NAMESPACE) {
		            for ($j=$i+1;$j<count($tokens); $j++) {
		                if ($tokens[$j][0] === T_STRING) {
		                     $namespace .= '\\'.$tokens[$j][1];
		                } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
		                     break;
		                }
		            }
		        }

		        if ($tokens[$i][0] === T_CLASS) {
		            for ($j=$i+1;$j<count($tokens);$j++) {
		                if ($tokens[$j] === '{') {
		                    $class = $tokens[$i+2][1];
		                }
		            }
		        }
		    }
		}

		return $class;
	}
}
