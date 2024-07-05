<?php

namespace BlueFission\BlueCore\Conversation;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;

class AssistantCriteriaConversation extends Conversation
{
    protected $chatbotType;
    protected $personalityTraits;
    protected $initialPrompt;
    protected $name;
    protected $gender;
    protected $goals;
    protected $primaryConcerns;

    public function askChatbotType()
    {
        $question = Question::create("What type of chatbot do you want to create?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_chatbot_type")
            ->addButtons([
                Button::create('Assistant')->value('assistant'),
                Button::create('Companion')->value('companion'),
                Button::create('Customer Service')->value('customer_service'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->chatbotType = $answer->getValue();
                $this->askPersonalityTraits();
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function askPersonalityTraits()
    {
        $question = Question::create("What personality traits should your chatbot have?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_personality_traits")
            ->addButtons([
                Button::create('Friendly')->value('friendly'),
                Button::create('Professional')->value('professional'),
                Button::create('Humorous')->value('humorous'),
                Button::create('Empathetic')->value('empathetic'),
                Button::create('Other')->value('other'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $value = $answer->getValue();
                if ($value === 'other') {
                    $this->askCustomPersonalityTrait();
                } else {
                    $this->personalityTraits = $value;
                    $this->askInitialPrompt();
                }
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function askCustomPersonalityTrait()
    {
        $this->ask('Please provide your custom personality trait:', function (Answer $answer) {
            $this->personalityTraits = $answer->getText();
            $this->askInitialPrompt();
        });
    }


    public function askInitialPrompt()
    {
        $this->ask('What initial GPT prompt do you want the chatbot to know about itself and its job?', function (Answer $answer) {
            $this->initialPrompt = $answer->getText();
            $this->askName();
        });
    }

    public function askName()
    {
        $this->ask('What is the name of your chatbot?', function (Answer $answer) {
            $this->name = $answer->getText();
            $this->askGender();
        });
    }

    public function askGender()
    {
        $question = Question::create("What is the gender of your chatbot?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_gender")
            ->addButtons([
                Button::create('Male')->value('male'),
                Button::create('Female')->value('female'),
                Button::create('Other')->value('other'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->gender = $answer->getValue();
                $this->askGoals();
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function askGoals()
    {
        $this->ask('What are the goals of your chatbot?', function (Answer $answer) {
            $this->goals = $answer->getText();
            $this->askPrimaryConcerns();
        });
    }

    public function askPrimaryConcerns()
    {
        $this->ask('What are the primary concerns of your chatbot?', function (Answer $answer) {
            $this->primaryConcerns = $answer->getText();
            $this->summarizeAssistantCriteria();
        });
    }

    public function summarizeAssistantCriteria()
    {
        $summary = "Here are the criteria for your chat assistant:\n";
        $summary .= "Type: {$this->chatbotType}\n";
        $summary .= "Personality Traits: {$this->personalityTraits}\n";
        $summary .= "Initial Prompt: {$this->initialPrompt}\n";
        $summary .= "Name: {$this->name}\n";
        $summary .= "Gender: {$this->gender}\n";
        $summary .= "Goals: {$this->goals}\n";
        $summary .= "Primary Concerns: {$this->primaryConcerns}\n";

        $this->say($summary);
        // Continue with the next steps, such as saving criteria, generating code, or other actions
    }

    public function run()
    {
        $this->askChatbotType();
    }
}

