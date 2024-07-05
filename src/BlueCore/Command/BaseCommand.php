<?php
namespace BlueFission\BlueCore\Command;

use BlueFission\Services\Service;

class BaseCommand extends Service {
    protected $_entries = [];
    protected $_selected = null;
    protected $_page;
    protected $_perPage;
    protected $_subPage;
    protected $_perSubPage;
    protected $_key = 'name';
    protected $_id = 'id';
    protected $_name = 'sample';
    protected $_location = null;
    protected $_plural = null;
    protected $_itemName = null;
    protected $_subItemName = null;
    protected $_listAccessCommand;
    protected $_helpDetails = [];
    protected $_actions = ['list','show','next','previous','do','make','generate','delete','help'];

	public function __construct( )
	{
        $this->_location = $this->_location ?? "{$this->_name}_data";
        $this->_page = (int)store("_system.{$this->_name}.page");
        $this->_perPage = (int)store("_system.{$this->_name}.per_page");
        $this->_subPage = (int)store("_system.{$this->_name}.todo_page");
        $this->_perSubPage = (int)store("_system.{$this->_name}.per_todo_page");

        $this->_page = $this->_page > 0 ? $this->_page : 1;
        $this->_perPage = $this->_perPage > 0 ? $this->_perPage : 25;
        $this->_page = $this->_subPage > 0 ? $this->_subPage : 1;
        $this->_perPage = $this->_perSubPage > 0 ? $this->_perSubPage : 25;

        $item = $this->_itemName ?? $this->_name;

        $this->_listAccessCommand = $this->_listAccessCommand ?? "Type `show the {$this->_name} \"<{$this->_key}>\"` for any of the above ". $this->pluralize($item) ." to view more.";

		parent::__construct();
	}

    public function handle($behavior, $args)
    {
        $action = $behavior->name();

        $actions = [];
        foreach ($this->_actions as $verb) {
            $actions[] = $this->resolveAction($verb);
        }

        if (!in_array($action, $actions)) {
            $response = "I'm sorry, Invalid action for this resource ({$action})." . PHP_EOL . PHP_EOL;
            $response .= $this->help();
            
            $this->_response = $response;
            return;
        }

        switch ($action) {
            case 'do':
                $this->execute($args);
                break;
            case 'make':
                $this->create($args);
                break;
            case 'edit':
                $this->edit($args);
                break;
            case 'save':
                $this->save($args);
                break;
            case 'generate':
                $this->generate($args);
                break;
            case 'set':
                $this->set($args);
                break;
            case 'get':
                $this->get($args);
                break;
            case 'find':
                $this->find($args);
                break;
            case 'all':
                $this->all();
                break;
            case 'list':
                $this->list($args);
                break;
            case 'next':
                $this->next($args);
                break;
            case 'previous':
                $this->previous($args);
                break;
            case 'show':
                $this->show($args);
                break;
            case 'select':
                $this->select($args);
                break;
            case 'add':
                $this->add($args);
                break;
            case 'delete':
                $this->delete($args);
                break;
            default:
            case 'help':
                $this->_response = $this->help();
                break;
        }
    }

    protected function create($args)
    {
        $item = $this->_itemName ?? $this->_name;

        $response = "";
        if (count($args) > 0) {
            $name = $args[0];
            $entry = $this->retrieve($name);
            if ($entry === null) {
                $entry = $this->makeItem($name, $args);
                if ($entry == null) {
                    return;
                }
                $this->keep($entry);
                $response = ucfirst($item)." '{$name}' created'\n";
            } else {
                $response = ucfirst($item)." '{$name}' already exists.\n";
            }
        } else {
            $response = "Please provide a name for the new $item (ex: `create {$this->_name} \"<{$this->_key}>\"`).\n";
        }

        $this->_response = $response;
    }

