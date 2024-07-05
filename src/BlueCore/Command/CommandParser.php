<?php
namespace BlueFission\BlueCore\Command;

// CommandParser.php
class CommandParser
{
    protected $verbs = [
        'do' => ['do','perform','enact', 'run', 'use'],
        'go' => ['go', 'navigate'],
        'make' => ['make', 'create'],
        'get' => ['get', 'retrieve'],
        'set' => ['set', 'change', 'assign'],
        'update' => ['update'],
        'delete' => ['delete', 'remove'],
        'generate' => ['generate', 'build'],
        'message' => ['message'],
        'open' => ['open'],
        'input' => ['input', 'enter'],
        'send' => ['send', 'submit'],
        'edit' => ['edit'],
        'add' => ['add','append'],
        'download' => ['download'],
        'show' => ['show', 'display', 'view'],
        'list' => ['list'],
        'find' => ['find', 'search', 'locate'],
        'previous' => ['previous', 'back'],
        'next' => ['next', 'forward'],
        'select' => ['select', 'choose', 'take', 'check'],
        'help' => ['help', 'assist'],
        'save' => ['save', 'preserve', 'store'],
        'cancel' => ['cancel', 'stop', 'nevermind'],
        'prompt' => ['prompt', 'ask', 'inquire'],
        'less' => ['less', 'fewer'],
        'more' => ['more', 'greater'],
        //Special system root commands
        'scroll' => ['scroll'],
        'copy' => ['copy', 'duplicate'],
        'cut' => ['cut', 'remove'],
        'paste' => ['paste', 'insert'],
        'move' => ['move', 'shift'],
    ];

    protected $questionKeywords = [
        'who' => 'user',
        'what' => 'model',
        'where' => 'location',
        'when' => 'event',
        'why' => 'reason',
        'how' => 'method',
    ];

    protected $pronouns = [
        'you' => 'app',
        'it' => 'last_resource',
        'they' => 'last_set',
        'them' => 'last_set',
    ];

    protected $prepositions = [
        'to', 'in', 'from', 'at', 'on', 'for', 'with', 'about', 'as', 'by', 'of', 'over', 'through', 'under'
    ];

    protected $resourceHandlers = [];

    protected $grammar = [
        'command' => '/^(?<verb>\w+)(?:\s+(?<rest>.+))?$/',
    ];

    protected $app;

    protected $knownResources = [
        'system', 'model', 'controller', 'user', 'filemanager', 'database', 'code', 'skill', 'command', 'info', 'weather', 'website', 'web', 'howto', 'news', 'variable', 'file', 'resource', 'todo', 'queue', 'stack', 'schedule', 'ai', 'transcript', 'task', 'step', 'calc', 'action', 'api', 'feature', 'note', 'entity'
    ];

    protected $noiseWords = [
        'a', 'an', 'the', 'it', 'this', 'and', 'with', 'all',
    ];

