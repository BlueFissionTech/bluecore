<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;
use BlueFission\BlueCore\Hooks\DispatchesLifecycleHooks;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\Collections\Collection;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Data\Storage\Storage;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Func;
use BlueFission\Str;
use BlueFission\Val;

class DatasourceManager extends Service {
	use DispatchesLifecycleHooks;

	public const FILTER_MIGRATION_PLAN = 'bluecore.datasource.migration.plan';
	public const HOOK_MIGRATION_BEFORE = 'bluecore.datasource.migration.before';
	public const HOOK_MIGRATION_AFTER = 'bluecore.datasource.migration.after';
	public const HOOK_MIGRATION_FAILED = 'bluecore.datasource.migration.failed';
	public const FILTER_POPULATION_PLAN = 'bluecore.datasource.population.plan';
	public const HOOK_POPULATION_BEFORE = 'bluecore.datasource.population.before';
	public const HOOK_POPULATION_AFTER = 'bluecore.datasource.population.after';
	public const HOOK_POPULATION_FAILED = 'bluecore.datasource.population.failed';

	protected $_deltaDir = '';
	protected $_generatorDir = '';
	protected $_db = null;

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

	public function runMigrations($batch = null): array
	{
		$batch = Val::isEmpty($batch) ? 'opus' : Str::trim((string)$batch);
		$result = Arr::make([
			'ok' => true,
			'batch' => $batch,
			'changed' => false,
			'stage' => 'discovery',
			'total' => 0,
			'applied' => 0,
			'failed' => 0,
			'results' => [],
			'nextAction' => null,
			'error' => null,
		]);

		if (Flag::isFalse($this->deltaDirectoryExists())) {
			$result->set('stage', 'complete');

			return $this->finalizeDatasourceResult(
				$result,
				'migration',
				self::HOOK_MIGRATION_AFTER,
				self::HOOK_MIGRATION_FAILED
			);
		}

		$deltas = $this->loadDeltas();
		$iteration = 0;
		$storedDeltas = $this->getDeltasFromDB($iteration, $batch);
		$iteration++;

		$deltas = Arr::make($deltas)
			->diff($storedDeltas)
			->filter(fn ($delta) => Str::pos((string)$delta, '.') !== 0)
			->values()
			->toArray();
		$plan = Arr::make([
			'operation' => 'migration',
			'batch' => $batch,
			'iteration' => $iteration,
			'deltas' => $deltas,
		]);

		try {
			$plan = $this->filteredDatasourcePlan(
				self::FILTER_MIGRATION_PLAN,
				$plan,
				'deltas'
			);
		} catch (\Throwable $exception) {
			$result->set('ok', false);
			$result->set('stage', 'planning');
			$result->set('failed', 1);
			$result->set('nextAction', 'review_migration_plan');
			$result->set('error', $exception->getMessage());

			return $this->finalizeDatasourceResult(
				$result,
				'migration',
				self::HOOK_MIGRATION_AFTER,
				self::HOOK_MIGRATION_FAILED,
				$exception
			);
		}

		$deltas = Arr::make($plan->get('deltas'))->toArray();
		$this->dispatchLifecycleAction(self::HOOK_MIGRATION_BEFORE, [$plan, $this]);

		$result->set('total', Arr::size($deltas));
		$result->set('stage', 'migration');
		$dbActive = $this->migrationTableExists();
		$migrations = Arr::make([]);
		$failureException = null;

		foreach ($deltas as $delta) {
			$migration = Arr::make([
				'ok' => false,
				'name' => $delta,
				'batch' => $batch,
				'iteration' => $iteration,
				'status' => 'pending',
				'error' => null,
				'exception' => null,
			]);
			$classname = $this->findClassName($this->_deltaDir . $delta);

			if (Val::isEmpty($classname)) {
				$failureException = new \RuntimeException('Migration class could not be resolved.');
				$migration->set('status', 'missing_class');
				$migration->set('error', $failureException->getMessage());
				$migrations->push($migration->toArray());
				break;
			}

			$this->includeMigration($this->_deltaDir . $delta);
			$this->_db->clear();
			$this->_db->assign([
				'name' => $delta,
				'batch' => $batch,
				'iteration' => $iteration,
				'status' => 1,
			])->write();
			$this->_db->id($this->_db->lastRow());
			$status = 2;

			try {
				$object = $this->migrationInstance($classname);
				Func::make([$object, 'change'])->call();
				$migration->set('ok', true);
				$migration->set('status', 'applied');
			} catch (\Throwable $exception) {
				$failureException = $exception;
				$status = 3;
				$migration->set('status', 'failed');
				$migration->set('error', $exception->getMessage());
				$migration->set('exception', $exception::class);
			}

			if (Flag::isFalse($dbActive)) {
				$this->_db->activate();
				$dbActive = true;
			}

			$this->_db->assign([
				'name' => $delta,
				'batch' => $batch,
				'iteration' => $iteration,
				'status' => $status,
			])->write();
			$migrations->push($migration->toArray());

			if (Flag::isFalse($migration->get('ok'))) {
				break;
			}
		}

		$failures = $migrations
			->filter(fn ($migration) => Flag::isFalse(Arr::getPath($migration, 'ok', false)))
			->values();
		$applied = $migrations
			->filter(fn ($migration) => Flag::parseBool(Arr::getPath($migration, 'ok', false)))
			->values();
		$result->set('results', $migrations->toArray());
		$result->set('applied', $applied->count());
		$result->set('failed', $failures->count());
		$result->set('changed', Arr::isNotEmpty($applied->toArray()));
		$result->set('ok', Arr::isEmpty($failures->toArray()));
		$result->set('stage', Arr::isEmpty($failures->toArray()) ? 'complete' : 'migration');

		if (Arr::isNotEmpty($failures->toArray())) {
			$failure = Arr::getPath($failures->values()->toArray(), '0', []);
			$result->set('nextAction', 'retry_migrations');
			$result->set('error', Arr::getPath($failure, 'error', 'Migration failed.'));
		}

		return $this->finalizeDatasourceResult(
			$result,
			'migration',
			self::HOOK_MIGRATION_AFTER,
			self::HOOK_MIGRATION_FAILED,
			$failureException
		);
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

	public function populate($auto = false): array
	{
		$result = Arr::make([
			'ok' => true,
			'changed' => false,
			'stage' => 'discovery',
			'total' => 0,
			'populated' => 0,
			'failed' => 0,
			'results' => [],
			'nextAction' => null,
			'error' => null,
		]);

		if (Flag::isFalse($this->generatorDirectoryExists())) {
			$result->set('stage', 'complete');

			return $this->finalizeDatasourceResult(
				$result,
				'population',
				self::HOOK_POPULATION_AFTER,
				self::HOOK_POPULATION_FAILED
			);
		}

		try {
			$generators = $this->orderedGenerators($this->loadGenerators());
		} catch (\Throwable $exception) {
			return $this->finalizeDatasourceResult(
				Arr::make($this->failedPopulationResult($result, 'RootSeeder.php', $exception)),
				'population',
				self::HOOK_POPULATION_AFTER,
				self::HOOK_POPULATION_FAILED,
				$exception
			);
		}
		$plan = Arr::make([
			'operation' => 'population',
			'auto' => Flag::parseBool($auto),
			'generators' => $generators,
		]);

		try {
			$plan = $this->filteredDatasourcePlan(
				self::FILTER_POPULATION_PLAN,
				$plan,
				'generators'
			);
		} catch (\Throwable $exception) {
			$result->set('ok', false);
			$result->set('stage', 'planning');
			$result->set('failed', 1);
			$result->set('nextAction', 'review_population_plan');
			$result->set('error', $exception->getMessage());
			$result->set('results', []);

			return $this->finalizeDatasourceResult(
				$result,
				'population',
				self::HOOK_POPULATION_AFTER,
				self::HOOK_POPULATION_FAILED,
				$exception
			);
		}

		$generators = Arr::make($plan->get('generators'))->toArray();
		$this->dispatchLifecycleAction(self::HOOK_POPULATION_BEFORE, [$plan, $this]);

		$result->set('total', Arr::size($generators));
		$result->set('stage', 'population');
		$outcomes = Arr::make([]);
		$failureException = null;

		foreach ($generators as $generator) {
			$outcome = Arr::make([
				'ok' => false,
				'name' => $generator,
				'status' => 'pending',
				'error' => null,
				'exception' => null,
			]);

			try {
				$classname = $this->findClassName($this->_generatorDir . $generator);
				if (Val::isEmpty($classname)) {
					throw new \RuntimeException('Population class could not be resolved.');
				}

				$this->includeGenerator($this->_generatorDir . $generator);
				$object = $this->generatorInstance($classname);
				if (Flag::isFalse(Func::isCallable([$object, 'populate']))) {
					throw new \RuntimeException('Population class must expose populate().');
				}

				Func::make([$object, 'populate'])->call($auto);
				$outcome->set('ok', true);
				$outcome->set('status', 'populated');
			} catch (\Throwable $exception) {
				$failureException = $exception;
				$outcome->set('status', 'failed');
				$outcome->set('error', $exception->getMessage());
				$outcome->set('exception', $exception::class);
			}

			$outcomes->push($outcome->toArray());
			if (Flag::isFalse($outcome->get('ok'))) {
				break;
			}
		}

		$failures = $outcomes
			->filter(fn ($outcome) => Flag::isFalse(Arr::getPath($outcome, 'ok', false)))
			->values();
		$populated = $outcomes
			->filter(fn ($outcome) => Flag::parseBool(Arr::getPath($outcome, 'ok', false)))
			->values();
		$result->set('results', $outcomes->toArray());
		$result->set('populated', $populated->count());
		$result->set('failed', $failures->count());
		$result->set('changed', Arr::isNotEmpty($populated->toArray()));
		$result->set('ok', Arr::isEmpty($failures->toArray()));
		$result->set('stage', Arr::isEmpty($failures->toArray()) ? 'complete' : 'population');

		if (Arr::isNotEmpty($failures->toArray())) {
			$failure = Arr::getPath($failures->toArray(), '0', []);
			$result->set('nextAction', 'review_population_failure');
			$result->set('error', Arr::getPath($failure, 'error', 'Datasource population failed.'));
		}

		return $this->finalizeDatasourceResult(
			$result,
			'population',
			self::HOOK_POPULATION_AFTER,
			self::HOOK_POPULATION_FAILED,
			$failureException
		);
	}

	protected function orderedGenerators(array $generators): array
	{
		$generators = Arr::make($generators)
			->filter(fn ($generator) => Str::pos((string)$generator, '.') !== 0)
			->map(fn ($generator) => Str::endsWith((string)$generator, '.php') ? $generator : $generator . '.php')
			->values()
			->toArray();

		if (Flag::isFalse(Arr::has($generators, 'RootSeeder.php', true))) {
			return $generators;
		}

		$classname = $this->findClassName($this->_generatorDir . 'RootSeeder.php');
		if (Val::isEmpty($classname)) {
			throw new \RuntimeException('Root seeder class could not be resolved.');
		}

		$this->includeGenerator($this->_generatorDir . 'RootSeeder.php');
		$object = $this->generatorInstance($classname);
		if (Flag::isFalse(Func::isCallable([$object, 'seeders']))) {
			return $generators;
		}

		return Arr::make(Func::make([$object, 'seeders'])->call())
			->map(fn ($generator) => Str::endsWith((string)$generator, '.php') ? $generator : $generator . '.php')
			->values()
			->toArray();
	}

	private function filteredDatasourcePlan(string $hook, Arr $plan, string $itemsKey): Arr
	{
		$original = $plan->toArray();
		$filtered = $this->applyLifecycleFilter($hook, $plan);

		if (!$filtered instanceof Arr) {
			throw new \UnexpectedValueException("{$hook} must return a DevElation Arr.");
		}

		foreach ($original as $key => $value) {
			if ($key !== $itemsKey && $filtered->get($key) !== $value) {
				throw new \UnexpectedValueException("{$hook} cannot change {$key}.");
			}
		}

		$items = $filtered->get($itemsKey);
		if (!Arr::is($items)) {
			throw new \UnexpectedValueException("{$hook} must preserve {$itemsKey} as an array.");
		}

		$items = Arr::make($items)->values()->toArray();
		if (Arr::make($items)->unique()->values()->toArray() !== $items) {
			throw new \UnexpectedValueException("{$hook} cannot duplicate planned entries.");
		}

		$available = Arr::make(Arr::getPath($original, $itemsKey, []))->values()->toArray();
		foreach ($items as $item) {
			if (Flag::isFalse(Str::is($item)) || Flag::isFalse(Arr::has($available, $item, true))) {
				throw new \UnexpectedValueException(
					"{$hook} can only reorder or remove discovered entries."
				);
			}
		}

		$normalized = Arr::make($filtered->toArray());
		$normalized->set($itemsKey, $items);

		return $normalized;
	}

	private function finalizeDatasourceResult(
		Arr $result,
		string $operation,
		string $afterHook,
		string $failedHook,
		?\Throwable $exception = null
	): array {
		if (Flag::parseBool($result->get('ok'))) {
			$this->dispatchLifecycleAction($afterHook, [Arr::make($result->toArray()), $this]);
		} else {
			$exception ??= new \RuntimeException(
				(string)(Val::isEmpty($result->get('error'))
					? 'Datasource operation failed.'
					: $result->get('error'))
			);
			$this->dispatchLifecycleAction($failedHook, [
				new LifecycleFailure($failedHook, $operation, static::class, $exception),
			]);
		}

		return $result->toArray();
	}

	private function failedPopulationResult(Arr $result, string $generator, \Throwable $exception): array
	{
		$result->set('ok', false);
		$result->set('stage', 'population');
		$result->set('total', 1);
		$result->set('failed', 1);
		$result->set('nextAction', 'review_population_failure');
		$result->set('error', $exception->getMessage());
		$result->set('results', [[
			'ok' => false,
			'name' => $generator,
			'status' => 'failed',
			'error' => $exception->getMessage(),
			'exception' => $exception::class,
		]]);

		return $result->toArray();
	}

	protected function getDeltasFromDB(&$iteration, ?string $batch = null): array
	{
		if ( !MySQLLink::tableExists('migrations') ) {
			return [];
		}

		$this->_db->activate();
		$this->_db->clear();
		if (Val::isNotEmpty($batch)) {
			$this->_db->where('batch', $batch);
		}
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

	protected function loadGenerators(): array
	{
		return scandir($this->_generatorDir);
	}

	protected function generatorDirectoryExists(): bool
	{
		return Flag::parseBool(Arr::getPath(Path::readiness($this->_generatorDir), 'exists', false));
	}

	protected function includeGenerator(string $path): void
	{
		include_once($path);
	}

	protected function generatorInstance(string $classname): object
	{
		return \App::makeInstance($classname);
	}

	protected function loadDeltas( $reverse = SCANDIR_SORT_ASCENDING )
	{
		return scandir($this->_deltaDir, $reverse);
	}

	protected function deltaDirectoryExists(): bool
	{
		return Flag::parseBool(Arr::getPath(Path::readiness($this->_deltaDir), 'exists', false));
	}

	protected function migrationTableExists(): bool
	{
		return MySQLLink::tableExists('migrations');
	}

	protected function includeMigration(string $path): void
	{
		include_once($path);
	}

	protected function migrationInstance(string $classname): object
	{
		return \App::makeInstance($classname);
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
