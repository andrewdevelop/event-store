<?php

namespace Core\EventStore\Exceptions;

use Exception;

class NotFoundException extends Exception
{
	public function __construct($aggregate_id = null, $code = 404, Exception $previous = null)
	{
		$message = "Stream '$aggregate_id' not found.";
		parent::__construct($message, $code, $previous);
	}
}