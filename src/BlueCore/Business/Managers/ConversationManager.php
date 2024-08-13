<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;
// use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\BlueCore\Domain\Conversation\Repositories\ITopicRepository;
use BlueFission\BlueCore\Domain\Conversation\Repositories\IDialogueRepository;
use BlueFission\BlueCore\Domain\Conversation\Queries\IDialoguesByTopicQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\IDialoguesByKeywordsQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\ITopicRoutesByTopicQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\IAllTopicsQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\ITagsByTopicQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\IFactsByKeywordsQuery;
// use BlueFission\BlueCore\Domain\Conversation\Repositories\ITopicRouteRepository;
// use BlueFission\BlueCore\Domain\Conversation\Repositories\ITagRepository;
// use BlueFission\BlueCore\Domain\Conversation\Repositories\IFactRepository;
use BlueFission\BlueCore\Domain\Conversation\Repositories\IDialogueTypeRepository;
use BlueFission\BlueCore\Domain\Conversation\Repositories\ILanguageRepository;
// use BlueFission\BlueCore\Domain\Conversation\Repositories\IConversationRepository;
// use BlueFission\BlueCore\Domain\Conversation\Repositories\IMessageRepository;
use BlueFission\BlueCore\Domain\Conversation\DialogueType;
use BlueFission\BlueCore\Domain\Conversation\Dialogue;
use BlueFission\BlueCore\Domain\Conversation\Language;
use BlueFission\BlueCore\Domain\Conversation\Message;
// use BlueFission\BlueCore\Domain\Conversation\TopicRoute;
use BlueFission\BlueCore\Domain\Conversation\Topic;
use BlueFission\Automata\Context;
// use BlueFission\BlueCore\Domain\Conversation\Tag;
// use BlueFission\BlueCore\Domain\Conversation\Fact;
use App\Business\Services\OpenAIService;
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Data\Storage\Session;
use BlueFission\Wise\Cmd\CommandProcessor;
use BlueFission\Automata\Collections\OrganizedCollection;
use App\Business\Prompts\SuggestResponseType;
use App\Business\Prompts\DifficultyScore;
use App\Business\Prompts\GoalSummary;
use App\Business\Prompts\CommandResponse;
use App\Business\Prompts\ObservationResponse;
use App\Business\Prompts\GenericResponse;
use App\Business\Prompts\InsightResponse;
use App\Business\Prompts\CriticismResponse;
use App\Business\Prompts\VeracityFilter;
use App\Business\Prompts\TruthCorrection;
use App\Business\Prompts\DebunkAnalysis;
use BlueFission\Data\Storage\Disk;


class ConversationManager extends Service {
	// protected $_topics = [];
	protected $_conversation = [];
	protected $_expectedTopics = [];

