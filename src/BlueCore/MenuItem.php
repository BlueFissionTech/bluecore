<?php
namespace BlueFission\BlueCore;

use BlueFission\HTML\Template;

class MenuItem
{
    protected $_id;
    protected $_label;
    protected $_action;
    protected $_role;
    protected $_group;
    protected $_permission;
    protected $_template;

    public function __construct($label, $action, $role = null, $group = null, $permission = null)
    {
        $this->_id = slugify($label);
        $this->_label = $label;
        $this->_action = $action;
        $this->_role = $role;
        $this->_group = $group;
        $this->_permission = $permission;
    }

    public function setTemplate($template)
    {
    	$this->_template = $template;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getLabel()
    {
        return $this->_label;
    }

    public function getAction()
    {
        return $this->_action;
    }

    public function getRole()
    {
        return $this->_role;
    }

    public function getGroup()
    {
        return $this->_group;
    }

    public function getPermission()
    {
        return $this->_permission;
    }

    public function render()
    {
		$template = new Template();
		
		// Configure the directory for template modules
		// $module_path = $theme->location.'modules';
		// $template->config('module_directory', $module_path);

		// Load the template file
		$template->load($this->_template);

		// Pass the data to the template
		$template->field([
			'id'=>$this->_id,
			'label'=>$this->_label,
			'action'=>$this->_action,
		]);

		// Render the template
		$output = $template->render();

        // die($output);
        // Implement your rendering logic here
        // return "<li>{$this->_label}</li>";
        return $output;
    }
}