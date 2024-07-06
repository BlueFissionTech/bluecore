<?php
namespace BlueFission\BlueCore\Domain\Communication\Repositories;

use BlueFission\BlueCore\Domain\Communication\Communication;

interface ICommunicationRepository
{
    public function find($id);
    public function save(Communication $communication, array $attributes = [], array $parameters = []);
    public function remove($id);
}