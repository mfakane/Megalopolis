<?php
namespace Megalopolis;

class ApplicationException extends \Exception
{
	public int $httpCode;
	public ?array $data = null;
	
	function __construct(string $message, int $httpCode = 500)
	{
		parent::__construct($message);
		$this->httpCode = $httpCode;
	}
}
?>
