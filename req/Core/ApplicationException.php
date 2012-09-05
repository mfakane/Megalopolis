<?php
class ApplicationException extends Exception
{
	public $httpCode;
	public $data;
	
	function __construct($message, $httpCode = 500)
	{
		parent::__construct($message);
		$this->httpCode = $httpCode;
	}
}
?>