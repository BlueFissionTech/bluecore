<?php
namespace BlueFission\BlueCore\Generation;

use BlueFission\HTML\Form;
use BlueFission\HTML\XML;
use BlueFission\HTML\Table;

class HTMLGenerator implements IHTMLGenerator {
    private $html;
    
    public function __construct($config = []) {
        // set up initial HTML document based on config options
        $this->html = "<!DOCTYPE html>\n";
        $this->html .= "<html>\n";
        // add any additional config options here
        $this->html .= "</html>";
    }
    
    public function addElement($element, $attributes = [], $content = '') {
        // add element to HTML document with given attributes and content
        $attr_str = '';
        foreach ($attributes as $attr => $value) {
            $attr_str .= "$attr=\"$value\" ";
        }
        $this->html .= "<$element $attr_str>$content</$element>\n";
    }
    
    public function addScript($src, $attributes = []) {
        // add script tag to HTML document with given source and attributes
        $attr_str = '';
        foreach ($attributes as $attr => $value) {
            $attr_str .= "$attr=\"$value\" ";
        }
        $this->html .= "<script src=\"$src\" $attr_str></script>\n";
    }
    
    public function addStylesheet($href, $attributes = []) {
        // add stylesheet link tag to HTML document with given href and attributes
        $attr_str = '';
        foreach ($attributes as $attr => $value) {
            $attr_str .= "$attr=\"$value\" ";
        }
        $this->html .= "<link rel=\"stylesheet\" href=\"$href\" $attr_str>\n";
    }
        
    public function addForm($data, $action = '', $method = 'POST') {
        $formHTML = Form::open($action, $name, $method);
        
        foreach ($data as $name => $value) {
            $label = ucfirst(str_replace('_', ' ', $name));
            $type = 'text';

            // Check for specific field types
            if (strpos($name, 'email') !== false) {
                $type = 'email';
            } elseif (strpos($name, 'password') !== false) {
                $type = 'password';
            } elseif (strpos($name, 'date') !== false) {
                $type = 'date';
            } elseif (strpos($name, 'time') !== false) {
                $type = 'time';
            }

            $output .= Form::field($name, $value, $label, $type);
        }
        
        $formHTML .= Form::close();
        
        $this->html .= $formHTML;
    }

    public function addTable($data, $config = []) {
        $table = new Table($config);
        $table->content($data);
        $this->html .= $table->render();
    }

    public function addData($data) {
        // recursively build HTML from data array
        foreach ($data as $tag) {
            $tag_name = isset($tag['name']) ? $tag['name'] : '';
            $tag_attrs = isset($tag['attrs']) ? $tag['attrs'] : [];
            $tag_content = isset($tag['content']) ? $tag['content'] : '';
            if ($tag_name) {
                $this->addElement($tag_name, $tag_attrs, $tag_content);
                if (isset($tag['child'])) {
                    $this->addData($tag['child']);
                }
            }
        }
    }

    public function render() {
        // return complete HTML document as a string
        return $this->html;
    }

    public function generate() {
        $this->render();
    }
}