<?php
namespace BlueFission\BlueCore\Domain\Communication\Repositories;

use BlueFission\BlueCore\Domain\Communication\CommunicationType;

interface ICommunicationTypeRepository
{
    public function find($id);
    public function findByName($name);
    public function save(CommunicationType $communication_type);
    public function remove($id);
}