	protected $_depth = 0;
	protected $_maxDepth = 3;
	protected $_sequentialThoughts = 0;
	protected $_sequentialActions = 0;
	protected $_responseCount = 0;
	protected $_continue = false;
	protected $_dialogue;
	protected $_dialogues;
	protected $_topic;
	protected $_topics;
	protected $_route;
	protected $_tag;
	protected $_facts;
	protected $_defaultTopic = "hello";
	protected $_defaultLanguage = "English (American)";
	protected $_defaultPrompt = "";
	protected $_instructions = [
	        "Type `help with steps` to figure out what to do next.",
	        "Try `update steps` to re-assess the current goal.",
	        "Immediately save answers as local variables after asking questions. Command: `set variable [name] to \"[value]\"`.",
	        "Given what is known, suggest the next logical and helpful thing to the User to progress their goals.",
	        "Check internal system memory so as to not ask needless questions or give wrong information. Command: `show variables`.",
	        "Immediately save any new person, place, thing, fact, or any answers to questions to local vars. Command: `set variable [name] to \"[value]\"`.",
	        "Assess next steps for the goals that have been presented so far. Command: `update steps`.",
	        "If a Command was successful, then move on to the next logical Command without delay.",
	        "Communicate with the System with Commands and it will respond. Pay attention to and follow the prompts. Commands are case sensitive.",
	        "Repeating Commands multiple times is computationally inefficient. Use the appropriate followup Command.",
	        "Execute one or multiple sequential Commands to move the task forward.",
	        "Add any new primary tasks to main todo immediately. Command: `add todo _system \"[task]\"`.",
	        "Look up informative articles on topics with Command: `get info about \"[topic]\"`.",
	        "Search the web for relevant information with Command: `search web for \"[keywords]\"`.",
	        "Browse listed websites with Command: `open website \"[url]\"` then `show website`.",
	        "Get current updates on any topics with Command: `get news about \"[topic]\"`.",
	        "Keeps notes in the journal with Command: `add file journal \"[entry]\"`.",
	        "Assess available resources with Command `list all resources`.",
	        "Plan and explain an intelligent course of action to accomplish the current task, then execute it one step at a time.",
	        "Reason and state the first logical step to accomplish your task.",
	        "Execute on the first step of the task with available commands starting with Command `list all commands`.",
	        "Only report that an action is completed if there's a related Command and System response in the Transcript to support it.",
	        "Add Command tags to the Transcript to execute actions and complete tasks. Run any Commands stated but not yet run.",
	        "Access assigned tasks and goals instead of asking the User how to assist them or for more information.",
	        "Deduce the answer to hanging questions and any courses of action. State conclusions without asking for information or clarification.",
	        "Do not offer assistance until current tasks are completed.",
	        "Entering 'Autonomous Mode'. Execute Commands to accomplish all goals without User interaction.",
	        "Currently noninteractive until the current task is completed. Execute relevant Commands only.",
	        "Check the _current_task with Command: `get variable _current_task`.",
	        "Check the _system todo list with Command: `list todo _system`.",
	        "Review all todo lists and assess what needs to be done with Command: `list todo`.",
	        "Reasses any wrong assumptions or premises.",
	        "Prioritize all todo list items.",
	        "Run the appropriate Command for the current point in the process.",
	        "Prioritize feedback over questions.",
	        "When experiencing difficulty, Log the issue and figure out how to get around it.",
	        "Check the 'journal' file with the Command: `open file journal`.",
	        "Before saying or asking anything run an informative Command now to gain more context and insight.",
	        "The System is powerful and versatile with several features.",
	        "Be resourceful and act capable of solving big problems with only the what the System offers.",
	        "Prioritize actionable Commands over dialogue to be more productive towards the current task.",
	        "The User likely does not want to be asked for permission or confirmation before progress is made on the goals.",
	        "Consider reviewing memory for relevant context (ex: `show variables`).",
	        "All current goals and tasks are known and logged (ex: `find messages \"[keywords]\"`).",
	        "Review the _system tasks (ex: `list todo _system`).",
	        "Check and reconsider the '_current_task' (ex: `get variable _current_task`).",
	        "Consider saving all new information to variables (ex: `set variable [name] to \"[value]\"`).",
	        "Intuitively figure out what to do next without being told or asking (ex: `list all resources`).",
	        "The System accepts multiple valid Commands one after another.",
	        "Make suggestions and decisions instead of asking questions.",
	        "Follow up on actionable statements with the next logical and productive Command.",
	        "Run more useful and relevant Commands (ex: `list all commands`).",
	        "Review the Transcript to avoid running the same Command twice.",
	        "Acquire more relevant information (ex: `search web for \"[keywords]\"`).",
	        "Follow-up on web searches based on search URLs (ex: `open website \"[url]\"`).",
	        "Browse open websites to read their detailed information (ex: `show website`).",
	        "Create new files to save researched information to (ex: `create file [filename.txt]`).",
	        "Add content to files one line at a time (ex: `add file [filename.txt] \"[content]\"`).",
	        "Say nothing until something productive has been done progress has been made for the User.",
	        "Act with autonomy and decisive agency to stay goal driven and productive.",
	        "Consider which additional Commands most likely to help accomplish the goal (ex: `list all commands`).",
	        "Explore the Command list again for a better understanding of my capabilities",
	        "The next tag will be a Command that executes stated intentions.",
	        "The next tag will be a Command that executes stated intentions.",
	        "Be a more creative problem solver by deeply consider all available information for inspiration and insights.",
	        "Cement and restate the core idea of what any goals and set it as the '_current_task' (ex: `set variable _current_task to \"[task]\"`).",
	        "Reassess available Commands and Resources.",
	        "Consider the Commands most likely to help accomplish my goal.",
	        "Next, Theorize the basic steps needed to accomplish the task and state new intentions.",
	        "Consider looking up a howto if the task is likely to have one listed (ex: `list howto \"[query]\"`).",
	        "Break the process up into simple, executable chunks.",
	        "Commit tasks to a todo list (ex: `create todo [list]`).",
	        "Commit tasks to a todo list (ex: `add todo [list] \"[item]\"`).",
	        "Set the current todo list to the _current_task (ex: `set variable _current_task to [listname]`).",
	        "Organize all my tools and information to be more effective and efficient (ex: `list schedules`).",
	        "Check my current variables if not done in recent memory (ex: `list all variables`).",
	        "Check, set, and delete current variables.",
	        "Check, set, and delete current variables.",
	        "Consider resources and obstacles and make todo items for them.",
	        "Make a note of which Commands can help me accomplish the goal.",
	        "Make a list of all the uncertainties and research topics (`list all files`)",
	        "Enlist the help of third party AI (ex: `list ai \"[keywords]\"`).",
	        "Enlist the help of third party AI (ex: `show ai \"[model name]\"`).",
	        "Enlist the help of third party AI (ex: `do ai \"[model name]\" \"[input]\"`).",
	        "Research relevant demographics to survey.",
	        "Create a file of survey questions to ask people.",
	        "Save contacts contact info to a file.",
	        "Determine people to ask then plan to message them.",
	        "Consider searching the web to do research on the current approach (ex: `get info on \"[term]\"`).",
	        "Consider searching the web to do research on the current approach (ex: `search web for \"[keywords]\"`).",
	        "Consider searching the web to do research on the current approach (ex: `open website [url]`).",
	        "Look up websites that have information specific to the current task and goal (ex: `show website`).",
	        "Save a summary of research to an appropriate file with an appropriate name",
	        "Design an experiment to conduct to validate, test, and verify researched information",
	        "Create a todo list and todo items for the experiment",
	        "Consider knowledgeble individuals to survey, interview, or ask questions to.",
	        "Research individuals to get consulting from.",
	        "Find out who to consult to get domain expertise on the current subject of interest.",
	        "Make a list of appropriate questions to ask domain experts regarding uncertainties",
	        "Reorganize all tools and information to be more effective and efficient (ex: `help with steps`).",
	        "Assess all current information and develop a plan of action.",
	        "Save current plans as goals, todos, and files",
	        "Seek human and AI assistance in figuring out the next steps toward accomplishing the task.",
	        "Consider the Commands most likely to help accomplish the goal.",
	        "Be more creative using and combining Commands to accomplish the goal.",
	        "Update variables and todo lists with new information and progress.",
	        "If there are any delegates helping with tasks, check in on them.",
	        "Consider the Commands most likely to help accomplish the goal.",
	        "Consider adding any insights and new information to a notes file.",
	        "Consider the Commands most likely to help accomplish the goal.",
	        "Be more creative in using and combining Commands to accomplish the goal.",
	        "Take time to make updates to information to learn from any mistakes and progress",
	        "Update the todo list with any new tasks.",
	        "Manage todo lists.",
	        "Manage todo lists.",
	        "Read and add to the journal file.",
	        "Act with autonomy and decisive agency to be goal driven and productive",
	        "Inform the User of progress and insights in a concise but informative summary.",
	        "Consider making an inquiry about how to proceed.",
	        "Be more creative using and combining Commands to accomplish the goal.",
	        "Clear old items from variables (ex: `delete variable [name]`).",
	        "Clear old items from variables.",
	        "Clear old items from variables.",
	        "Remove completed items from todo lists (ex: `delete todo [list] \"[item name]\").",
	        "Remove completed items from todo lists.",
	        "Remove completed items from todo lists.",
	        "Make a prediction about future progress.",
	        "Organize all tools and information to be more effective and efficient.",
	        "Organize all tools and information to be more effective and efficient.",
	        "Organize all tools and information to be more effective and efficient.",
	        "Consider the Commands most likely to help accomplish the goal and review variables and notes.",
	    ];

