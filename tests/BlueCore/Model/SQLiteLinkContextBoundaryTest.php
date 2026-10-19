<?php

namespace BlueFission\Tests\BlueCore\Model;

use BlueFission\Connections\Database\SQLiteLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Installed-link characterizations, not an implementation of a shared model context. */
class SQLiteLinkContextBoundaryTest extends TestCase
{
    private string $database;
    private array $links = [];

    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'bluecore-link-context-' . bin2hex(random_bytes(12)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ($this->links as $link) {
            $link->close();
        }
        if (is_file($this->database)) {
            unlink($this->database);
        }
    }

    public function testDefaultOpenCreatesMissingFileAndAllowsWrites(): void
    {
        $this->assertFileDoesNotExist($this->database);
        $link = $this->open();
        $this->assertFileExists($this->database);
        $link->query('CREATE TABLE context_probe (id INTEGER PRIMARY KEY)');
        $link->query('INSERT INTO context_probe VALUES (1)');
        $this->assertSame(1, $link->connection()->querySingle('SELECT count(*) FROM context_probe'));
        // No noncreating/read-only guarantee can be inferred from default open().
    }

    public static function transactionOutcomes(): array
    {
        return ['rollback' => ['ROLLBACK', 0], 'commit' => ['COMMIT', 1]];
    }

    #[DataProvider('transactionOutcomes')]
    public function testPooledLinksShareNativeTransactionButNotExternalLifetime(string $outcome, int $rows): void
    {
        $owner = $this->open();
        $peer = $this->open();
        $handle = $owner->connection();
        $this->assertSame($handle, $peer->connection());
        $handle->exec('CREATE TABLE context_record (id INTEGER PRIMARY KEY)');
        $handle->exec('CREATE TABLE context_history (id INTEGER PRIMARY KEY)');

        // Native calls are disposable-fixture controls, not a proposed production adapter.
        $handle->exec('BEGIN IMMEDIATE');
        $owner->query('INSERT INTO context_record VALUES (1)');
        $peer->query('INSERT INTO context_history VALUES (1)');
        $owner->close();
        $this->assertSame(1, $handle->querySingle('SELECT count(*) FROM context_record'));
        $this->assertSame(1, $handle->querySingle('SELECT count(*) FROM context_history'));
        $handle->exec($outcome);
        $peer->close();

        // Retaining connection() is not a borrowed-lifetime contract: last link exit closes it.
        try {
            $handle->querySingle('SELECT 1');
            $this->fail('The installed last-link close unexpectedly preserved the raw handle.');
        } catch (\Error $error) {
            $this->assertStringContainsString('not been correctly initialised', $error->getMessage());
        }

        $reopened = new \SQLite3($this->database, SQLITE3_OPEN_READONLY);
        try {
            $this->assertSame($rows, $reopened->querySingle('SELECT count(*) FROM context_record'));
            $this->assertSame($rows, $reopened->querySingle('SELECT count(*) FROM context_history'));
        } finally {
            $reopened->close();
        }
    }

    public function testIgnoredAndAbortedStatementsNeedDifferentFailureEvidence(): void
    {
        $link = $this->open();
        $link->query('CREATE TABLE context_probe (id INTEGER PRIMARY KEY)');
        $link->query('CREATE TRIGGER ignore_probe BEFORE INSERT ON context_probe BEGIN SELECT RAISE(IGNORE); END');
        $this->assertSame($link, $link->query('INSERT INTO context_probe VALUES (1)'));
        $this->assertSame(SQLiteLink::STATUS_SUCCESS, $link->status());
        $this->assertSame(0, $link->connection()->changes());
        $this->assertSame(0, $link->connection()->querySingle('SELECT count(*) FROM context_probe'));

        $link->query('DROP TRIGGER ignore_probe');
        $link->query("CREATE TRIGGER abort_probe BEFORE INSERT ON context_probe BEGIN SELECT RAISE(ABORT, 'synthetic context failure'); END");
        $this->assertSame($link, $link->query('INSERT INTO context_probe VALUES (1)'));
        $this->assertFalse($link->result());
        $this->assertStringContainsString('synthetic context failure', $link->status());
        $this->assertSame(0, $link->connection()->querySingle('SELECT count(*) FROM context_probe'));
    }

    private function open(): SQLiteLink
    {
        $link = new SQLiteLink(['database' => $this->database]);
        $this->links[] = $link;
        $link->open();
        $this->assertInstanceOf(\SQLite3::class, $link->connection());
        return $link;
    }
}