    public function __construct()
    {
        $this->app = \App::instance();

        // Register default resource handlers
        $this->registerResourceHandler('phone', '/^\+?\d{1,4}[\s-]?\d{1,4}(?:[\s-]?\d{1,4}){1,4}$/', null);
        $this->registerResourceHandler('email', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/i', null);
        $this->registerResourceHandler('street', '/^\d+\s[A-z]+\s[A-z]+/', null);
        $this->registerResourceHandler('web', '/^https?:\/\/[^\s]+/', null);
    }

    public function parse($input)
    {
        $command = new Command();
        $this->parseCommand($input, $command);
        return $command;
    }

    protected function parseCommand($input, Command $command)
    {
        if (preg_match($this->grammar['command'], $input, $matches)) {
            $verb = $this->findVerb(strtolower($matches['verb']));
            if ($verb) {
                $command->verb = $verb;
                $this->parseResourcesAndArgs($matches['rest'] ?? '', $command);
            }
        }
    }

    public function processPronouns($input, $context)
    {
        $words = $this->splitWords($input);
        $newWords = [];

        foreach ($words as $word) {
            $lowerWord = strtolower($word);
            if (array_key_exists($lowerWord, $this->pronouns)) {
                $replacement = $context[$this->pronouns[$lowerWord]] ?? null;
                if ($replacement) {
                    $newWords[] = $replacement;
                } else {
                    $newWords[] = $word;
                }
            } else {
                $newWords[] = $word;
            }
        }

        return implode(' ', $newWords);
    }

    public function processQuestion($input)
    {
        $words = $this->splitWords($input);
        $firstWord = strtolower($words[0]);

        if (array_key_exists($firstWord, $this->questionKeywords)) {
            $service = $this->questionKeywords[$firstWord];
            $behavior = 'search';
            $args = array_slice($words, 1);

            $command = new Command();

            $command->resources[] = $service;
            $command->verb = $behavior;
            $command->args = $args;
        }

        return null;
    }

    public function registerResourceHandler($name, $pattern, callable $handler = null)
    {
        $this->resourceHandlers[$name] = [
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function getSystemVerbs($category = null)
    {
        if ($category) {
            return $this->verbs[$category] ?? [];
        }

        $verbs = [];
        foreach ($this->verbs as $verbsInCategory) {
            $verbs = array_merge($verbs, $verbsInCategory);
        }

        return $verbs;
    }

    public function getSystemResources()
    {
        return $this->knownResources;
    }

    public function getSystemPrepositions()
    {
        return $this->prepositions;
    }

    protected function processResource($word)
    {
        foreach ($this->resourceHandlers as $name => $handlerInfo) {
            if (preg_match($handlerInfo['pattern'], $word)) {
                if (is_callable($handlerInfo['handler'])) {
                    $word = $handlerInfo['handler']($word);
                }
                break;
            }
        }

        return $word;
    }

    protected function parseResourcesAndArgs($input, Command $command)
    {
        preg_match_all('/\s*(?:(?:"([^"]*)")|([^\s"]+))/', $input, $matches, PREG_SET_ORDER);
        $words = [];
        $areLiterals = [];
        foreach ($matches as $match) {
            $areLiterals[] = !isset($match[2]);
            $words[] = isset($match[2]) ? $match[2] : $match[1];
        }

        $resources = [];
        $args = [];
        $currentArg = [];

        $i = -1;
        foreach ($words as $word) {
            $i++;
            if (in_array($word, $this->prepositions) && !$areLiterals[$i]) {
                if ($currentArg) {
                    $arg = implode(' ', $currentArg);
                    $args[] = $arg;
                    $currentArg = [];
                }
                continue;
            }

            if (in_array($word, $this->noiseWords) && !$areLiterals[$i]) {
                if ($currentArg) {
                    $arg = implode(' ', $currentArg);
                    $args[] = $arg;
                    $currentArg = [];
                }
                continue;
            }

            if ($this->isResource($word) && !$areLiterals[$i]) {
                if ($currentArg) {
                    $arg = implode(' ', $currentArg);
                    $args[] = $arg;
                    $currentArg = [];
                }
                $resources[] = $this->normalizeResource($word);
            } elseif ($areLiterals[$i]) {
                if ( count($currentArg) > 0) {
                    $arg = implode(' ', $currentArg);
                    $args[] = $arg;
                    $currentArg = [];
                }
                $args[] = $word;
            } else {
                $currentArg[] = $word;
            }
        }

        if ($currentArg) {
            $arg = implode(' ', $currentArg);
            $args[] = $arg;
        }

        $command->resources = $resources;
        $command->args = $args;
    }

    protected function processInput($input)
    {
        $commands = $this->splitCommands($input);
        $commandObjects = [];

        foreach ($commands as $command) {
            $commandObjects[] = $this->processCommand($command);
        }

        return $commandObjects;
    }

    protected function splitWords($input)
    {
        // Split the input string into words using a space as a delimiter
        $words = preg_split('/\s+/', $input);

        return $words;
    }

    protected function splitCommands($input)
    {
        $commands = preg_split('/(?<=[a-z0-9])\.(?=\s)/i', $input);
        return $commands;
    }

    protected function processCommand($command)
    {
        $command = $this->processQuotes($command);
        $words = $this->splitWords($command);
        $commandObject = new Command();

        foreach ($words as $word) {
            // process the word with existing logic
        }

        return $commandObject;
    }

    protected function processQuotes($input)
    {
        preg_match_all('/"([^"]*)"/', $input, $matches);
        $quotedStrings = $matches[1];

        foreach ($quotedStrings as $quotedString) {
            $placeholder = '__QUOTED_' . md5($quotedString) . '__';
            $this->quotedPlaceholders[$placeholder] = $quotedString;
            $input = str_replace('"' . $quotedString . '"', $placeholder, $input);
        }

        return $input;
    }

    protected function findVerb($word)
    {
        foreach ($this->verbs as $key => $synonyms) {
            if (in_array($word, $synonyms)) {
                return $key;
            }
        }
        return null;
    }

    protected function isResource($word)
    {
        $word = $this->normalizeResource($word);
        return in_array($word, $this->knownResources);
    }

    protected function normalizeResource($word)
    {
        $word = strtolower($word);

        // Remove noise words
        if (in_array($word, $this->noiseWords)) {
            return null;
        }

        // Handle pluralization
        // if (substr($word, -1) === 's' && in_array(substr($word, 0, -1), $this->knownResources)) {
        //     $word = substr($word, 0, -1);
        // }
        foreach ($this->knownResources as $resource)
        {
            if ($word == pluralize($resource)) {
                $word = $resource;
                break;
            }
        }

        // Process punctuation marks
        $word = preg_replace('/[,.!?;]/', ' ', $word); // Commas, periods, exclamation marks, question marks, semicolons
        $word = preg_replace('/[\'"]/i', '', $word); // Apostrophes, quotes
        $word = preg_replace('/[\{\}\[\]\(\)]/i', '', $word); // Curly braces, brackets, parentheses
        $word = preg_replace('/\s+/', ' ', $word); // Collapse multiple spaces
        $word = trim($word); // Trim spaces at the beginning and end

        if (isset($this->quotedPlaceholders[$word])) {
            $word = $this->quotedPlaceholders[$word];
        }

        $word = $this->processResource($word);

        return $word;
    }
}