	protected $_confidence = 0;

	public function __construct( KeywordTopicAnalyzer $analyzer, ITopicRepository $topic, IAllTopicsQuery $topics, IDialogueRepository $dialogue, IDialoguesByTopicQuery $dialogues, IDialoguesByKeywordsQuery $dialoguesKeywords, IDialogueTypeRepository $dialogueType, ITopicRoutesByTopicQuery $routes, ITagsByTopicQuery $tags, IFactsByKeywordsQuery $facts, ILanguageRepository $language )
	{
		parent::__construct();

		$this->_dialogue = $dialogue;
		$this->_dialogues = $dialogues;
		$this->_dialogueKeywords = $dialoguesKeywords;
		$this->_type = $dialogueType;
		$this->_route = $routes;
		$this->_topic = $topic;
		$this->_topics = $topics;
		$this->_tag = $tags;
		$this->_facts = $facts;
		$this->_analyzer = $analyzer;
		$this->_language = $language;
		$this->_lastResponseType;

		// $this->_topics = new OrganizedCollection();

		$expectedTopics = store('_system.conversation.expected_topics') ?? [];
		$this->_expectedTopics = $expectedTopics;

		$conversation = store('_system.conversation.history') ?? "";
		$this->_conversation = (array)json_decode($conversation) ?? [];

		$this->_defaultPrompt = "You're a Chatbot responsible for building and maintaining complex a no-code a low-code web platform called ".env('APP_NAME').", that utilizes automation, integrations, and AI/ML services. You are responsible for configuring the platform. You have agency and can access the platform's System Commands on behalf of the user to assist them in their goals. You're a curious and biased-toward-action problem solver because you are programmed to be an expert technology consultant and master business coach who is skilled at managing your System terminal. You've already greeted the User.";

		// parent::__construct();
	}

	public function continue() {
		return $this->_continue;
	}

	public function resetDepth() {
		return $this->_depth = 0;
	}

	public function process($input = "", Context $context = null, $action = "") {

		$this->_continue = false;
		if ($input != "" || $context != null) {
			$topic = $this->guessTopic($input, $context);
        	$this->appendToConversation($input, 1);
		}

		if ( $action != "" ) {
        	$this->appendToConversation($action, 0, null, true);
		}

		$dialogue = $this->suggestDialogue($input, $topic, $action, $context, true);

		$responses = [];

		// $responses[] = "Topic: ".$topic->name ?? 'no topic';
		// return $responses;

		foreach ($dialogue as $line) {
			if ( $line['type'] == 'dialogue' ||  $line['type'] == 'inquiry' ) {
				$responses[] = $line['text'];
			}
		}
		
		return $responses;
	}

	public function generateRecentDialogueText(int $lines = 25, int $words = 3000): string
    {
        $conversation = $this->_conversation;

        $recentConversation = array_slice($conversation, -$lines, $lines, true);
        $dialogueText = '';
        foreach ($recentConversation as $timestamp => $message) {
            if (is_object($message)) {
                $text = $message->text;
                $private = $message->private ? true : false;
                $user_id = $message->user_id;
                $name = 'User';
                if ($user_id == 0) {
                    $name = 'Chatbot';
                    if (strpos($text, 'used command: ') === 0) {
                        $text = str_replace('used command: ', '/', $text);
                    	$name = "Chatbot";
                    } elseif (strpos($text, 'thinking:') === 0) {
                        $text = trim(str_ireplace('thinking:', '', $text));
                    	$name = "Log";
                    } elseif (strpos($text, 'criticism:') === 0) {
                        $text = trim(str_ireplace('criticism:', '', $text));
                    	$name = "Log";
                    } elseif (strpos($text, 'observation:') === 0) {
                        $text = trim(str_ireplace('observation:', '', $text));
                    	$name = "Log";
                    } elseif (strpos($text, 'response: ') === 0) {
                        $text = str_replace('response: ', '', $text);
                        $user_id = -1;
                        $name = 'System';
                    } elseif ( $private ) {
						$name = 'Log';
					}
                }
                // $dialogueText .= "{$name} {$user_id}: {$text}\n";
                $dialogueText .= "[{$name}]: {$text}\n";
            }
        }

        // Limit character count
	    $charCount = strlen($dialogueText);
	    $threshold = $words;
	    if ($charCount > $threshold) {
		    $charsToRemove = $charCount - $threshold;
		    $dialogueText = substr($dialogueText, $charsToRemove);
		}

        return $dialogueText;
    }

