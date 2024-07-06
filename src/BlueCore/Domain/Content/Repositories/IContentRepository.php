<?php
namespace BlueFission\BlueCore\Domain\Content\Repositories;

use BlueFission\BlueCore\Domain\Content\Content;

interface IContentRepository
{
    public function find($id);
    public function save(Content $addon);
    public function remove($id);
}