<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface IFactsByKeywordsQuery {
	public function fetch($input);
}