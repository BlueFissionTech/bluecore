<?php
namespace AddOns\{addon}\Domain\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class {class_name}Model extends Model {
	protected $_table = '{table_name}';
	protected $_fields = [
		{#each field=fields}'{@current}',{#endeach}
	];

	{#each relationship=relationships}
	public function {relationship.name}()
	{
		{#if(relationship.type='descendents')}
		return $this->descendants('AddOns\{addon}\Domain\Models\{relationship.class_name}', '{primary_key}');
		{#endif}
		{#if(relationship.type='ancestors')}
		return $this->ancestors('AddOns\{addon}\Domain\Models\{relationship.class_name}', '{relationship.key}');
		{#endif}
		{#if(relationship.type='associates')}
		return $this->associates('AddOns\{addon}\Domain\Models\relationship.class_name}', 'AddOns\{addon}\Domain\Models\{relationship.pivot}', '{relationship.key}', '{relationship.foo}', 'relationship.bar');
		{#endif}
	}

	{#endeach}
}