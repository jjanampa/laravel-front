<?php

namespace WeblaborMx\Front\Inputs;

use Illuminate\Foundation\AliasLoader;

class MorphTo extends Input
{
	public $types;
	public $types_models;
	public $hide = false;

	public function types($types)
	{
		// Create the types as front objects
		$this->types = collect($types)->map(function($item) {
			return new $item($this->source);
		});

		// Detect aliases
		$loader = AliasLoader::getInstance();
		$aliases = collect($loader->getAliases());

		// Make a new array with all models of the types
		$this->types_models = $this->types->mapWithKeys(function($item) use ($aliases) 
		{
			$alias = $item->getModel();

			// If model has alias use alias instead of model
			if($aliases->contains($item->getModel())) {
				$alias = $aliases->search($item->getModel());
			}

			return [$item->label => $alias];
		});

		// Return object
		return $this;
	}

	private function getFrontOnClass($class)
	{
		return $this->types->filter(function($item) use ($class) {
			return $item->getModel() == $class;
		})->first();
	}

	public function getValue($object)
	{
		$relation = $this->column;
		if(!is_object($object->$relation)) {
			return '--';
		}

		$value = $object->$relation;
		if(!isset($value)) {
			return '--';
		}
		$class = get_class($value);
		if(!isset($this->types_models)) {
			abort(405, 'Please defines the possible types for the polimorfic values of '.$this->column.' with types() function');
		}
		$result = $this->types_models->search($class);
		if(!isset($result)) {
			return '--';
		}
		$front = $this->getFrontOnClass($class);
		if(is_null($front)) {
			abort(405, $class.' front is not defined on the types');
		}
		$this->link = $front->base_url.'/'.$value->getKey();
		$title_field = $front->title;
		return $value->$title_field;
	}

	public function form()
	{
		if( $this->hide && ((request()->filled($this->column.'_type') && request()->filled($this->column.'_id')) || $this->source=='edit') ) {
			return collect([
				Hidden::make($this->title, $this->column.'_type'),
				Hidden::make($this->title.' Id', $this->column.'_id')
			])->map(function($item) {
				return (string) $item->formHtml();
			})->implode('');
		}
		return collect([
			Select::make($this->title, $this->column.'_type')->options($this->types_models->flip()),
			Text::make($this->title.' Id', $this->column.'_id')
		])->map(function($item) {
			return $item->formHtml();
		})->implode('');
	}

	public function formHtml()
	{
		return $this->form();
	}

	public function hideWhenValuesSet()
	{
		$this->hide = true;
		return $this;
	}

	public function editRules($rules)
	{
		// Dont do anything if there isnt any rule to this element
		if(!isset($rules[$this->column])) {
			return;
		}

		$rule = $rules[$this->column];
		unset($rules[$this->column]);
		$rules[$this->column.'_type'] = $rule;
		$rules[$this->column.'_id'] = $rule;
		return $rules;
	}
}
