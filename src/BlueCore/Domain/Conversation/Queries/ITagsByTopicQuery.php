<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface ITagsByTopicQuery {
	public function fetch($topic_id);
}