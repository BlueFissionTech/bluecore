<?php
namespace BlueFission\BlueCore\Conversation;

use BotMan\BotMan\Messages\Incoming\Answer;

class ApplicationBuilderConversation extends DynamicConversation
{
    public function __construct()
    {
        parent::__construct('Application Builder', 'A conversation to help configure your application');
    }

    public function askApplicationBuilderQuestion()
    {
        $question = 'Would you like to start configuring your application?';
        $options = ['Yes' => 'yes', 'No' => 'no'];

        $this->prompt($question, 'start_configuring', null, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                if ($answer->getValue() === 'yes') {
                    // Start ApplicationCriteriaConversation here
                    $this->bot->startConversation(new ApplicationCriteriaConversation());
                } else {
                    $this->say('Alright, if you change your mind, just let me know.');
                }
            }
        }, $options);
    }

    public function run()
    {
        $this->askApplicationBuilderQuestion();
    }
}