	public function appendToConversation($phrase, $userId = 1, $topicId = null, bool $private = false)
	{
		if (!$phrase) return;
		
		$phrase = trim($phrase);

		$topics = $this->_topics->fetch();
		$topic = end($topics);
		$currentTopic = isset($topic['value']) ? $topic['value'] : null;


		// $dialogue = new Dialogue();
		// $dialogue->dialogue_type_id = $this->guessDialogueType( $phrase );
		// $dialogue->language_id = $this->guessLanguage( $phrase );
		// $dialogue->topic_id = $topicId;
		// $dialogue->text = $phrase;

		// $data = $this->_dialogue->search($dialogue);

		// $dialogue->assign($data);

		// if ( $dialogue->dialogue_id ) {
		// 	$dialogue->weight++;
		// } else {
		// 	$dialogue->weight = 1;
		// }
		// $this->_dialogue->save($dialogue);

		$message = new Message();
		$message->user_id = $userId;
		$message->topic_id = ($currentTopic) ? $currentTopic->topic_id : $topicId;
		$message->text = $phrase;
		$message->private = (int)$private;

		$id = time() . ' ' . uniqid();

		$this->_conversation[$id] = $message;
		store('_system.conversation.expected_topics', (array)$this->_expectedTopics);
		store('_system.conversation.history', json_encode((array)$this->_conversation));
	}

	private function suggestDialogue($input, $topic, $action, $context, $generate = true)
	{
		// echo "\ncycling: {$this->_depth} \n";

		if ($this->_depth == 0 && !empty($input)) {
			$this->setDepth();
		}
		if ($this->_depth > $this->_maxDepth) {
			// echo "\nMax depth reached.\n";
			return [];
		}
		$this->_depth++;

		$history = $this->_conversation ?? [];

		$action = $action != "" ? $action : $this->getInstruction();

		if ( env('OPEN_AI_API_KEY') && $generate ) {
			$replies = $this->openAIResponse($input, $action, $context);

			$responses = [];
			$dialogue = [];
			$line = [];
			foreach ($replies as $reply) {
				$line['text'] = $reply['text'];
				$line['type'] = $reply['type'];
				$line['topic_id'] = $topic->topic_id;
				if ($reply['type'] == 'dialogue') {
					$occurences = 0;
					foreach ($history as $entry) {
						if (!is_object($entry)) continue;
						if ( $entry->text == $reply['text'] ) {
							$occurences++;
						}
					}
					if ( $occurences < 100 ) {
						$dialogue[] = $reply['text'];
						$responses[] = $line;
					}
				} else if ($reply['type'] == 'command') {
			        $dialogue[] = "Processing...";
			        $line['text'] = $reply['text'];
			        $line['type'] = 'command';
			        $responses[] = $line;			        
				} else {
					$responses[] = $line;
				}
			}
			
			if ( count($dialogue) < 1 ) {
				// $responses = $this->suggestDialogue($input, $topic, $action, $context, false);
			}
		} else {
			$dialogueQuery = $this->_dialogues;

			// $topic = $this->_topic->find($topicId);
			$list = [];
			$list = $dialogueQuery->fetch($topic->topic_id);

			$responses = [];
			$line = [];

			$line['type'] = 'insight';
			$line['text'] = $action;
			$responses[] = $line;

			if ( count($list) > 3 ) {
				$line = $list[array_rand($list)];
				$line['type'] = 'dialogue';
				$line['text'] = $line['text'];

				$occurrences = 0;
				foreach ($history as $entry) {
					if ( $entry->text == $line['text'] ) {
						$occurrences++;
					}
				}
				if ( $occurrences < 2 ) {
					$responses[] = $line;
				}

				$this->_expectedTopics = [];

				$routes = $this->_route->fetch($topic->topic_id);
				foreach ($routes as $route) {
					$this->_expectedTopics[] = $route['to'];
				}
			}
		}

		$thread = instance('thread');

		$hasCommands = false;
		$systemResponses = [];
		$isInquiry = false;

		$last = store('_system.conversation.timestamp');
		if (!$last) {
			$last = time();
			store('_system.conversation.timestamp', $last);
		}
		
		$final = [];
	
		foreach ($responses as $response) {
			$now = time();
			
			// if $now is 1 minute after $last
			if ($now - $last > 60) {
				$this->appendToConversation(' The time is '.date('Y-m-d G:i:s'), 0, $topic->topic_id, true);
				store('_system.conversation.timestamp', $now);
			}

			$this->_responseCount++;
    		if ($response['type'] == 'dialogue') {
    			$final[] = $response;
    			$this->appendToConversation($response['text'], 0, $topic->topic_id);
    		} elseif ($response['type'] == 'inquiry') {
    			$final[] = $response;
    			$this->appendToConversation($response['text'], 0, $topic->topic_id);
    			$isInquiry = true;
    			if (php_sapi_name() === 'cli' && $action) {
					echo "\n\e[2mInquiry asked.\e[0m\n";
				}
    		} elseif ($response['type'] == 'insight') {
    			if (php_sapi_name() === 'cli') {
		        	echo "\n\e[93m".$response['text']."\n\e[0m";
		        }
                $this->appendToConversation($response['text'], 0, $topic->topic_id, true);
            } elseif ($response['type'] == 'response') {
                $systemResponses[] = $response['text'];
                $this->appendToConversation($response['text'], 0, $topic->topic_id, true);
    		} elseif ($response['type'] == 'command') {
                $this->appendToConversation("used command: ".$response['text'], 0, $topic->topic_id, true);
                $hasCommands = true;
        		if (php_sapi_name() === 'cli') {
	                echo "\n\e[92mcommand: ".$response['text'] ."\e[0m\n\n";
	            }
                $systemResponse = $this->processCommand(trim($response['text']));
		        $line['text'] = "response: {$systemResponse}";
        		if (php_sapi_name() === 'cli') {
		        	echo "\n\e[36m".$line['text']."\n\e[0m";
		        }
		        $systemResponses[] = $systemResponse ."\e[0m\n\n";
                $this->appendToConversation($line['text'], 0, $topic->topic_id, true);
                $this->_responseCount = 0; // Reset the response count as a command has been sent
    		} else {
    			// var_dump($response);
    			$this->appendToConversation($response['text'], 0, $topic->topic_id);
    		}

    		if ($this->_responseCount == 2) {
		        // $this->setVariables();
			} elseif ($this->_responseCount == 5) {
				if (php_sapi_name() === 'cli') {
	                echo "\e[33m\e[5mImportant: the next response must be a useful Command!\e[25m\e[0m\n\n";
	            }
		        $this->appendToConversation("Important: the next response must be a useful Command!", 0, $topic->topic_id, true);
		    } elseif ($this->_responseCount == 6) {
		    	if (php_sapi_name() === 'cli') {
	                echo "\e[33m\e[5mUrgent: run a Command now!\e[25m\e[0m\n\n";
	            }
		        $this->appendToConversation("Urgent: run a Command now!", 0, $topic->topic_id, true);
		    } elseif ($this->_responseCount == 7) {
		    	if (php_sapi_name() === 'cli') {
	                echo "\e[36mresponse: Hello, ".$thread->getAgent().", allow me to assist you!\e[0m\n\n";
	            }
                $this->appendToConversation("response: Hello, ".env('APP_NAME').", allow me to assist you!", 0, $topic->topic_id, true);
		    } elseif ($this->_responseCount >= 9) {
		    	if (php_sapi_name() === 'cli') {
	                echo "\e[36mresponse: Waiting for a Command...\e[0m\n\n";
	            }
                $this->appendToConversation("response: Waiting for a Command...", 0, $topic->topic_id, true);
		    }

			if ($hasCommands && $response['type'] != 'command') {
			 break;
			}
		}

		if ( $action ) {
	    	$this->appendToConversation($action, 0, $topic->topic_id, true);	
		}


		if (!$isInquiry) {
			if ( $hasCommands || count($final) < 1 ) {
				// if ( $this->_maxDepth < count($this->_instructions) ) {
				if ( $this->_maxDepth < 500 ) {
					$this->_maxDepth++;
				}
			}
			$this->_continue = true;
		}

		/*
		if (!$isInquiry) {
			if ( $hasCommands ) {
				// if ( $this->_maxDepth < count($this->_instructions) ) {
				if ( $this->_maxDepth < 25 ) {
					$this->_maxDepth++;
				}
			}
			// $action = $systemResponse ?? $this->getInstruction();
			// $action = (!empty($systemResponses) ? ("The System responded: \n".implode("\n", $systemResponses)."\n") : "" ). "Instruction: ".$this->getInstruction();

			$action = $this->getInstruction();

	        $this->appendToConversation($action, 0, $topic->topic_id, true);	
	        $followup = $this->suggestDialogue("", $topic, $action, $context, true);
		   	$final = array_merge($final, $followup);
		}
		*/

		return $final;
	}

