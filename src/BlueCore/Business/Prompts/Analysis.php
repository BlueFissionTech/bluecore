<?php
namespace BlueFission\BlueCore\Business\Prompts;

use BlueFission\Automata\LLM\Prompt;

class Analysis extends Prompt
{
	protected $_fields = ['input', 'day', 'location'];
	protected $_template = "

Question - What day is it?
Answer - {day}
Question - Where is this happening?
Answer - {location}.
Question - {input}
Answer - ";
}