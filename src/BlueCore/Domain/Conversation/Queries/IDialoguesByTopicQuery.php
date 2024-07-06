<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface IDialoguesByTopicQuery {
	public function fetch($topic_id);
}