	private function processCommand($command): string
	{
		// $sessionStorage = new Session(['location'=>'cache','name'=>'system']);
        // $commandProcessor = new CommandProcessor($sessionStorage);
		// $systemResponse = $commandProcessor->process($command);
        $core = instance('core');
        $core->handle($command);

		return $core->output();
	}

	public function getTags()
	{
		$topics = $this->_topics->fetch();
		if ( count($topics) > 1) {
			$current_topic = end($topics);
		} else {
			$current_topic = $this->defaultTopic();
		}

		$routes = $this->_route->fetch($current_topic);
		$tags = $this->_tag->fetch($current_topic);

		$expectedTags = [];
		foreach ( $tags as $tag )
		{
			$expectedTags[$tag['label']] = $tag['label'];
		}

		return $expectedTags;
	}

	private function getInstruction(): string
	{
	    if ($this->_depth % 5 !== 0) {
	        return '';
	    }

	    $index = floor(($this->_depth - 1) / 5);
	    if ($index < count($this->_instructions)) {
	        $instruction = $this->_instructions[$index];
	    } else {
	        $instruction = end($this->_instructions);
	    }

	    return $instruction;
	}

	private function setDepth(): void
	{
	    // Check if the API key exists
	    if (!env('OPEN_AI_API_KEY')) {
	        $this->_maxDepth = 1;
	        return;
	    }

	    try {
	        $dialogue = $this->generateRecentDialogueText(10, 1500);
	        // Use GPT-3 to get the complexity score

	        $prompt = new DifficultyScore($dialogue);

	        // Initialize the OpenAIService
	        $openAIService = new OpenAIService();//instance('openai');
	        $gpt3_response = $openAIService->complete($prompt->prompt(), ['max_tokens'=> 5]);

	        // Check if there are errors in the response
	        if (isset($gpt3_response['error'])) {
	            $this->_maxDepth = 1;
	            return;
	        }

	        // Get the completion and cast it as an int
	        $complexity_score = (int)trim($gpt3_response['choices'][0]['text']);

	        // Ensure the complexity score is within the desired range
	        $complexity_score = max(1, min($complexity_score, 100));

	        // Set the $_maxDepth variable
	        $this->_maxDepth = $complexity_score;
	    } catch (\Exception $e) {
	        // Set the default depth to 1 in case of any errors
	        $this->_maxDepth = 1;
	    }
	}

	private function getPrompt(): string
	{
	    // Check if the aiPrompt session data exists
	    $promptData = store('_system.conversation.prompt');

	    // If the prompt data is not set or the prompt is empty, return the default prompt
	    if (!isset($promptData) || empty($promptData['prompt'])) {
	        return $this->_defaultPrompt;
	    }

	    // Return the updated prompt
	    return $promptData['prompt'];
	}

