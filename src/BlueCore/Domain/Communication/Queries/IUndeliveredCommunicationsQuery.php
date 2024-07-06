<?php
namespace BlueFission\BlueCore\Domain\Communication\Queries;

interface IUndeliveredCommunicationsQuery {
	public function fetch();
}