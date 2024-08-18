<?php
namespace BlueFission\Wise\Res;

use BlueFission\Services\Service;
use BlueFission\BlueCore\Domain\Conversation\Repositories\IMessageRepository;
use BlueFission\BlueCore\Domain\Conversation\Queries\IMessagesQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\IMessagesByKeywordQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\IMessagesByTimestampQuery;
use BlueFission\BlueCore\Domain\Conversation\Queries\IMessagesByUserIdQuery;

class MessageResource extends Service
{
    private $_repository;
    private $_keywordQuery;
    private $_timestampQuery;
    private $_userIdQuery;

    public function __construct(
        IMessageRepository $repository,
        IMessagesByKeywordQuery $keywordQuery,
        IMessagesQuery $messageQuery,
        IMessagesByTimestampQuery $timestampQuery,
        IMessagesByUserIdQuery $userIdQuery
    ) {
        $this->_repository = $repository;
        $this->_messageQuery = $messageQuery;
        $this->_keywordQuery = $keywordQuery;
        $this->_timestampQuery = $timestampQuery;
        $this->_userIdQuery = $userIdQuery;

        parent::__construct();
    }

    public function handle($behavior, $args)
    {
        $action = $behavior->name();
        $isAdmin = false; // Set this according to the user's role
        $response = 'Invalid action specified.';

        switch ($action) {
            case 'show':
                $response = $this->list();
                break;
            case 'find':
            case 'search':
                $response = $this->find($args['type'], $args['value'], $isAdmin);
                break;
            case 'help':
                $response = $this->help();
                break;
            default:
                $response = $this->help();
                // throw new \Exception('Invalid action');
        }

        $this->_response = $response;
    }

    private function list()
    {
        // Retrieve the most recent 100 messages
        $messages = $this->_messageQuery->fetchRecent(100);

        // Format messages as plain text transcript
        $transcript = '';
        foreach ($messages as $message) {
            $transcript .= "{$message['timestamp']} {$message['username']}: {$message['text']}\n";
        }
        return $transcript;
    }

    private function find($type, $value, $isAdmin)
    {
        $limit = 50;
        $messages = [];

        switch ($type) {
            case 'keyword':
                $messages = $this->_keywordQuery->fetch($value, $limit);
                break;
            case 'timestamp':
                $messages = $this->_timestampQuery->fetch($value, $limit);
                break;
            case 'user_id':
                if (!$isAdmin) {
                    throw new \Exception('Unauthorized access');
                }
                $messages = $this->_userIdQuery->fetch($value, $limit);
                break;
            default:
                throw new \Exception('Invalid search type');
        }
        return $messages;
    }

    private function help()
    {
        return "Message Manager Help:\n\n"
            . "Commands available:\n"
            . "  - list: Retrieve the most recent 100 messages.\n"
            . "  - find <type> <value>: Search for messages based on the type and value provided. Available types are 'keyword', 'timestamp', and 'user_id' (admin only).\n"
            . "  - help: Show this help message.\n\n"
            . "Examples:\n"
            . "  - To retrieve the most recent 100 messages: 'message list'\n"
            . "  - To search for messages containing a keyword: 'message find keyword example'\n"
            . "  - To search for messages sent after a specific timestamp: 'message find timestamp 2023-04-10T12:34:56'\n"
            . "  - To search for messages sent by a specific user (admin only): 'message find user_id 12345'\n";
    }
}
