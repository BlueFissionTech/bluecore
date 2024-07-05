<?php
namespace BlueFission\BlueCore\Conversation;

use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;

class ModelBuilderConversation extends Conversation
{
    public function askModelBuilderQuestion()
    {
        $question = Question::create('Would you like to start building a Machine Learning model?')
            ->addButtons([
                Button::create('Yes')->value('yes'),
                Button::create('No')->value('no'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                if ($answer->getValue() === 'yes') {
                    // Start ModelCriteriaConversation here
                    $this->bot->startConversation(new ModelCriteriaConversation());
                } else {
                    $this->say('Alright, if you change your mind, just let me know.');
                }
            }
        });
    }

    public function run()
    {
        $this->askModelBuilderQuestion();
    }
}
