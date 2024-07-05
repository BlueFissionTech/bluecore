<?php

namespace BlueFission\BlueCore\Conversation;

use BotMan\BotMan\Messages\Incoming\Answer;
use BlueFission\BlueCore\Generation\ApplicationCriteria;

class ApplicationCriteriaConversation extends DynamicConversation
{
    public function __construct()
    {
        parent::__construct('Application Criteria', 'This conversation gathers information about your project');
    }

    public function run()
    {
        $this->askProjectName();
    }

    public function askProjectName()
    {
        $this->prompt('What should we name your project?', 'project_name', '', function (Answer $answer) {
            $this->askIndustry();
        });
    }

    public function askIndustry()
    {
        $industryOptions = [
            'Healthcare' => 'healthcare',
            'Technology' => 'technology',
            'Finance' => 'finance',
            'Education' => 'education',
            'Other' => 'other',
        ];

        $this->prompt('What industry does your project belong to?', 'industry', '', function (Answer $answer) {
            $this->askDescription();
        }, $industryOptions, true);
    }

    public function askDescription()
    {
        $this->prompt('Please provide a brief description of your project.', 'description', '', function (Answer $answer) {
            $this->askAudience();
        });
    }

    public function askAudience()
    {
        $this->prompt('Who is the target audience for your project?', 'audience', '', function (Answer $answer) {
            $this->askProblem();
        });
    }

    public function askProblem()
    {
        $this->prompt('What problem does your project aim to solve?', 'problem', '', function (Answer $answer) {
            $this->askSolution();
        });
    }

    public function askSolution()
    {
        $this->prompt('What solution does your project provide?', 'solution', '', function (Answer $answer) {
            $this->askUserExperience();
        });
    }

    public function askUserExperience()
    {
        $this->prompt('How do you envision the end user\'s experience of the solution?', 'user_experience', '', function (Answer $answer) {
            $this->askKPIs();
        });
    }

    public function askKPIs()
    {
        $this->prompt('What are some goals that you would like to track for your project? (Type "done" when you have listed all the goals you want to include.)', 'goals', '', function (Answer $answer, $responses) {
            if (strtolower($answer->getText()) === 'done') {
                $goalList = $this->getContext()->get('goal_list');
                $this->say('Great! Here are the goals you provided: ' . implode(', ', $goalList));
                // Proceed to the next step or end the conversation
            } else {
                $goalList = $this->getContext()->get('goal_list') ?? [];
                $goalList[] = $answer->getText();
                $this->getContext()->set('goal_list', $goalList);

                if (count($goalList) < 6) {
                    $this->repeat();
                } else {
                    $this->say('You have provided the maximum of 6 Goals: ' . implode(', ', $goalList));
                    // Proceed to the next step or end the conversation
                }
            }
        });
    }
}
