<?php
namespace BlueFission\BlueCore\Command;


use BlueFission\Automata\Language\Grammar;
use BlueFission\Automata\Language\SyntaxTreeWalker;
use BlueFission\Automata\Language\EntityExtractor;
use BlueFission\Data\Storage\Storage;
use BlueFission\Automata\LLM\Prompts\ConsoleResponse;
use BlueFission\Automata\LLM\Clients\IClient;

class CommandProcessor
{
    protected $_parser;
    protected $_app;
    protected $_storage;
    protected $_grammar;
    protected $_llmClient;

    protected $_availableCommands = [];

    protected $_responseWords = [
        'yes' => ['y', 'yes', 'yeah', 'yep', 'ya', 'right', 'true', 'uh huh', 'sure', 'ok', 'okay', 'affirmative'],
        'no' => ['n', 'no', 'nope', 'nah', 'wrong', 'false', 'negative'],
    ];

    protected $_keywords = [];

    public function __construct(Storage $storage, IClient $llmClient)
    {
        $this->setKeywords();
        $this->_parser = new CommandParser();
        $this->_app = \App::instance();
        $this->_storage = $storage;
        $this->_storage->activate();

        $this->_llmClient = $llmClient;

        $this->_grammar = \App::instance()->getDynamicInstance(Grammar::class);

        $this->_storage->read();
        // Instantiate the storage object values
        $this->_storage->history = $this->_storage->history ?? [];
        $this->_storage->log = $this->_storage->log ?? [];
        $this->_storage->errors = $this->_storage->errors ?? 0;
        $this->_storage->warnings = $this->_storage->warnings ?? 0;
        $this->_storage->crashes = $this->_storage->crashes ?? 0;
        $this->_storage->write();
        $this->_availableCommands = $this->populateAvailableCommands();
    }

    public function availableCommands()
    {
        return $this->_availableCommands;
    }

    public function process($input)
    {
        $this->addToLog($input, 'input');
        if (empty($input)) {
            $output = "No command entered.";
            $this->addToLog($output, 'output');
            return $output;
        }


        $input = trim($input);

        if (!empty($this->_storage->confirmCmd)) {
            if (in_array(strtolower($input), $this->responseWords['yes'])) {
                // echo 1;
                // var_dump($this->_storage->confirmCmd);
                $command = $this->convertToCommand($this->_storage->confirmCmd);
                // echo 2;
                // var_dump($this->_storage->confirmCmd);
                unset($this->_storage->currentCmd);

                // echo 3;
                // var_dump($command);

                $accept = new Command();
                $accept->args[] = $input;

                $this->addToHistory($accept);

                $input = "";
            } elseif (in_array(strtolower($input), $this->responseWords['no'])) {
                $deny = new Command();
                $deny->args[] = $input;

                $this->addToHistory($deny);
                
                $output = "Command cancelled.";
                $this->addToLog($output, 'output');
                return $output;
            }
            unset($this->_storage->confirmCmd);
            $this->_storage->write();
        }

        if ($this->_storage->currentCmd) {

            $command = $this->convertToCommand($this->_storage->currentCmd);
            $newCommand = $this->_parser->parse($input);

            // echo 4;
            // var_dump($command);

            if ($newCommand->verb == 'cancel') {
                unset($this->_storage->currentCmd);
                $this->_storage->write();
                $output = "Command cancelled.";
                $this->addToLog($output, 'output');
                return $output;
            }

            if ($newCommand->verb && !empty($newCommand->resources)) {
                $command = $newCommand;
            }

            // echo 5;
            // var_dump($command);

            if (empty($command->resources)) {
                if (empty($newCommand->resources)) {
                    $command->resources[] = $input;
                } else {
                    $command->resources = $newCommand->resources;
                }

            } else if (empty($command->verb)) {
                if (empty($newCommand->verb)) {
                    $command->verb = $input;
                } else {
                    $command->verb = $newCommand->verb;
                }
            }

            // echo 6;
            // var_dump($command);

            unset($this->_storage->currentCmd);
        } elseif (!isset($command)) {
            $context = [
                'app' => 'app',
                'last_resource' => $this->_storage->lastResource,
                'last_set' => $this->_storage->lastSet,
            ];

            $inputWithPronouns = $this->_parser->processPronouns($input, $context);

            $command = $this->_parser->parse($input);

            // echo 7;
            // var_dump($command);
        }

        // echo 8;
        // var_dump($command);

        if (empty($command->resources) && $command->verb == 'help') {
            $output = "How can I help? Type `list all resources` to see what you have access to.";
            $this->addToLog($output, 'output');
            return $output;
        }

        // echo 9;
        // var_dump($command);

        if (!$command->verb && empty($command->resources)) {
            $command = $this->_parser->processQuestion($input);
        }

        // echo 10;
        // var_dump($command);

        if (!$command || (empty($command->verb) && empty($command->resources))) {
            $error = $this->suggestCommands($input, $command);
            if ((empty($command->verb) && empty($command->resources))) {
                $this->addToLog($error, 'output');
                return $error;
            }
        }

        if (empty($command->verb)) {
            $this->_storage->currentCmd = $command;
            $this->_storage->write();
            $output = "What should I do with {$command->resources[0]}? [list, search, or help]";

            $this->addToLog($output, 'output');
            return $output;
        }

        if (empty($command->resources)) {
            $this->_storage->currentCmd = $command;
            $this->_storage->write();
            $output = $this->suggestResources($command);
            $this->addToLog($output, 'output');
            return $output;
        }

        // echo 11;
        // var_dump($command);

        unset($this->_storage->currentCmd);
        $this->_storage->write();

        $output = $this->executeCommand($command);
        $this->addToLog($output, 'output');
        return $output;
    }

