<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\BlueCore\Business\Prompts\Analysis;
use BlueFission\BlueCore\Business\Prompts\AssistantRules;
use BlueFission\Automata\LLM\StatementClassifier;
use BlueFission\Data\Storage\Disk;
use BlueFission\Services\Service;
use BlueFission\Utils\DateTime;
use BlueFission\DevString;

class ThreadManager extends Service {
    private $_agent;
    private $_user;
    private $_description;
    private $_prompt;
    private $_input;
    private $_action;

    public function __construct(StatementClassifier $statementClassifier)
    {
        $this->_agent = 'Opus';
        $this->_appname = env('APP_NAME');
        $this->_user = "User";
        $this->_statementClassifier = $statementClassifier;
        parent::__construct();
    }

    public function setPrompt($prompt)
    {
        $this->_prompt = $prompt;
    }

    public function setInput($input)
    {
        $this->_input = $input;
    }

    public function setAction($action)
    {
        $this->_action = $action;
    }

    public function setAgent($agent)
    {
        if (!empty($agent))
            $this->_agent = $agent;
    }

    public function getAgent()
    {
        return $this->_agent;
    }

    public function setUser($user)
    {
        if (!empty($user))
            $this->_user = $user;
    }

    public function setDescription($description)
    {
        $this->_description = $description;
    }

    private function buildTranscript() {
        
        $conversation = store('_system.conversation.history') ?? "";

        $history = (array)json_decode($conversation) ?? [];
        $transcript = "";

        if ($history) {
            $length = 0;
            if (count($history) > 300) {
                $length = count($history) - 300;
            }

            $now = time();
            $history = array_splice($history, $length);
            
            $lastUser = null;

            $time = strtotime('24 hours ago');
            foreach ($history as $key => $line) {
                if (!is_object($line)) continue;
                $content = DevString::truncate($line->text, 500);

                if ( $lastUser !== $line->user_id ) {
                    $lastUser = $line->user_id;
                }

                if ($line->user_id == 0) {
                    if ( strpos($content, 'The time is ') === 0) {
                        $timestamp = substr($content, strlen('The time is ') );
                        $time = strtotime($timestamp);
                    }

                    if ($line->private == 1 ) {
                        if ( strpos($content, 'The time is ') === 0 ) {
                            $transcript .= "[".$this->_agent . "'s Log]: ".$content. PHP_EOL;
                        } elseif ( strpos($content, 'used command: ') === 0) {
                                $transcript .= "-- {$this->_agent} used command `/".trim(substr($content, strlen('used command: ') ))."` --" . PHP_EOL;
                        } elseif ( ($now - $time < 1 * 60) ) {
                            if ( strpos($content, 'response: ') === 0) {
                                $transcript .= "[System]: ".substr($content, strlen('response: ') ).PHP_EOL;
                            } elseif ( stripos($content, 'thinking: ') === 0 || stripos($content, 'criticism: ') === 0 ) {
                                $transcript .= "[Thought]: ".$content.PHP_EOL;
                            } else {
                                $transcript .= "[".$this->_agent . "'s Log]: ".$content. PHP_EOL;
                            }
                        }
                    } else {
                        $transcript .= "[".$this->_agent . "]: ". $content . PHP_EOL;
                    }
                } else {
                    $transcript .= "[".$this->_user . "]: ". $content . PHP_EOL;
                }
            }
        }

        $charCount = strlen($transcript);
        $threshold = 10000;
        if ($charCount > $threshold) {
            $charsToRemove = $charCount - $threshold;
            $transcript = substr($transcript, $charsToRemove);
        }

        return $transcript;
    }

    private function addExchange() {
        $transcript = "";

        if ($this->_input != "") {
            $transcript .= $this->_user . ": ". $this->_input . PHP_EOL;
        }

        if ($this->_action) {
            $transcript .= "-- {$this->_agent} is thinking \"{$this->_action}\" --" . PHP_EOL;
        }

        return $transcript;
    }

    public function history()
    {
        $transcript = $this->buildTranscript();

        return $transcript;
    }

