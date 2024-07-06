<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\BlueCore\Domain\Conversation\Language;

interface ILanguageRepository
{
    public function find($id);
    public function findByName($name);
    public function save(Language $language);
    public function remove(Language $language);
}