<?php
namespace BlueFission\BlueCore\Domain\Repositories;

interface IGenericRepository
{
    public function find($id);
    public function save($entity);
    public function remove($id);
}