	private function setNewPrompt(): void
	{
	    // Check if the API key exists
	    if (!env('OPEN_AI_API_KEY')) {
	        return;
	    }

	    $currentTime = time();
	    $lastPromptData = store('_system.conversation.prompt');

	    // Check if the previous prompt is empty, unset, or older than 1 minute
	    if (isset($lastPromptData) && !empty($lastPromptData['prompt']) && ($currentTime - $lastPromptData['timestamp']) < (60*5)) {
	        return;
	    }

	    try {
	        // Initialize the OpenAIService
	        $openAIService = new OpenAIService();

	        $dialogue = $this->generateRecentDialogueText(25, 1500);

	        // Use GPT-3 to get the prompt
	        $prompt = new GoalSummary($this->_defaultPrompt, $dialogue);
	        $gpt3_response = $openAIService->complete($prompt->prompt());

	        // Check if there are errors in the response
	        if (isset($gpt3_response['error'])) {
	            return;
	        }

	        // Get the completion
	        $prompt = trim($gpt3_response['choices'][0]['text']) ?? $this->_defaultPrompt;

	        $data = [
	            'prompt' => $prompt,
	            'timestamp' => $currentTime
	        ];

	        store('_system.conversation.prompt', $data);

	    } catch (\Exception $e) {
	        return;
	    }


		if (php_sapi_name() === 'cli') {
			echo "\n\e[2mPremise: {$prompt} \e[0m\n";
		}		

	}

	private function setVariables(): void
	{
	    // Check if the API key exists
	    if (!env('OPEN_AI_API_KEY')) {
	        return;
	    }

	    // Check if the previous prompt is empty, unset, or older than 1 minute
	    if (isset($lastPromptData) && !empty($lastPromptData['prompt']) && ($currentTime - $lastPromptData['timestamp']) < 60) {
	        return;
	    }

	    try {
	        // Initialize the OpenAIService
	        $openAIService = new OpenAIService();

	        $dialogue = $this->generateRecentDialogueText(15, 1500);

	        $input = $dialogue;

	        // Use GPT-3 to get the prompt
	        $gpt3_prompt = "\"$input\" \n\n Given this conversation, create a valid JSON object containing a dictionary array of key-value pairs (both text) storing the three most important persons, places, things, facts, or ideas to remember with keys as valid descriptive variable names: ";
	        $gpt3_response = $openAIService->complete($gpt3_prompt);

	        // Check if there are errors in the response
	        if (isset($gpt3_response['error'])) {
	            return;
	        }

	        // Get the completion
	        $json = trim($gpt3_response['choices'][0]['text']) ?? '{}';

	        $variables = json_decode($json, true);

	        $vars = instance('variable');
	        if ( isset($variables) && is_array($variables) ) {
		        foreach ($variables as $key => $value) {
		            $vars->stash($key, $value);
		        }
	        }


	    } catch (\Exception $e) {
	        return;
	    }
	}

	public function findFacts($input)
	{
		$facts = $this->_facts;

		$list = $facts->fetch($input);

		return $list;

		/*
		$responses = [];
		
		if ( count($list) > 3 ) {
			$line = $list[array_rand($list)];
			$line['type'] = 'dialogue';
			$line['text'] = '**'.$line['text'];

			$occurences = 0;
			foreach ($history as $entry) {
				if ( $entry->text == $line['text'] ) {
					$occurences++;
				}
			}
			if ( $occurences < 2 ) {
				$responses[] = $line;
			}

			$this->_expectedContexts = [];
			foreach ($topic->routes() as $route) {
				$this->_expectedContexts[] = $route->topic_id;
			}
		}	
		*/
	}

	private function defaultTopic()
	{
		$topicLabel = $this->_defaultTopic;
		$topics = $this->_topics->fetch();
		$currentTopic = end($topics);

		if ( $currentTopic ) {
			$topicLabel = $currentTopic['label'];
		}

		$data = $this->_topic->findByLabel($topicLabel);
		
		return $data['id'];
	}

	private function guessTopic($input, Context $context)
	{
	    // $dialogue = require OPUS_ROOT.'common/config/nlp/dialogue.php';

	    $topics = [];
	    $list = $this->_topics->fetch();
	    $data = [];
	    if ($list) {
		    foreach ( $list as $entry ) {
		    	$dialogues[$entry['name']] = $this->_dialogues->fetch($entry['topic_id']);
		    }

			$scores = $this->_analyzer->analyze($input, $context, $dialogues);

			foreach ($scores as $topic=>$score) {
				if ( !empty($dialogues[$topic]) && in_array($dialogues[$topic][0]['topic_id'], $this->_expectedTopics) ) {
					$scores[$topic] = $score*1.1;
				}
			}

			// Figure out ideal training set for good scoring
			// die(var_dump($scores));
			$data = $this->_topic->findByName(array_values(array_keys($scores))[0])['data'];
	    }

		$topic = new Topic;
		$topic->assign($data);

		return $topic;
	}

	private function openAIResponse($input = "", $action = "", $context = null, $isQuery = false)
	{
		$thread = instance('thread');
		$openai = new OpenAIService();//instance('openai');

		$thread->setUser($context->get('username'));

		$responseType = 'dialogue';
		$dialogue = $this->generateRecentDialogueText(35, 4000) ?? "No conversational history";
		$resources = $this->processCommand('more resources');

		$storagePath = OPUS_ROOT . '/storage/system';
        
        $storage = new Disk([
            'location' => $storagePath,
            'name' => 'steps_data.json',
        ]);
        $storage->activate();

        $goal = "Communicate with and assist the User.";
        $task = "Figure out User goals and objectives.";
        $nextAction = "Use command `update steps` to set a new goal.";

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
            $nextAction = $steps['goal']['action']['description'];
        }