    protected function generate($args)
    {
        $title = $args[0] ?? '';
        $prompt = $args[1] ?? '';

        $item = $this->_itemName ?? $this->_name;

        // Check if the OpenAI API key is set
        if (!env('OPEN_AI_API_KEY')) {
            $this->_response = "OpenAI API key is not set.";
            return;
        }

        $sampleJson = $this->jsonSample();

        // Initialize the OpenAIService
        $openAIService = new OpenAIService();

        // Generate the object using GPT-3
        $gpt3_prompt = "Sample:\n$sampleJson\n\nTitle:\"$title\" \n\nInstructions:\"$prompt\" \n\nUsing the example, generate a JSON file representing the described {$tiem}: ";
        $gpt3_response = $openAIService->complete($gpt3_prompt, ['max_tokens'=>3000]);

        // Check for errors in the response
        if (isset($gpt3_response['error'])) {
            $this->_response = "Error generating {$item}.";
            return;
        }

        // Get the generated object
        $object = trim($gpt3_response['choices'][0]['text']);

        $this->_response = $object;
    }

    protected function set($args)
    {
        $item = $this->_itemName ?? $this->_name;

        if (isset($args) && isset($args[0]) && isset($args[1])) {
            $key = $args[0] ?? null;
            $value = $args[1] ?? null;
            if ($this->_selected) {
                $entry = $this->_selected;
            } elseif (isset($args[2])) {
                $name = $args[2];
                $entry = $this->retrieve($name);
            } else {
                $this->_response = "Please provide a property to get the value of the {$item}.";
            }
            
            if (isset($entry)) {
                $entry[$key] = $value;
                $this->keep($entry);
                $this->_response = ucfirst($item)." '{$key}' has been set to '{$value}'.";
            } else {
                $this->_response = ucfirst($item)." '{$key}' not found.";
            }
        } else {
            $this->_response = "Please provide a property and a value to set the {$item}.";
        }
    }

    protected function get($args)
    {
        $item = $this->_itemName ?? $this->_name;
        if (isset($args) && isset($args[0])) {
            $key = $args[0] ?? '';
            if ($this->_selected) {
                $entry = $this->_selected;
            } elseif (isset($args[1])) {
                $name = $args[1];
                $entry = $this->retrieve($name);
            }

            if (isset($entry) && isset($entry[trim($key)]) && $entry[trim($key)] !== '') {
                $value = $entry[trim($key)];
                $this->_response = "The value of '{$key}' is '{$value}'.";
            } else {
                $this->_response = ucfirst($this->_name)." '{$key}' not found.";
            }
            return;
        } else {
            $this->_response = "Please provide a property to get the value of the {$item}.";
        }
    }

	protected function list($args)
	{
        $item = $this->_itemName ?? $this->_name;

        $this->refreshList();
        $response = "";
        if (count($args) > 0 && !is_numeric($args[0])) {
            $key = $args[0];
            $list = $this->retrieve($key);
            
            if (isset($list)) {
                $response = $this->subList($args);
            } else {
                $response = "'".ucfirst($item)." '{$key}' not found. Use `list all ".$this->pluralize($this->_name)."` to see available lists.\n";
            }
        } else {
            $response = $this->mainList($args);
        }
        $this->_response = $response;
	}

    protected function mainList($args)
    {
        $item = $this->_itemName ?? $this->_name;
        $plural = $this->pluralize($this->_name);
        $page = count($args) >= 1 ? (int)$args[0] : $this->_page;
        if (count($args) >= 2) {
            $this->_perPage = (int)$args[0];
            $page = (int)$args[1];
        }

        $this->_page = $page;
        $entries = array_keys($this->_entries);

        if ($entries !== null) {
            if ($this->_perPage < 1) {
                $this->_perPage = 25;
            }

            $total = count($entries);
            $totalPages = ceil($total / $this->_perPage);

            if ($page < 1) {
                $page = 1;
            } elseif ($page > $totalPages) {
                $page = $totalPages;
            }
            $this->_page = $page;

            $pageStart = ($page - 1) * $this->_perPage;
            $pageEnd = $pageStart + $this->_perPage;

            $i = 0;
            $count = 0;
            $response = "";

            $response = "Available ". $this->pluralize($item) .":" . PHP_EOL;
            foreach ($entries as $entry) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $response .= "  - " . $entry . PHP_EOL;
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} ". $this->pluralize($item) .". Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= $this->_listAccessCommand . PHP_EOL;
            $response .= "Type `previous ". $this->pluralize($this->_name) ."` or `next ". $this->pluralize($this->_name) ."` to move through pages." . PHP_EOL;
            $response .= "Type `help with ". $this->pluralize($this->_name) ."` for more options." . PHP_EOL;
        } else {
            $response = "No ". $this->pluralize($item) ." found.";
        }

