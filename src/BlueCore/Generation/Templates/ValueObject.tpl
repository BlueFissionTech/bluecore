<?php
namespace Addons\{addon}\Domain;

use BlueFission\BlueCore\ValueObject;

class {class_name} extends ValueObject {
	{#each field=fields}public ${@current};{#endeach}
}