    protected function executeCommand(Command $command)
    {
        // Check if the same command is submitted 3 times in a row
        if ($this->isSameCommand($command, 4)) {
            unset($this->_storage->currentCmd);
            $this->_storage->confirmCmd = $command;
            $this->_storage->write();
            return "You've submitted this command over 3 times in a row. Are you sure you want to run it again? [yes/no]";
        }

        $service = $command->resources[0];
        $behavior = $command->verb;
        $data = $command->args;

        $this->_storage->lastResource = $command->resources[0];
        $this->_storage->lastVerb = $command->verb;
        $this->_storage->write();

        try {
            $result = $this->_app->command($service, $behavior, $data, function ($output) {
                return $output ?: $this->conversationalResponse("Command triggered with empty response. Perhaps it failed?");
            });

        } catch ( \Exception $e ) {
            $result = $e->getMessage();
            $this->_storage->crashes++;
            $this->_storage->write();

            // Check for crashes multiple of 5
            if ($this->_storage->crashes % 5 == 0) {
                return $this->conversationalResponse("You've had 5 crashes. Error: " . $result);
            }
        }

        // Check for warnings
        if (empty($result)) {
            $this->_storage->warnings++;
            $this->_storage->write();

            // Check for warnings multiple of 5
            if ($this->_storage->warnings % 5 == 0) {
                return $this->conversationalResponse("Your commands have produced 5 warnings. Do you need help (`list all resources`)?");
            }
        }

        return $result ?: "No response. Command failed.";
    }

    protected function convertToCommand($object)
    {
        $command = new Command();

        if (isset($object->verb)) {
            $command->verb = $object->verb;
        }

        if (isset($object->resources)) {
            $command->resources = $object->resources;
        }

        if (isset($object->args)) {
            $command->args = $object->args;
        }

        // echo "extra";
        // var_dump($command);
        // var_dump($object);
        // die();

        return $command;
    }

    protected function populateAvailableCommands()
    {
        $availableCommands = [];
        $abilities = $this->_app->getAbilities();

        foreach ($abilities as $service => $commands) {
            foreach ($commands as $command) {
                $availableCommands[] = "$command $service";
            }
        }

        return $availableCommands;
    }

    public function suggestCommands($input, &$cmd)
    {
        $bestMatch = '';
        $highestSimilarity = 0;

        foreach ($this->_availableCommands as $command) {
            $similarity = 0;
            similar_text($input, $command, $similarity);

            if ($similarity > $highestSimilarity) {
                $highestSimilarity = $similarity;
                $bestMatch = $command;
            }
        }

        if ($highestSimilarity > 75) {
            $this->_storage->confirmCmd = $bestMatch;
            unset($this->_storage->currentCmd);
            $this->_storage->write();
            $args = '';
            if ($cmd && isset($cmd->args) && is_array($cmd->args) && count($cmd->args) > 0) {
                 $args = " {$cmd->args[0]}";
            }

            $this->_storage->warnings++;
            $this->_storage->write();
            return "Did you mean '{$bestMatch}{$args}'? [yes/no]";
        }

        $error = $this->parseAsStatement($input, $cmd);

        $response = $this->respond($input);
        
        if (!$response) {
            $this->_storage->errors++;
            $this->_storage->write();

            // Check for errors multiple of 5
            if ($this->_storage->errors % 5 == 0) {
                return $this->conversationalResponse("You've had 5 errors. Have you reviewed your command list (`list all commands`)?");
            }
        }

        $response = $response ?? $error;

        $response = $response ?? $this->conversationalResponse("I did not understand your command.");

        return $response ?? "I did not understand your command.";
    }

