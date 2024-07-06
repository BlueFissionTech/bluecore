<?php
namespace BlueFission\BlueCore\Domain\AddOn\Repositories;

use BlueFission\BlueCore\Domain\AddOn\AddOn;

interface IAddOnRepository
{
    public function find($id);
    public function save(AddOn $addon);
    public function remove($id);
}