    public function aiPrompt($input = "", $action = "", $isQuery = false)
    {
        $this->setInput($input);
        $this->setAction($action);

        // $transcript = $this->buildTranscript();

        // $transcript .= $this->addExchange();

        // if ($isQuery) {
        //     $transcript .= $this->addAnalysis();
        // }
        
        // $transcript = instance('convo')->generateRecentDialogueText(25) ?? "No conversational history";
        $transcript = $this->buildTranscript() ?? (instance('convo')->generateRecentDialogueText(25) ?? "No conversational history");

        $time = date('l jS \of F Y h:i:s A');
        $place = $this->_appname." Website";

        $storagePath = OPUS_ROOT . '/storage/system';
        
        $storage = new Disk([
            'location' => $storagePath,
            'name' => 'steps_data.json',
        ]);
        $storage->activate();

        $goal = "Communicate with and assist the User.";
        $task = "Figure out User goals and objectives.";
        $action = "Use command `update steps` to set a new goal.";

        $steps = $storage->read();
        if ($steps) {
            $goal = $steps['goal']['description'];
            $task = "Assess best course of action";
            foreach ($steps['goal']['steps'] as $index => $process) {
                if (!$process['complete']) {
                    $task = $process['description'];
                    break;
                }
            }
            $action = $steps['goal']['action']['description'];
        }

        $primer = $this->_prompt;

        $prompt = new AssistantRules($primer, $this->_agent, $this->_appname, $goal, $task, $transcript, $action);

        return $prompt->prompt();
    }

    private function addAnalysis($input) {
        $day = date('l');
        $location = $this->_appname." Website";
        $prompt = new Analysis($input, $day, $location);

        return $prompt->prompt();
    }

    public function parseAIResponse($result, $category = null)
    {
        if (isset($result['error'])) {
            $output[] = ['type' => 'dialogue', 'text' => "I'm experiencing some trouble. Please try again later?"];
            $output[] = ['type' => 'insight', 'text' => $result['error']['message']];
            return $output;
        }

        $me = $this->_agent;

        // $text = trim($result['choices'][0]['text']);
        $response = '';
        if (isset($result['choices'])) {
            if (isset($result['choices'][0])) {
                if (isset($result['choices'][0]['message'])) {
                    if(isset($result['choices'][0]['message']['content'])) {
                        $response = $result['choices'][0]['message']['content'];
                    }
                } elseif (isset($result['choices'][0]['text'])) {
                    $response = $result['choices'][0]['text'];
                }
            }
        }

        $text = trim($response);

        $lines = [];
        // check if it's a command by if first character is a '/'
        if ($category == 'action' || strpos($text, '/') === 0 || strpos($text, '`/') === 0) {
            $text = trim(substr(str_replace('`', '', $text), 1));
            $commands = explode("\n", $text);
            $next = "";

            foreach ($commands as $command) {
                if (strpos($command, '/') === 0) {
                    if ($next !== "") {
                        $line['type'] = 'command';
                        $line['topic_id'] = 0;
                        $line['text'] = substr($next, 1);
                        $lines[] = $line;
                    }
                    $next = $command;
                } else {
                    $next .= "\n{$command}";
                }
            }

            if ($next !== "") {
                $line['type'] = 'command';
                $line['topic_id'] = 0;
                $line['text'] = substr($next, 1);
                $lines[] = $line;
            }

        } elseif (in_array($category, ['process','critique','observation']) || stripos($text, 'thinking:') === 0 || stripos($text, 'criticism:') === 0 || stripos($text, 'observation:') === 0) {
            $line['type'] = 'insight';
            $line['text'] = $text;
            $line['topic_id'] = 0;
            $lines[] = $line;
        } else {
            $classification = $this->_statementClassifier->classify($text);
            $line['type'] = trim($classification) == 'question' ? 'inquiry' : 'dialogue';
            if (php_sapi_name() === 'cli') {
                echo "\n\e[2mStatement Classification: {$classification} \e[0m\n";
            }
            $line['text'] = $text;
            $line['topic_id'] = 0;
            $lines[] = $line;
        }


        return $lines;
    }
}

class Character {
    public $name;
    public $description;
    public $properties;

    public function __construct($name, $description, $properties) {
        $this->name = $name;
        $this->description = $description;
        $this->properties = $properties;
    }
}

class Property {
    public $name;
    public $value;

    public function __construct($name, $value) {
        $this->name = $name;
        $this->value = $value;
    }
}