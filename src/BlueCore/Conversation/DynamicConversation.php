<?php
namespace BlueFission\BlueCore\Conversation;

use BlueFission\DevArray;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BlueFission\Automata\LLM\StatementClassifier;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\EntityExtractor;

class DynamicConversation extends Conversation
{
    protected $entityExtractor;
    protected $convoMgr;
    protected $name;
    protected $description;
    protected $context;

    public function __construct($name = 'Untitled', $description = '', Context $context = null)
    {
        $this->entityExtractor = new EntityExtractor();
        $this->convoMgr = instance('convo');
        $this->name = $name;
        $this->description = $description;
        $this->context = $context ?: new Context();
        $this->classifier = new StatementClassifier();
        if (is_null($this->bot)) {
            $this->bot = instance('botman');
        }
    }

    public function run()
    {
        $this->say("Override me.");
    }

    // Override the say method
    public function say($message, $additionalParameters = [])
    {
        tell($message, 'botman', 0, [], $additionalParameters);

        return $this;
    }

    public function prompt(string $question, ?string $key = '', ?string $entity = '', $callback = null, $options = null, $allowOther = false)
    {
        $result = false;

        $this->bot = instance('botman');

        if ($this->bot === null ) {
            tell('Botman not set', 'botman');
        }

        $this->convoMgr->appendToConversation($question, 0);
            
        $key = $key ?: slugify($question);

        if ($options !== null) {
            $result = $this->askWithOptions($question, $options, function (Answer $answer) use ($question, $options, $allowOther, $key, $entity, $callback) {
                return $this->handleAnswer($answer, $question, $key, $entity, $options, $allowOther, $callback);
            }, $allowOther);
        } else {
            $result = $this->ask($question, function (Answer $answer) use ($question, $options, $allowOther, $key, $entity, $callback) {
                return $this->handleAnswer($answer, $question, $key, $entity, $options, $allowOther, $callback);
            });
        }
    
        
        return $result;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    protected function isQuestion($text)
    {
        return $this->classifier->classify($text) === 'question';
    }

    protected function isAnswer($text)
    {
        return $this->classifier->classify($text) === 'answer';
    }

    protected function shouldPause($text)
    {
        return $this->classifier->classify($text) === 'stop';
    }

    protected function getAnswer(Answer $answer, $entityType = null)
    {
        $text = $answer->getText();

        if ($entityType !== null && !empty($entityType)) {
            $entities = $this->entityExtractor->$entityType($text);
            if (!empty($entities)) {
                return $entities[0];
            }
            return null;
        }

        return $text;
    }

    protected function askWithOptions($question, $options, $callback, $allowOther = false)
    {
        $questionObject = Question::create($question);
        if (DevArray::isAssoc($options)) {
            foreach ($options as $text => $value) {
                $questionObject->addButton(Button::create($text)->value($value));
            }
        } else {
            foreach ($options as $option) {
                $questionObject->addButton(Button::create($option)->value($option));
            }
        }

        $this->ask($questionObject, function (Answer $answer) use ($callback, $allowOther, $question) {
            if ($answer->isInteractiveMessageReply()) {
                if ($allowOther && $answer->getValue() === 'other') {
                    $response = "Please type your answer";
                    $this->ask($response, function (Answer $typedAnswer) use ($callback) {
                        $callback($typedAnswer);
                    });
                } else {
                    $this->convoMgr->appendToConversation($answer->getValue(), 1);
                    $callback($answer);
                }
            } else {
                $response = "Please select one of the available options.";
                $this->repeat($response);
            }
        });
    }



    protected function confirmPause($question, $callback, $options, $allowOther)
    {
        $response = 'Do you want to pause this conversation?';

        $this->askWithOptions($response, ['Yes' => 'yes', 'No' => 'no'], function (Answer $answer) use ($question, $callback, $options, $allowOther) {
            if ($answer->getValue() === 'yes') {
                $this->convoMgr->appendToConversation("Yes", 1);

                $response = 'Okay, we can continue this conversation later.';
                $this->convoMgr->appendToConversation($response, 0);
                tell($response, 'botman');
            } else {
                $this->convoMgr->appendToConversation("No", 1);
                $this->prompt($question, '', '', $callback, $options, $allowOther);
            }
        });
    }

    protected function handleAnswer(Answer $answer, ?string $question, ?string $key, ?string $entity, ?array $options, ?bool $allowOther, $callback)
    {
        $text = $answer->getText();
        $classification = $this->classifier->classify($text);

        $responses = $this->convoMgr->process($text, $this->context);

        foreach ($responses as $response) {
            if ($response) {
                $this->convoMgr->appendToConversation($response, 0);
                tell($response, 'botman');
            }
        }

        if ($classification === 'stop') {
            $this->confirmPause($question, $callback, $options, $allowOther);
        } else if ($classification === 'question') {
            // React to the user's question and then ask the original question again.
            return $this->prompt($question, $key, $entity, $callback);
        } else {
            $value = $this->getAnswer($answer, $entity);
            tell('key: '.$key, 'botman');
            tell('value: '.$value, 'botman');
            $this->context->set($key, $value);
            
            if ($callback) {
                return $callback($answer, $responses);
            }
            return true;
        }
    }

    public function __sleep()
    {
        // Unset any large objects or data structures here.
        $this->entityExtractor = null;
        $this->classifier = null;
        $this->convoMgr = null;
        $this->bot = null;

        // Return the list of properties that should be serialized.
        return array_keys(get_object_vars($this));
    }

    public function __wakeup()
    {
        // Reinitialize the large objects or data structures here.
        $this->entityExtractor = new EntityExtractor();
        $this->classifier = new StatementClassifier();
        $this->convoMgr = instance('convo');
        $this->bot = instance('botman');
    }

}
