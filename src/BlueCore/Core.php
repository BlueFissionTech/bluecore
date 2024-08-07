<?php

namespace BlueFission\BlueCore;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Wise\Arc\ProcessManager;
use BlueFission\Wise\Sys\{
	MemoryManager,
	FileSystemManager,
	DisplayManager,
	KeyInputManager,
	Drivers\ConsoleDisplayDriver,
	Utl\ConsoleDisplayUtil,
	Utl\KeyInputUtil
};
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Wise\Cli\Console;
use BlueFission\Wise\Cli\Components;
use BlueFission\Async\{Heap, Thread, Fork};
use BlueFission\Data\Storage\{Disk, Memory, SQLite};
use BlueFission\Automata\Language\{
	Interpreter,
	Grammar,
	StemmerLemmatizer,
	Documenter,
	Walker
};
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Wise\Sys\Conn\ExtendedStdio;// as Stdio;
use BlueFission\IPC\IPC;
use BlueFission\Data\Queues\MemQueue;

class Core extends Service {
	private $_kernel;
	private $_console;

	public function __construct() {
		$this->init();
		parent::__construct();
	}

	public function init(): void
	{
		mb_internal_encoding("UTF-8");

		MemQueue::setMode(MemQueue::FIFO);

		// Handle IO
		$stdio = (new ExtendedStdio('php stdin.php', 'php polling.php'))->open();
		ConsoleDisplayUtil::init($stdio);
		KeyInputUtil::init($stdio);

		// Prepare the Console
		$splash = new Components\SplashScreen();

		$repl = new Components\REPL($splash);

		$screen = new Components\Screen();
		$screen->addChild($repl);

		$this->_console = new Console(
			new DisplayManager( new ConsoleDisplayDriver ),
			new KeyInputManager()
		);
		$this->_console->addComponent($screen);
		$this->_console->setDisplayMode(Console::STATIC_MODE);
		// $this->_console->setDisplayMode(Console::DYNAMIC_MODE);

		$grammarRules = [];

		// Create and initialize the kernel
		$this->_kernel = new Kernel(
			new ProcessManager(),
			new CommandProcessor( new Disk() ),
			new MemoryManager(300, 60),  // MemoryManager with 300 seconds threshold and 60 seconds monitoring interval
			new FileSystemManager(['root'=>getcwd()]),
			new Interpreter( new Grammar( new StemmerLemmatizer(), $grammarRules ), new Documenter(), new Walker() ),
			$this->_console, // Our console object we previously setup
			new Disk(['location'=>'../', 'name'=>'storage.json']),
			new SQLite(['database'=>'database.db']),
			new IPC(new Memory())
		);

		// Set the Async handler to an appropriate driver
		$this->_kernel->setAsyncHandler( function_exists('pcntl_fork') ? Fork::class : Thread::class );
		$this->_kernel->setQueueHandler( MemQueue::class );
		
		// Boot the kernel
		$this->_kernel->boot();
	}

	public function run():void
	{
		$this->_kernel->run();
	}

	public function handle($input): mixed
	{
		$this->_kernel->handle($input);
		return $this->_kernel->output();
	}
}