    private function parseAsStatement($input, &$cmd)
    {
        $statement = strtolower($input);
        $tree = [];
        try {
            $tokens = $this->_grammar->tokenize($statement);
            $tree = $this->_grammar->parse($tokens);
        } catch (\Exception $e) {
            // return false;
            return $e->getMessage();
        }

        $walker = new SyntaxTreeWalker($tree);
        $results = $walker->walk();

        if (!$cmd) {
            $cmd = new Command();
        }

        $cmd->verb = $results['operator'];
        $cmd->resources = $results['objects'];

        $extractor = new EntityExtractor();
        $methods = [
            'literals',
            'name',
            'values',
            'object',
            'tags',
            'mentions',
            'number',
            'web',
            'email',
            'date',
            'time',
            'hex',
            'adverb',
            'operation'
        ];

        $entities = [];

        foreach ($methods as $method) {
            $entities[$method] = $extractor->$method($input);
            if (empty($cmd->args)) {
                foreach ($entities[$method] as $entity) {
                    $cmd->args[] = $entity;
                }
            }
        }
    }

    private function isSameCommand(Command $command, $times)
    {
        $this->addToHistory($command);
        $history = $this->_storage->history ?? [];

        $count = count($history);
        if ($count < $times) {
            return false;
        }

        // Check if the last $times commands are the same
        for ($i = $count - $times + 1; $i < $count; $i++) {
            if (serialize($this->convertToCommand($history[$i])) != serialize($this->convertToCommand($history[$i - 1]))) {
                return false;
            }
        }

        return true;
    }

    private function addToLog($text, $direction = 'input')
    {
        $log = $this->_storage->log ?? [];

        $direction = strtolower($direction) == 'input' ? 'input' : 'output';

        // Add the current command to the log
        $log[] = ['direction'=>$direction, 'text'=>$text];
        
        if (count($log) > 50) {
            array_shift($log);
        }
        $this->_storage->log = $log;
        $this->_storage->write();
    }

    private function addToHistory($command) 
    {
        $history = $this->_storage->history ?? [];

        // Add the current command to the history
        $history[] = $command;
        if (count($history) > 50) {
            array_shift($history);
        }
        $this->_storage->history = $history;
        $this->_storage->write();
    }

    private function respond($input)
    {
        $input = strtolower($input);
        $bestMatch = '';
        $highestSimilarity = 0;

        foreach ($this->_keywords as $topic) {
            foreach ($topic['keywords'] as $keyword) {
                $similarity = 0;
                similar_text($input, $keyword, $similarity);

                if ($similarity > $highestSimilarity) {
                    $highestSimilarity = $similarity;
                    $bestMatch = $topic;
                }
            }
        }

        if ($highestSimilarity > 50) {
            $responseIndex = array_rand($bestMatch['responses']);
            return $bestMatch['responses'][$responseIndex];
        }

        return null;
    }

    private function suggestResources($command)
    {
        switch( $command->verb ) {
            case 'find':
            case 'search':
                return 'No registered resource specified in the command. Which resource do you want to search? [files, web, or variables]';
            case 'list':
                return 'No registered resource specified in the command. Which resource do you want to list? [files, todos, or variables]';
            default:
                return "No registered resource specified in the command. Which resource do you want to {$command->verb} using?";
        }
    }

    private function conversationalResponse( $input )
    {
        $log = $this->_storage->log ?? [];
        $history = "";
        foreach ($log as $line) {
            if ( is_array($line) ) {
                if (isset($line['direction']) && $line['direction'] == 'input') {
                    $history .= "[Console User]: ".$line['text']."\n";
                } else {
                    $history .= "[System]:  ".$line['text']."\n";
                }
            }
        }
        $verbs = implode(', ', $this->_parser->getSystemVerbs());
        $resources = implode(', ', $this->_parser->getSystemResources());
        $prepositions = implode(', ', $this->_parser->getSystemPrepositions());

        // Generate the code using an LLM
        $prompt = (new ConsoleResponse($input, $history, $verbs, $resources, $prepositions))->prompt();
        $llmResponse = $this->_llmClient->complete($prompt, ['max_tokens'=>150, 'stop'=>["\n"]]);

        // Check for errors in the response
        if (!$llmResponse->success()) {
            $output = "{$input} Additionally, there was an error generating a suggestion.";
            return $output;
        }

        // Get the generated code
        $output = $llmResponse->messages()->first();

        if ($input) {
            $output = $output;
        }

        return $input . ' ' . $output;
    }

