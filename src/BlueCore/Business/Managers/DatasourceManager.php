<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;

class DatasourceManager extends Service {

	private $_deltaDir = OPUS_ROOT.'/datasources/structure/';
	private $_generatorDir = OPUS_ROOT.'/datasources/generator/';
	private $_db = null;

	public function __construct( MySQLLink $link, Storage $storage )
    {
		parent::__construct();
		$link->open();
		$this->_db = $storage;
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
		$deltas = $this->loadDeltas();
		$this->_db->config('name', 'migrations');
					
		$this->_db->clear();
		$this->_db->order('iteration', 'DESC')
			->read();

		$iteration = $this->_db->result()->first()->iteration ?: 1;
		$deltasToIgnore = $this->_db->result()->map(function($row) {
			return $row->delta;
		})->toArray();

		$deltas = array_diff($deltas, $deltasToIgnore);

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
		$this->_db->config('name', 'migrations');
		$this->_db->clear();
		$this->_db->order('iteration', 'DESC')
			->order('migration_id', 'DESC')
			->read();

		$iteration = $this->_db->result()->first()->iteration ?: 1;
		$batch = $this->_db->result()->map(function($row) use ($iteration) {
			if ( $row->iteration != $iteration ) {
				return null;
			}
			return $row->delta;
		})
		->filter(function($delta) {
			return $delta !== null;
		});

		$deltas = $batch->count() > 0 ? $batch->toArray() : $$this->loadDeltas(1);
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

	public function populate()
	{
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
					call_user_func([$object, 'populate']);
				}
			}
		}
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