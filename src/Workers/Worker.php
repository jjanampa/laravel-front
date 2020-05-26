<?php

namespace WeblaborMx\Front\Workers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class Worker
{
	use AuthorizesRequests;

	public $is_input = false;
	public $front;

	public function __construct($front, $column = null, $extra = null, $source = null)
	{
		$front = '\App\Front\\'.$front;
		$this->source = $source;
		$this->front = getFront($front, $this->source);
	}

	public static function make($title = null, $column = null, $extra = null) 
	{
		$source = session('source');
		return new static($title, $column, $extra, $source);
	}

	public function handle()
	{
		//
	}

	public function execute()
	{
		try {
			return $this->handle();
		} catch (ValidationException $e) {
        	return collect($e->errors())->flatten(1)->implode('<br />');
        } catch (\Exception $e) {
			return $e->getMessage();
		}
	}
}
