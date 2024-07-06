<?php
namespace BlueFission\BlueCore\Domain\Communication\Repositories;

use BlueFission\BlueCore\Domain\Communication\CommunicationChannel;

interface ICommunicationChannelRepository
{
    public function find($id);
    public function findByName($name);
    public function save(CommunicationChannel $communication_channel);
    public function remove($id);
}