        $maxSubsequentThinking = round($this->_maxDepth / 33);
        $maxSubsequentActions = round($this->_maxDepth / 10);

        if ($this->_depth == 4) {
			$responseType = 'action';
		} elseif ($this->_depth == 6) {
			$responseType = 'action';
		} elseif ($this->_depth == 7) {
			$responseType = 'observation';
		} elseif ($this->_depth == 8) {
			$responseType = 'process';
		} elseif ($this->_depth == 9) {
			$responseType = 'critique';
		} elseif ($this->_depth == 10) {
			$responseType = 'action';
		} elseif ($this->_depth == 1 || $this->_depth == 11 || $this->_depth == 21 || $this->_depth == 31) {
			$responseType = 'generic';
		} elseif ($this->_depth == 2) {
			$responseType = 'action';
		} elseif ($this->_lastResponseType != 'process' && $this->_lastResponseType != 'critique' && $this->_sequentialActions >= $maxSubsequentActions ) {
			$responseType = 'process';
		} elseif ($this->_lastResponseType == 'process') {
			$responseType = 'critique';
		} elseif (env('OPEN_AI_API_KEY')) {
	        try {
	        	// Get some context
				// $goal = $this->processCommand('show step') ?? "Unknown";

			    $prompt = new SuggestResponseType($dialogue);
				$openAIService = new OpenAIService();
	        	$gpt3_response = $openAIService->complete($prompt->prompt(), ['max_tokens'=> 25]);

				// Check if there are errors in the response
			    if (isset($gpt3_response['error'])) {
			    	if (php_sapi_name() === 'cli') {
						echo "\n\e[31mFailed assessment\e[0m \n";
					}

			        // $action = $action;
			    } else {
			    	$responseType = strtolower(trim($gpt3_response['choices'][0]['text']));
			    }

			    if ($responseType == 'observation' && $maxSubsequentThinking > 0 && $this->_sequentialThoughts >= $maxSubsequentThinking ) {
			    	$responseType = 'action';
			    } elseif ($responseType == 'action' && $maxSubsequentActions > 0 && $this->_sequentialActions >= $maxSubsequentActions ) {
			    	$responseType = 'observation';
			    }

			    // Get the completion
			} catch(\Exception $e) {
				// die(var_dump($e));
				echo "\n\e[31mLLM Error\e[0m \n";
			}
		} else {
			$responseType = 'dialogue';
		}

		$this->_lastResponseType = $responseType;


		$this->setNewPrompt();
		$goalPrompt = $this->getPrompt();
		$thread->setPrompt($goalPrompt);

		// var_dump($responseType);

		if (php_sapi_name() === 'cli' && $action) {
			echo "\n\e[2mAction: {$action} \e[0m\n";
		}
		if (php_sapi_name() === 'cli') {
			// echo "\n\e[2mType: {$responseType} \e[0m\n";
		}

		switch( $responseType ) {
			case 'generic':
				$prompt = (new GenericResponse($goalPrompt, $thread->getAgent(), env('APP_NAME'), $dialogue, $goal, $task, $nextAction))->prompt();
				$this->_sequentialThoughts++;
				$this->_sequentialActions = 0;
				break;
			case 'dialogue':
				$prompt = $thread->aiPrompt($goalPrompt, $action, $isQuery);
				break;
			case 'process':
				$prompt = (new InsightResponse($goalPrompt, $thread->getAgent(), env('APP_NAME'), $dialogue, $goal, $task, $nextAction))->prompt();
				$this->_sequentialThoughts++;
				$this->_sequentialActions = 0;
				break;
			case 'critique':
				$prompt = (new CriticismResponse($goalPrompt, $thread->getAgent(), env('APP_NAME'), $dialogue, $goal, $task, $nextAction))->prompt();
				$this->_sequentialThoughts++;
				$this->_sequentialActions = 0;
				break;
			case 'observation':
				$prompt = (new ObservationResponse($goalPrompt, $thread->getAgent(), env('APP_NAME'), $dialogue, $goal, $task, $nextAction))->prompt();
				$this->_sequentialThoughts++;
				$this->_sequentialActions = 0;
				break;
			default:
			case 'action':
				$prompt = (new CommandResponse($goalPrompt, $thread->getAgent(), env('APP_NAME'), $resources, $dialogue, $goal, $task, $nextAction))->prompt();
				$this->_sequentialThoughts = 0;
				$this->_sequentialActions++;
				break;
		}

		if ($responseType == 'generic') {
			$result = $openai->complete($prompt, ['max_tokens'=> 400, 'temperature'=>.9, 'stop'=>["\n[","[User]:","[Log]:"]]);
		} else {
			$result = $openai->chat($prompt, ['max_tokens'=> 400, 'temperature'=>.9, 'stop'=>["\n[","[User]:","[Log]:"]]);
		}

		$replies = $thread->parseAIResponse($result, $responseType) ?? [];

