<?php
namespace BlueFission\BlueCore\Conversation;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;

class WebsiteCriteriaConversation extends Conversation
{
    protected $websiteType;
    protected $audience;
    protected $features;
    protected $pages;
    protected $nameLogo;
    protected $colorSchemeStyleGuide;

    public function askWebsiteType()
    {
        $question = Question::create("What type of website are you building?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_website_type")
            ->addButtons([
                Button::create('Brochure')->value('brochure'),
                Button::create('eCommerce')->value('ecommerce'),
                Button::create('Social')->value('social'),
                // Add more types if necessary
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->websiteType = $answer->getValue();
                $this->askAudience();
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function askAudience()
    {
        $this->ask('Who is the target audience for your website?', function (Answer $answer) {
            $this->audience = $answer->getText();
            $this->askFeatures();
        });
    }

    public function askFeatures()
    {
        $this->ask('What features do you want on your website? (e.g. Blog, Contact Form, Gallery, etc.) Separate each feature with a comma.', function (Answer $answer) {
            $this->features = array_map('trim', explode(',', $answer->getText()));
            $this->askPages();
        });
    }

    public function askPages()
    {
        $this->ask('What pages do you want on your website? (e.g. Home, About, Contact, etc.) Separate each page with a comma.', function (Answer $answer) {
            $this->pages = array_map('trim', explode(',', $answer->getText()));
            $this->askNameLogo();
        });
    }

    public function askNameLogo()
    {
        $question = Question::create("Do you have a name, logo, or branding for your website?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_name_logo")
            ->addButtons([
                Button::create('Yes')->value('yes'),
                Button::create('No')->value('no'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->nameLogo = $answer->getValue() === 'yes';
                $this->askColorSchemeStyleGuide();
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function askColorSchemeStyleGuide()
    {
        $this->ask('Do you have a preferred color scheme or style guide for your website? If yes, please provide details. If no, just type "No".', function (Answer $answer) {
            $this->colorSchemeStyleGuide = $answer->getText();
            $this->askContentImagery();
        });
    }

    public function askContentImagery()
    {
        $this->ask('What type of content and imagery do you want for your website? Please provide any specific requirements or preferences.', function (Answer $answer) {
            $this->contentImagery = $answer->getText();
            $this->summarizeWebsiteCriteria();
        });
    }

    public function summarizeWebsiteCriteria()
    {
        $summary = "Here's the summary of your website criteria:\n";
        $summary .= "Website Type: {$this->websiteType}\n";
        $summary .= "Target Audience: {$this->audience}\n";
        $summary .= "Features: " . implode(', ', $this->features) . "\n";
        $summary .= "Pages: " . implode(', ', $this->pages) . "\n";
        $summary .= "Name, Logo, or Branding: " . ($this->nameLogo ? 'Yes' : 'No') . "\n";
        $summary .= "Color Scheme / Style Guide: {$this->colorSchemeStyleGuide}\n";
        $summary .= "Content & Imagery: {$this->contentImagery}\n";

        $this->say($summary);
    }

    public function run()
    {
        $this->askWebsiteType();
    }
}
