<?php
namespace BlueFission\BlueCore;

use BlueFission\HTML\Template;

class Menu
{
    protected $_label;
    protected $_id;
    protected $_items = [];
    protected $_theme;
    protected $_template;
    protected $_itemTemplate;

    public function __construct($label, $theme = null, $template = null, $itemTemplate = null)
    {
        $this->_label = $label;
        $this->_id = slugify($label);
        $this->_theme = $theme;
        $this->_template = $template;
        $this->_itemTemplate = $itemTemplate;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getLabel()
    {
        return $this->_label;
    }

    public function addItem(MenuItem|Menu $item)
    {
        if ($item instanceof MenuItem) {
            if ($this->_itemTemplate) {
                $app = instance();
                $theme = $app->theme($this->_theme);
                $path = $theme->location.$this->_itemTemplate;
                $item->setTemplate($path);
            }
        } elseif ($item instanceof Menu) {

        }
        $this->_items[$item->getId()] = $item;
    }

    public function getItem($itemId)
    {
        if (array_key_exists($itemId, $this->_items)) {
            return $this->_items[$itemId];
        }
        return null;
    }

    public function getItems()
    {
        return $this->_items;
    }

    public function render()
    {

        // Implement your rendering logic here
        $renderedItems = [];
        $renderedItems = array_map(function($item) { return $item->render(); }, $this->_items);
        $renderedItems = implode('', $renderedItems);

        $app = instance();
        $theme = $app->theme($this->_theme);
        $path = $theme->location.$this->_template;
            
        $template = new Template();
        // Load the template file
        $template->load($path);

        // Pass the data to the template
        $template->field([
            'id'=>$this->_id,
            'label'=>$this->_label,
            'children'=>$renderedItems,
        ]);

        // Render the template
        $output = $template->render();
        // return "<ul>{$renderedItems}</ul>";
        return $output;
    }
}