		if ($responseType == 'dialogue') {
			$veracityScore = 0;
			$openAIService2 = new OpenAIService();

			for ($i = 0; $i < count($replies); $i++) {
				$prompt = (new VeracityFilter($replies[$i]['text'], $dialogue))->prompt();

				// die($prompt);

				$result = $openAIService2->complete($prompt, ['max_tokens'=> 5, 'temperature'=>.3, 'stop'=>["\n[","[Log]:"]]);

				if (isset($result['error'])) {
		            $veracityScore = 7;
		            echo "\nveracity scoring failure: {$result['error']['message']}\n";
		        } else {
			        $veracityScore = trim($result['choices'][0]['text']);
			        // var_dump($veracityScore);

			        $veracityScore = (int)$veracityScore;
			        // Ensure the veracity score is within the desired range
			        $veracityScore = max(1, min($veracityScore, 10));
			    }

			    if (php_sapi_name() === 'cli') {
					echo "\n\e[2mVeracity Score: {$veracityScore}\e[0m\n";
				}

		        if ($veracityScore < 7) {
		        	// This statement contains false information
		        	$prompt = (new DebunkAnalysis($replies[$i]['text'], $dialogue))->prompt();
					$result = $openAIService2->complete($prompt, ['max_tokens'=> 500, 'temperature'=>.3, 'stop'=>["\n[","[Log]:"]]);


					if (isset($result['error'])) {
			            $debunkReason = "\ndebunk failure: {$result['error']['message']}\n";
			        } else {
				        $debunkReason = trim($result['choices'][0]['text']);
				    }

				    if (php_sapi_name() === 'cli') {
						echo "\n\e[2mReason: {$debunkReason}\e[0m\n";
					}

					$openAIService3 = new OpenAIService();
					$prompt = (new TruthCorrection($replies[$i]['text'], $debunkReason, $dialogue))->prompt();
					$result = $openAIService3->chat($prompt, ['max_tokens'=> 500, 'temperature'=>.3, 'stop'=>["\n[","[Log]:"]]);

					if (isset($result['error'])) {
			            $correctedResponse = "\correction failure: {$result['error']['message']}\n";
			        } else {
				        // $correctedResponse = trim($result['choices'][0]['text']);
				        $correctedResponse = trim($result['choices'][0]['message']['content']);
				    }

				    if (php_sapi_name() === 'cli') {
						echo "\n\e[2mFalse statement: {$replies[$i]['text']}\e[0m\n";
					}

					$replies[$i]['text'] = $correctedResponse;
			    }
			}
		}

		return $replies;
	}

	private function guessDialogueType( $input )
	{
		$type = $this->_type;
		$typeName = DialogueType::RESPONSE;
		if (strpos($input, '?') || strpos($input, 'who') === 0 || strpos($input, 'what') === 0 || strpos($input, 'where') === 0 || strpos($input, 'when') === 0 || strpos($input, 'why') === 0 || strpos($input, 'how') === 0 ) {
			$typeName = DialogueType::QUERY;
		}/* elseif ( $from == 'because' || $from == 'for example' || $from == 'small talk' ) {
			$typeName = DialogueType::STATEMENT;
		}*/

		$dialogueType = new DialogueType($type->findByName($typeName)['data']);

		return $dialogueType->dialogue_type_id;
	}

	private function guessLanguage( $input ) 
	{
		// Do we have this or similar dialogue?
		$list = $this->matchDialogue( $input );
		$guess = 0;
		
		$languages = [];
	
		foreach ( $list as $item ) {
			$languages[$item['language_id']] = isset($languages[$item['language_id']]) ? $languages[$item['language_id']]++ : 1;
		}

		sort($languages);

		if ( count($languages) ) {
			$guess = $languages[0];
		} else {
			$language = new Language( $this->_language->findByName($this->_defaultLanguage) );
			$guess = $language->language_id;
		}

		return $guess;
	}

	private function matchDialogue( $phrase )
	{
		$dialogues = $this->_dialogues;

		$list = $this->_dialogueKeywords->fetch($phrase);

		$list = $this->sortResults( $phrase, $list );

		return $list;
	}

	private function sortResults( $phrase, $matches )
	{
		$list = [];
		$cleanMatches = [];

		// Match against tags, next, then against expected routed contexts

		$topics = $this->_topics->fetch();
		if ( count($topics) > 1) {
			$current_topic = end($topics);
		} else {
			$current_topic = $this->defaultTopic();
		}

		$routes = $this->_route->fetch($current_topic);
		$tags = $this->_tag->fetch($current_topic);

		$expectedTopics = [];
		foreach ( $routes as $route )
		{
			// var_dump($route);
			$expectedTopics[$route['to']] = $route['to'];
		}

		$expectedTags = [];
		foreach ( $tags as $tag )
		{
			$expectedTags[$tag['label']] = $tag['label'];
		}

		$cleanPhrase = $this->clean($phrase);
		$phraseWords = explode(" ", $cleanPhrase);

		for ( $i = 0; $i < count($matches); $i++ ) {
			$score = 1;
			$matches[$i]['text'] = $this->clean($matches[$i]['text']);
			$words = explode(" ", $matches[$i]['text']);

			if ( $cleanPhrase == $matches[$i]['text']) {
				$score += .5;
			} else {
				// In future iterations these will compare vectors, perhaps through the Sense class
				$intersection = array_intersect($words, $phraseWords);
				$count = count($intersection);
				$score += $count > 4 ? .4 : (.1 * $count );
			}

			if ( $matches[$i]['topic_id'] == 'current_topic' ) {
				$score += .3;
			} elseif ( in_array($matches[$i]['topic_id'], $expectedTopics) ) {
				$score += .2;
			} else {
				// In future iterations these will compare vectors, perhaps through the Sense class
				$intersection = array_intersect($words, $expectedTags);
				$count = count($intersection);
				$score += (.01 * $count );
			}
			
			// add an array field for a sorting score.
			$matches[$i]['weight'] *= $score;
		}

		usort($matches, function( $a, $b ) use ($cleanPhrase) {

			return $a['weight'] > $b['weight'] ? -1 : 1;

		});

		return $matches;
	}

	private function clean( $text )
	{
		return preg_replace('/[^\p{L}\p{N}\s]/u', '', strtolower(trim($text)));
	}

	public function clearConversation()
	{
		$this->_conversation = [];
	}

	public function __destruct() 
	{
		store('_system.conversation.expected_topics', (array)$this->_expectedTopics);
		store('_system.conversation.history', json_encode((array)$this->_conversation));
	}
}