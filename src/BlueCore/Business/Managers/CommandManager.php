Busin<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Language\Grammar;
use BlueFission\Automata\Language\SyntaxTreeWalker;
use BlueFission\Automata\Language\EntityExtractor;

class CommandManager extends Service {
	protected $_grammar;

	public function __construct(Grammar $grammar)
	{
		$this->_grammar = $grammar;
		parent::__construct();
	}

	public function parse($statement)
	{
		$statement = strtolower($statement);
		$tree = [];
		try {
			$tokens = $this->_grammar->tokenize($statement);
			$tree = $this->_grammar->parse($tokens);
		} catch (\Exception $e) {
			tell($e->getMessage(), 'botman');
		}

		$walker = new SyntaxTreeWalker($tree);
		$results = $walker->walk();

		if (!$results['subject']) {
			$results['subject'] = 'app';
		}

		$extractor = new EntityExtractor();
		$methods = [
		    'hex',
		    'email',
		    'web',
		    'date',
		    'time',
		    'name',
		    'object',
		    'number',
		    'adverb',
		    'mentions',
		    'tags',
		    'values',
		    'operation'
		];

		$entities = [];

		foreach ($methods as $method) {
		    $entities[$method] = $extractor->$method($statement);
		}
		$results['entities'] = $entities;

		return $results;
	}
}