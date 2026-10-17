<?php

namespace BlueFission\Tests\BlueCore\Model;

use BlueFission\BlueCore\Model\ModelSQLite;
use BlueFission\Data\Storage\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Characterizes the legacy boundary; these are not shared-context acceptance tests. */
class SQLiteContextBoundaryTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'bluecore-context-' . bin2hex(random_bytes(12)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->database)) {
            unlink($this->database);
        }
    }

    public static function historyFailures(): array
    {
        return [
            'ignored history' => ['IGNORE'],
            'aborted history' => ["ABORT, 'synthetic history failure'"],
        ];
    }

    #[DataProvider('historyFailures')]
    public function testHistoryFailureSurvivesReopenAndSameValueShortCircuit(string $failure): void
    {
        $record = new ContextBoundaryRecord($this->database);
        $record->write(['state' => 'draft']);
        $id = $record->id();
        $db = $this->open();
        $db->exec('CREATE TABLE context_history (id INTEGER PRIMARY KEY, state TEXT, created TEXT, updated TEXT)');
        // The failure clause comes only from the fixed test provider, not caller input.
        $db->exec("CREATE TRIGGER deny_history BEFORE INSERT ON context_history BEGIN SELECT RAISE($failure); END");
        $db->close();

        $history = new ContextBoundaryHistory($this->database);
        try {
            $this->changeState($id, 'active', $history);
            $this->fail('A failed history insertion must not report success.');
        } catch (\Exception $error) {
            $expected = $failure === 'IGNORE' ? 'exactly one row' : 'synthetic history failure';
            $this->assertStringContainsString($expected, $error->getMessage());
        }
        $this->assertSame(Storage::STATUS_FAILED, $history->status());
        $this->assertNull($history->id());

        $reopened = $this->open();
        $this->assertSame('active', $reopened->querySingle('SELECT state FROM context_records'));
        $this->assertSame(0, $reopened->querySingle('SELECT count(*) FROM context_history'));
        $reopened->exec('DROP TRIGGER deny_history');
        $reopened->close();

        // A representative host optimization cannot infer history completeness from state equality.
        $this->assertFalse($this->changeState($id, 'active', new ContextBoundaryHistory($this->database)));
        $afterRetry = $this->open();
        $this->assertSame('active', $afterRetry->querySingle('SELECT state FROM context_records'));
        $this->assertSame(0, $afterRetry->querySingle('SELECT count(*) FROM context_history'));
        $afterRetry->close();
    }

    public function testSeparatePdoRollbackDoesNotRollBackModelWrite(): void
    {
        if (!class_exists(\PDO::class) || !in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Optional PDO SQLite driver is unavailable.');
        }

        $record = new ContextBoundaryRecord($this->database);
        $record->write(['state' => 'draft']);
        $db = $this->open();
        $db->exec('CREATE TABLE context_relations (id INTEGER PRIMARY KEY)');
        $db->exec("CREATE TRIGGER deny_relation BEFORE INSERT ON context_relations BEGIN SELECT RAISE(ABORT, 'synthetic relation failure'); END");
        $db->close();

        $relation = new \PDO('sqlite:' . $this->database);
        $relation->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $relation->beginTransaction();
        try {
            $record->write(['id' => $record->id(), 'state' => 'active']);
            $relation->exec('INSERT INTO context_relations(id) VALUES (1)');
            $this->fail('The synthetic relation insertion must fail.');
        } catch (\PDOException $error) {
            $this->assertStringContainsString('synthetic relation failure', $error->getMessage());
        } finally {
            if ($relation->inTransaction()) {
                $relation->rollBack();
            }
            $relation = null;
        }

        $reopened = $this->open();
        $this->assertSame('active', $reopened->querySingle('SELECT state FROM context_records'));
        $this->assertSame(0, $reopened->querySingle('SELECT count(*) FROM context_relations'));
        $reopened->close();
    }

    public function testAffectedWriteSuccessDoesNotCertifyStoredFieldValue(): void
    {
        $record = new ContextBoundaryRecord($this->database);
        $record->write(['state' => 'draft']);
        $db = $this->open();
        $db->exec("CREATE TRIGGER rewrite_state AFTER UPDATE ON context_records WHEN NEW.state = 'requested' BEGIN UPDATE context_records SET state = 'rewritten' WHERE id = NEW.id; END");
        $db->close();

        $record->write(['id' => $record->id(), 'state' => 'requested']);
        $this->assertSame(Storage::STATUS_SUCCESS, $record->status());
        $this->assertSame('requested', $record->field('state'));
        $reopened = $this->open();
        $this->assertSame('rewritten', $reopened->querySingle('SELECT state FROM context_records'));
        $reopened->close();
    }

    private function changeState(mixed $id, string $state, ContextBoundaryHistory $history): bool
    {
        $record = new ContextBoundaryRecord($this->database);
        $record->read(['id' => $id]);
        if ($record->field('state') === $state) {
            return false;
        }

        $record->write(['id' => $id, 'state' => $state]);
        $history->write(['state' => $state]);
        return true;
    }

    private function open(): \SQLite3
    {
        $db = new \SQLite3($this->database);
        $db->enableExceptions(true);
        return $db;
    }
}

class ContextBoundaryRecord extends ModelSQLite
{
    protected $_table = 'context_records';
    protected $_fields = ['id', 'state'];
}

class ContextBoundaryHistory extends ModelSQLite
{
    protected $_table = 'context_history';
    protected $_fields = ['id', 'state'];
}