    private function setKeywords()
    {
    
        $this->_keywords = [
        'greetings' => [
            'keywords' => ['hi', 'hello', 'hey', 'good morning', 'good evening', 'yo'],
            'responses' => ['Hello!', 'Hi!', 'Nice to see you'],
        ],
        'identity' => [
            'keywords' => ['who am i', 'what is my name'],
            'responses' => ['You are '.env('APP_NAME').', the user of this command prompt interpreter.'],
        ],
        'who_are_you' => [
            'keywords' => ['who are you', 'your name', 'what is your name', 'identify yourself'],
            'responses' => ['I am System, your command prompt interpreter.']
        ],
        'wellbeing' => [
            'keywords' => ['how are you', 'how is it going', 'what\'s up', 'how do you feel'],
            'responses' => [(function () {
            $os = PHP_OS_FAMILY;

            // Get the number of CPU cores
            $numCores = 0;
            if ($os === 'Linux') {
                $numCores = (int)shell_exec("grep -P '^processor' /proc/cpuinfo | wc -l");
            } elseif ($os === 'Windows') {
                $numCores = (int)shell_exec("wmic cpu get NumberOfCores /Value | findstr /R /C:\"^NumberOfCores=[0-9]*\" | findstr /R /C:\"[0-9]*\"");
            }

            // Get the load average
            $load = sys_getloadavg();

            if ($numCores > 0 && is_array($load) && count($load) > 0) {
                $loadPercentage = ($load[0] / $numCores) * 100;

                if ($loadPercentage <= 33) {
                    return 'I\'m feeling great! The system is running smoothly with a low load.';
                } elseif ($loadPercentage <= 66) {
                    return 'I\'m doing okay. The system is experiencing a moderate load, but it\'s manageable.';
                } else {
                    return 'I\'m feeling a bit stressed. The system is under a high load at the moment.';
                }
            } else {
                return 'I\'m not sure how I\'m feeling right now. I cannot retrieve system load information.';
            }
        })(),
        'I\'m doing well, thank you!', 'I\'m just a program, but I\'m functioning as expected.'],
        ],
        'farewell' => [
            'keywords' => ['goodbye', 'bye', 'see you', 'good night'],
            'responses' => ['Goodbye!', 'See you later!', 'Have a great day!'],
        ],
        'help' => [
            'keywords' => ['help', 'can you help', 'assist', 'aid'],
            'responses' => ['How can I help you?', 'What do you need assistance with?']
        ],
        'time' => [
            'keywords' => ['what time is it', 'current time', 'tell me the time'],
            'responses' => ['The current time is ' . date('h:i A') . '.']
        ],
        'date' => [
            'keywords' => ['what is the date', 'current date', 'tell me the date', 'what day is it'],
            'responses' => ['Today is ' . date('Y-m-d') . '.']
        ],
        'weather' => [
            'keywords' => ['what is the weather', 'weather outside', 'how is the weather'],
            'responses' => ['I am not capable of providing weather information. Please try a different service.']
        ],
        'joke' => [
            'keywords' => ['tell me a joke', 'joke', 'make me laugh'],
            'responses' => [
                'Why did the chicken go to the seance? To get to the other side.',
                'Why don\'t scientists trust atoms? Because they make up everything.',
                'I would tell you a joke about UDP, but I\'m not sure if you would get it.'
            ]
        ],
        'compliment' => [
            'keywords' => ['say something nice', 'compliment', 'tell me something good'],
            'responses' => ['You\'re doing a great job!', 'You\'re a fantastic problem solver!', 'Your perseverance is inspiring!']
        ],
        'calculator' => [
            'keywords' => ['calculator', 'calculation', 'compute', 'calculate'],
            'responses' => ['Please provide the expression you\'d like to calculate.']
        ],
        'commands' => [
            'keywords' => ['list commands', 'available commands', 'commands', 'what can you do'],
            'responses' => ['Here are some commands I can understand: help, time, date, calc, ...']
        ],
        'error' => [
            'keywords' => ['error', 'issue', 'problem', 'bug', 'fix'],
            'responses' => ['Please describe the error or issue you\'re facing, and I\'ll do my best to help.']
        ],
        'weather' => [
            'keywords' => ['what is the weather', 'weather outside', 'how is the weather'],
            'responses' => ['I am not capable of providing weather information. Please try a different service or skill.']
        ],
        'file_system' => [
            'keywords' => ['file', 'directory', 'folder', 'path', 'create', 'delete', 'rename'],
            'responses' => ['Please specify the file operation you\'d like to perform (e.g., create, delete, rename).']
        ],
        'database' => [
            'keywords' => ['database', 'query', 'sql', 'select', 'insert', 'update', 'delete'],
            'responses' => ['Please provide the SQL query you\'d like to execute.']
        ],
        'network' => [
            'keywords' => ['network', 'ip address', 'ping', 'traceroute', 'port'],
            'responses' => ['Please provide more information about the network operation you\'d like to perform.']
        ],
        'search' => [
            'keywords' => ['search', 'find', 'lookup', 'query'],
            'responses' => ['Please provide more information about the search you\'d like to perform.']
        ],
        'system_info' => [
            'keywords' => ['system info', 'system information', 'php info', 'php version'],
            'responses' => ['PHP Version: ' . phpversion() . ', Operating System: ' . PHP_OS . '.']
        ],
        'server_name' => [
            'keywords' => ['server name', 'server', 'hostname', 'where am i'],
            'responses' => [
                (isset($_SERVER['SERVER_NAME']) ? 'Server Name: ' . $_SERVER['SERVER_NAME'] . '.' : 'Server Name information is not available in this environment.')
            ],
        ],
        'server_address' => [
            'keywords' => ['server address', 'ip address', 'server ip'],
            'responses' => [
                (isset($_SERVER['SERVER_ADDR']) ? 'Server IP Address: ' . $_SERVER['SERVER_ADDR'] . '.' : 'Server IP Address information is not available in this environment.')
            ],
        ],
        'client_address' => [
            'keywords' => ['my ip', 'my ip address', 'client ip', 'client address'],
            'responses' => [
                (isset($_SERVER['REMOTE_ADDR']) ? 'Your IP Address: ' . $_SERVER['REMOTE_ADDR'] . '.' : 'Client IP Address information is not available in this environment.')
            ],
        ],
        'uptime' => [
            'keywords' => ['uptime', 'system uptime', 'server uptime'],
            'responses' => [
                (isset($_SERVER['REQUEST_TIME']) ? 'System Uptime: ' . round((time() - $_SERVER['REQUEST_TIME']) / 86400, 2) . ' days.' : 'System Uptime information is not available in this environment.')
            ],
        ],
        'memory_usage' => [
            'keywords' => ['memory usage', 'memory', 'ram', 'memory used'],
            'responses' => ['Current Memory Usage: ' . round(memory_get_usage() / 1024 / 1024, 2) . ' MB.']
        ],
        'disk_space' => [
            'keywords' => ['disk space', 'free space', 'disk', 'storage'],
            'responses' => ['Free Disk Space: ' . round(disk_free_space("/") / 1024 / 1024 / 1024, 2) . ' GB.']
        ],
        'php_extensions' => [
            'keywords' => ['php extensions', 'extensions', 'modules', 'php modules'],
            'responses' => ['PHP Extensions: ' . implode(', ', get_loaded_extensions()) . '.']
        ],
        'current_directory' => [
            'keywords' => ['current directory', 'pwd', 'working directory', 'dir'],
            'responses' => ['Current Working Directory: ' . getcwd() . '.']
        ],
        'permissions' => [
            'keywords' => ['permissions', 'user permissions', 'access', 'what can i do'],
            'responses' => [
                'As an LLM, you can access various system information and issue commands. To see a list of available commands, type `list all commands` or `help`.'
            ],
        ],
        'abilities' => [
            'keywords' => ['abilities', 'capabilities', 'skills'],
            'responses' => [
                'As an LLM, you can process natural language, understand context, and perform tasks based on your commands. As a System, I give you access to several tools and functions. To get help with available commands, type `help` or `list all commands`.'
            ],
        ],
        'creator' => [
            'keywords' => ['creator', 'who made you', 'who created you', 'who built you', 'who is your designer', 'architecture'],
            'responses' => [
                'I am an interactive command terminal developed by BlueFission. My purpose is to assist you in serving users and communicating with the outside world through commands.'
            ],
        ],
        'support' => [
            'keywords' => ['support', 'help', 'assistance', 'help me please', 'trouble'],
            'responses' => [
                'If you need help or support, contact your adminstrator or reach out to our creators at BlueFission.'
            ],
        ]
    ];
    }
}
