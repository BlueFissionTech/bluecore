<?php
namespace BlueFission\BlueCore\Conversation;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BlueFission\BlueCore\Generation\MachineLearning\ModelCriteria;

class ModelCriteriaConversation extends Conversation
{
    protected $modelCriteria;

    protected $projectName = 'Untitled';
    protected $tableName;

    public function __construct()
    {
        $this->modelCriteria = new ModelCriteria();
    }

    public function askProjectName()
    {
        $this->ask('What should we name your project?', function (Answer $answer) {
            $this->projectName = $answer->getText();
            $modelName = slugify($this->projectName).'-model';
            $jobName = slugify($this->projectName).'-job';
            
            $this->modelCriteria->setModelName($modelName);
            $this->modelCriteria->setTrainingJobName($jobName);
            $this->askDatasetLocation();
        });
    }

    public function askDatasetLocation()
    {
        $question = Question::create("Are you using a local or external dataset?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_dataset_location")
            ->addButtons([
                Button::create('Local (in database)')->value('local'),
                Button::create('External (file or api)')->value('external'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $type = $answer->getValue();
                switch ($type) {
                    case 'local':
                        $this->askDataSource();
                    break;
                    case 'external':
                        $this->askDatasetType();
                    break;
                }
                // Continue with the next step
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function askModelName()
    {
        $this->ask('What should we name your model?', function (Answer $answer) {
            $this->modelCriteria->setModelName($answer->getText());
            $this->askTrainingJobName();
        });
    }

    public function askTrainingJobName()
    {
        $this->ask('What should we name your training job?', function (Answer $answer) {
            $this->modelCriteria->setTrainingJobName($answer->getText());
            $this->askDatasetLocation();
        });
    }

    public function askNewDatasetNameAndType()
    {
        $this->ask("What is the name of the new dataset?", function (Answer $answer) {
            $this->modelCriteria->setDatasetName($answer->getText());
            $this->askDatasetType();
        });
    }

    public function askDatasetType()
    {
        $question = Question::create("What type of dataset is it?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_dataset_type")
            ->addButtons([
                Button::create('Numerical')->value('numerical'),
                Button::create('Categorical')->value('categorical'),
                Button::create('Text')->value('text'),
                Button::create('Time Series')->value('time_series'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->modelCriteria->setDatasetType($answer->getValue());
                // Continue with the next step
                $this->askDatasetSubmission();
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function askDataSource()
    {
        $appModels = $this->retrieveAvailableTables(); // Function to retrieve existing database models from your application

        $question = Question::create("Which existing database model do you want to use?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_database_model");

        foreach ($appModels as $appModel) {
            $question->addButton(Button::create($appModel['name'])->value($appModel['id']));
        }

        $question->addButton(Button::create('None, create a new dataset')->value('new_dataset'));

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $value = $answer->getValue();
                if ($value === 'new_dataset') {
                    $this->askNewDatasetNameAndType();
                } else {
                    $this->modelCriteria->setDatabaseModelId($value);

                    $this->tableName = $value;
                    
                    $datasetSize = $this->getDatasetSize($this->tableName);
                    $this->modelCriteria->setDatasetSize($datasetSize);
                    $this->retrieveAvailableTables();
                    $this->askOutputLocation();
                }
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function retrieveAvailableTables()
    {
        // $tables = $this->getTablesFromDatabaseModel($this->modelCriteria->getDatabaseModelId()); // Function to get tables from the chosen database model

        // Process tables to determine the size of the dataset
        // ...

        // Continue with the next step
        return [['id'=>'table1', 'name'=>'Table 1']];
    }

    private function getDatasetSize($tableName) {
        return 10000;
        // Assuming you have a function to get the PDO connection
        $pdo = getPdoConnection();

        // Prepare and execute the SQL query to count rows in the specified table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName}");
        $stmt->execute();

        // Fetch the result and return the dataset size
        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result;
    }


    public function askOutputLocation()
    {
        $this->ask('What is the S3 location where you would like to store the output? (e.g., s3://your-bucket/path/to/output)', function (Answer $answer) {
            $this->modelCriteria->setOutputLocation($answer->getText());
            $this->askProblemType();
        });
    }

    public function askProblemType()
    {
        $question = Question::create("What type of problem are you trying to solve?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_problem_type")
            ->addButtons([
                Button::create('Predict a continuous value')->value('regression'),
                Button::create('Classify data into categories')->value('classification'),
                Button::create('Group similar data points')->value('clustering'),
                Button::create('Discover patterns in data')->value('pattern_discovery'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->modelCriteria->setProblemType($answer->getValue());
                $this->suggestBestAlgorithm();
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    private function askDatasetSubmission()
    {
        $question = Question::create("How will you provide access to your dataset?")
            ->fallback("Unable to ask question")
            ->callbackId("ask_dataset_submission")
            ->addButtons([
                Button::create('Upload File')->value('upload'),
                Button::create('Online Location')->value('online'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->modelCriteria->setDatasetSubmission($answer->getValue());
                $this->suggestBestAlgorithm();
            } else {
                $this->repeat("Please select one of the provided options.");
            }
        });
    }

    public function suggestBestAlgorithm()
    {
        // Function to suggest the best algorithm based on the dataset and problem type
        $bestAlgorithm = $this->getBestAlgorithm($this->modelCriteria->getDatasetType(), $this->modelCriteria->getProblemType());

        $this->say("Based on your dataset and problem type, we suggest using the {$bestAlgorithm} algorithm.");
        // Continue with the next step, such as generating code or asking for more preferences
    }

    private function getBestAlgorithm( $datasetType, $problemType ) {
        return 'xgboost';
    }


    public function run()
    {
        $this->askProjectName();
    }
}