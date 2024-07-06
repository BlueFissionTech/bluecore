<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface ITopicRoutesByTopicQuery {
	public function fetch($topic_id);
}