        return $response;
    }

    protected function subList($args)
    {
        if (count($args) > 2) {
            $this->_perSubPage = (int)$args[1];
            $this->_subPage = (int)$args[2];
        } elseif (count($args) > 1) {
            $this->_subPage = (int)$args[1];
        }

        $listName = ($args[0] ?? null);
        $list = isset($args[0]) ? $this->retrieve($args[0]) : $this->_selected;
        
        $page = $this->_subPage;
        $perPage = $this->_perSubPage ;


        if (isset($list) && is_array($list)) {
            if ($perPage < 1) {
                $perPage = 25;
            }
            $total = count($list);
            $totalPages = ceil($total / $perPage);

            if ($page < 1) {
                $page = 1;
            } elseif ($page > $totalPages) {
                $page = $totalPages;
            }
            $this->_page = $page;

            $start = ($page - 1) * $perPage;
            $end = $start + $perPage - 1;
            $response = ucfirst($this->pluralize($this->_itemName))." in {$listName}:\n";
            $i = 0;
            $count = 0;
            foreach ($list as $title=>$entry) {
                if ($i >= $start && $i <= $end) {
                    $name = (is_string($entry) ? $entry : $entry['name']);
                    $response .= "- {$name}\n";
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} ".$this->pluralize($this->_itemName)." for {$listName}. Page {$page} of {$totalPages}. Type `previous {$this->_name} {$listName}` or `next {$this->_name} {$listName}` to move through pages.\n";
        } else {
            $response = "{$this->_itemName} '{$listName}' not found. Use `list all ".$this->pluralize($this->_name)."` to see available ".$this->pluralize($this->_itemName).".\n";
        }

        return $response;
    }

	protected function next($args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page += 1;

        $this->list([$this->_perPage, $this->_page]);
    }

    protected function previous($args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page -= 1;

        $this->list([$this->_perPage, $this->_page]);
    }

    protected function all()
    {
        $response = "";
        $entries = $this->getAll();
        foreach ($entry as $object) {
            $response .= $this->formatListItem($object) . PHP_EOL;
        }

        $this->_response = $response;
    }


    protected function find($args)
    {
        $item = $this->_itemName ?? $this->_name;
        $plural = $this->pluralize($this->_name);

        if (count($args) > 0) {
            $keyword = $args[0];
            $lists = [];
            $objects = [];

            $entries = $this->_entries;

            foreach ($entries as $name => $entry) {
                if (stripos($name, $keyword) !== false) {
                    $lists[] = $name;
                }

                if ( is_array($entry) ) {
                    foreach ($entry as $object) {
                        if ( is_array($object) ) {
                            if (stripos($object[$this->_key], $keyword) !== false) {
                                $objects[] = [
                                    'parent' => $name,
                                    $this->_key => $item[$this->_key]
                                ];
                            }
                        } else {
                            if (stripos($object, $keyword) !== false) {
                                $objects[] = [
                                    'parent' => $name,
                                    $this->_key => $object
                                ];
                            }
                        }
                    }
                }
            }
            if ( !empty($objects) ) {
                usort($objects, function ($a, $b) {
                    if ($a[$this->_key] === null) {
                        return 1;
                    }
                    if ($b[$this->_key] === null) {
                        return -1;
                    }
                    return $a[$this->_key] > $b[$this->_key] ? -1 : 1;
                });
            }

            $response = "";
            if ( count($lists) > 0 ) {
                $response .= "Found {$plural}:\n";
                foreach ($lists as $list) {
                    $response .= "- {$list}\n";
                }
            } else {
                $response .= "No {$plural} found matching keyword(s) '{$keyword}'.\n";
            }

            if ( $this->_subItemName ) {
                if ($response !== "") {
                    $response .= "\n";
                }
                if ( count($objects) > 0 ) {
                    $response .= "Found ".$this->pluralize($this->_subItemName).":\n";
                    foreach ($objects as $object) {
                        $response .= "- {$object[$this->_key]} (from {$item}: {$object['parent']})\n";
                    }
                } else {
                    $response .= "No ".$this->pluralize($this->_subItemName)." found matching keyword(s) '{$keyword}'.\n";
                }
            }
            $this->_response = $response;
        } else {
            $verb = 'find';
            foreach ($this->_verbs['find'] as $verb) {
                if ( in_array($verb, $this->_actions) ) {
                    break;
                }
            }
            
            $prep = $verb == 'search' ? 'for' : 'with';
            $this->_response = "Please provide a keyword to {$verb} by (ex: {$verb} {$plural} {$prep} \"<keyword>\").\n";
        }
    }

    protected function show($args)
    {
        $name = $args[0] ?? '';
        $item = $this->_itemName ?? $this->_name;

        if (!$name) {
            $this->_response = "You must provide the name of the {$this->_name} to display.";
            return;
        }

        $entry = $this->retrieve($name);

        if (!$entry) {
            $this->_response = ucfirst($item)." '$name' not found.";
            return;
        }

        $this->_response = $this->formatDetails($entry);
    }

    protected function select($args)
    {
        $id = $args[0] ?? '';
        $item = $this->_itemName ?? $this->_name;

        if (!$id) {
            $this->_response = "You must provide the id of the {$item} to display.";
            return;
        }

        $this->_selected = $this->retrieve($id);

        if (!$this->_selected) {
            $this->_response = ucfirst($item)." '$id' not found.";
            return;
        }

        $this->_response = $this->formatDetails($this->_selected);
    }

    protected function edit($args)
    {
        $name = $args[0] ?? '';
        $item = $this->_itemName ?? $this->_name;

        if (!$name) {
            $this->_response = "You must provide the name of the {$item} to edit.";
            return;
        }

        $entry = $this->retrieve([$name]);
        foreach ($this->_editSignature as $index => $property) {
            if (isset($args[$index])) {
                if (isset($args[$index])) {
                    $entry[$property] = $args[$index];
                }
            }
        }
        $this->keep($entry);

        $this->_response = ucfirst($item)." '{$name}' has been updated.";
    }

    protected function save($args)
    {
        $this->store();
        $item = $this->_itemName ?? $this->_name;

        $this->_response = ucfirst($item)." has been saved.";
    }

    protected function add($args)
    {
        $item = $this->_itemName ?? $this->_name;

        $response = "";
        if (count($args) == 1) {
            $parts = explode(' ', $args[0], 2);
        } elseif (count($args) > 1) {
            $parts = $args;
        } else {
            $parts = [];
        }

        if (count($parts) > 1) {
            $itemName = isset($parts[0]) ? $parts[0] : null;
            $this->_listName = $listName = $parts[1];
            $list = $this->retrieve($listName);
            if ($list !== null) {
                $itemExists = false;
                foreach ($list as $entry) {
                    $name = (is_string($entry) ? $entry : $entry['name']);
                    if ($name === $itemName) {
                        $itemExists = true;
                        break;
                    }
                }

                if (!$itemExists) {
                    $entry = $this->makeSubItem($itemName, $args);
                    $list[$itemName] = $entry;
                    $this->keep($list);
                    $response = "Item '{$itemName}' added to {$item} '{$listName}'.\n";
                } else {
                    $response = "Item '{$itemName}' already exists in list '{$listName}'.\n";
                }
            } else {
                $response = "List '{$listName}' not found. Use `list todo` to see available lists.\n";
            }
        } else {
            $response = "Please provide the list name and item name (ex: add \"<item description>\" to the {$this->_name} \"<list name>\").\n";
        }

        $this->_response = $response;
    }

    protected function help()
    {
        $item = $this->_itemName ?? $this->_name;

        $response = "Available {$item} commands:" . PHP_EOL;

        foreach ($this->_actions as $action) {
            $lines = $this->getHelp($action);

            foreach ($lines as $line) {
                $response .= $line . PHP_EOL;
            }
        }

        return $response;
    }

    protected function getHelp($action) 
    {
        $verb = $this->resolveAction($action);
        $a = $this->article($this->_name);
        $item = $this->_itemName ?? $this->_name;
        $a2 = $this->article($item);
        $singular = $this->_name;
        $plural = $this->pluralize($this->_name);
        $key = $this->_key ?? 'name';
        $id = $this->_id ?? 'id';

        $helpDetails = [
            'go' => [
                "  - {$action}: Go to the {$item} with the given id.",
                "      Usage `{$action} to the {$singular} \"<id>\" [\"<properties...>\"]`",
            ],
            'find' => [
                "  - {$action}: find the {$plural} that match a given search term.",
                "      Usage `{$action} {$plural} ".($action == 'find' ? 'with' : 'for')." \"<keyword>\"`",
            ],
            'do' => [
                "  - {$action}: Execute the {$item} with the given {$this->_key}.",
                "      Usage `{$action} the {$singular} \"<{$this->_key}>\" [ with \"<properties...>\"]`",
            ],
            'set' => [
                "  - {$action}: Set the {$item} property named 'name' to 'value'.",
                "      Usage `{$action} the {$singular} \"<name>\" to \"<value>\"`",
            ],
            'get' => [
                "  - {$action}: Get the {$item} property named 'name'.",
                "      Usage `{$action} the {$singular} \"<name>\"`",
            ],
            'generate' => [
                "  - {$action}: Create a new {$item} with the given description.",
                "      Usage `{$action} {$a} {$singular} from \"<description>\"`",
            ],
            'make' => [
                "  - {$action}: Create a new {$item} with the given properties.",
                "      Usage `{$action} {$a} {$singular} \"<{$this->_key}>\" [\"<properties...>\"]`",
            ],
            'open' => [
                "  - {$action}: Open the {$item} with the given {$this->_key}.",
                "      Usage `{$action} the {$singular} \"<{$this->_key}>\"`",
            ],
            'add' => [
                "  - {$action}: Add {$a2} {$item} to the selected {$this->_name}.",
                "      Usage `{$action} \"<{$item}>\" to the \"<{$this->_key}>\" {$singular}`",
            ],
            'edit' => [
                "  - {$action}: Edit the {$item} with the given {$this->_key}.",
                "      Usage `{$action} the {$singular} \"<{$this->_key}>\"`",
            ],
            'list' => [
                "  - {$action}: List all available {$plural}, with an optional pagination.",
                "      Usage `{$action} <perPage> {$plural} [on <page>]`",
                "      Usage `{$action} {$plural} by <perPage> [on <page>]`",
            ],
            'next' => [
                "  - {$action}: Show the next page of the {$plural} list.",
                "      Usage `{$action} <perPage> {$plural}`",
            ],
            'previous' => [
                "  - {$action}: Show the previous page of the {$plural} list.",
                "      Usage `{$action} <perPage> {$plural}`",
            ],
            'show' => [
                "  - {$action}: Show a detailed breakdown of the {$item} and its details.",
                "      Usage `{$action} the {$singular} \"<{$key}>\"`",
            ],
            'select' => [
                "  - {$action}: Select the {$item} with the given {$id}.",
                "      Usage `{$action} {$singular} by \"<{$id}>\"`",
            ],
            'delete' => [
                "  - {$action}: Delete the {$item} with the given {$key}.",
                "      Usage `{$action} the {$singular} \"<{$key}>\"`",
            ],
            'help' => [
                "  - {$action}: Show this help message.",
                "      Usage `{$action} with {$plural}`",
            ],

        ];

        if ( $this->_subItemName ) {
            $helpDetails['list'][] = "      Usage `{$action} \"<name>\" {$singular}`";
        }

        foreach ( $this->_helpDetails as $behavior => $details ) {
            $helpDetails[$behavior] = $this->_helpDetails[$behavior];
        }

        return $helpDetails[$verb] ?? [];
    }

    protected function getAll()
    {
        $this->refreshList();
        $entries = $this->_entries;
        $entries = array_keys($entries);

        return $entries;
    }

    protected function refreshList()
    {
        $this->_entries = store("_system.{$this->_name}.list") ?? [];
    }

    protected function retrieve($key)
    {
        $entry = $this->_entries[$key] ?? null;
        return $entry;
    }

    protected function keep($entry)
    {
        $this->_selected = $entry;
        $key = $this->_selected[$this->_key] ?? ( is_array($this->_entries) ? count($this->_entries) -1 : 0 );
        // die(var_dump($this->_listName, $this->_entries[$this->_listName], $key, $this->_key, $entry));

        if ( $this->_subItemName && $this->_listName && $this->_key ) {
            // $listName = store("_system.{$this->_name}.list_name") ?? '';
            $this->_entries[$this->_listName] = $this->_selected;
            $this->_listName = null;
        } elseif ( $this->_key ) {
            $this->_entries[$key] = $this->_selected;
        } else {
            $this->_entries = $this->_selected;
        }

        $this->store();
    }

    protected function store()
    {
        store("_system.{$this->_name}.list", $this->_entries);
    }

    protected function formatListItem($entry)
    {
        return "  - " . $entry;
    }

    protected function resolveAction($action)
    {
        foreach ($this->_verbs as $verb => $aliases) {
            if (in_array($action, $aliases)) {
                return $verb;
            }
        }

        return $action;
    }

    protected function formatDetails($entry)
    {
        $response = "Name: {$entry['name']}" . PHP_EOL;
        $response .= "Description: {$entry['description']}" . PHP_EOL;
        return $response;
    }

    protected function makeItem($itemName, $args)
    {
        $key = $this->_key ?? null;
        return $key ? [$key=>$itemName] : $itemName;
    }

    protected function makeSubItem($subItemName, $args)
    {
        $key = $this->_key ?? null;
        return $key ? [$key=>$subItemName] : $subItemName;
    }

    public function __destruct()
    {
        store("_system.{$this->_name}.page", $this->_page);
        store("_system.{$this->_name}.per_page", $this->_perPage);
        store("_system.{$this->_name}.sub_page", $this->_subPage);
        store("_system.{$this->_name}.per_sub_page", $this->_perSubPage);
        // store("_system.{$this->_name}.selected", $this->_selected[$this->_key] ?? null);
    }

    public function pluralize($text)
    {
        return (!empty($this->_plural) && $text == $this->_name) ? $this->_plural : pluralize($text);
    }

    public function article($text)
    {
        $a = (in_array(substr($text, 0, 1), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a');
        return $a;
    }

    private $_verbs = [
        'do' => ['do','perform','enact', 'run', 'use'],
        'go' => ['go', 'navigate'],
        'make' => ['make', 'create'],
        'get' => ['get', 'retrieve'],
        'set' => ['set', 'change', 'assign'],
        'update' => ['update'],
        'delete' => ['delete', 'remove'],
        'generate' => ['generate', 'build'],
        'message' => ['message'],
        'open' => ['open'],
        'input' => ['input', 'enter'],
        'send' => ['send', 'submit'],
        'edit' => ['edit'],
        'add' => ['add','append'],
        'download' => ['download'],
        'show' => ['show', 'display', 'view'],
        'list' => ['list'],
        'find' => ['find', 'search', 'locate'],
        'previous' => ['previous', 'back'],
        'next' => ['next', 'forward'],
        'select' => ['select', 'choose', 'take', 'check'],
        'help' => ['help', 'assist'],
        'save' => ['save', 'preserve', 'store'],
        'cancel' => ['cancel', 'stop', 'nevermind'],
        'prompt' => ['prompt', 'ask', 'inquire'],
        'less' => ['less', 'fewer'],
        'more' => ['more', 'greater'],
        //Special system root commands
        'scroll' => ['scroll'],
        'copy' => ['copy', 'duplicate'],
        'cut' => ['cut', 'remove'],
        'paste' => ['paste', 'insert'],
        'move' => ['move', 'shift'],
    ];
}