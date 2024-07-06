<?php
namespace BlueFission\BlueCore\Domain\Communication\Repositories;

use BlueFission\BlueCore\Domain\Communication\CommunicationStatus;

interface ICommunicationStatusRepository
{
    public function find($id);
    public function findByName($name);
    public function save(CommunicationStatus $communication_status);
    public function remove($id);
}