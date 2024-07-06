<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface IDialoguesByKeywordsQuery {
	public function fetch($phrase);
}