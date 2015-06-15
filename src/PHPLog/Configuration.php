<?php

namespace PHPLog;

/**
 * Global Configuration class used for configuration.
 * variables can be accessed with either the '->' operator or as an array.
 * @version 1
 * @author Jack Timblin
 */
class Configuration implements \ArrayAccess, \Countable {
	
	/**
	 * Constructor - assigns all of the variables to this configuration object
	 * from the array.
	 * @param array $config the configuration to give this configuration class.
	 */
	public function __construct($config) {
		foreach($config as $key => $value) {
			$this->offsetSet($key, $value);
		}
	}

	/**
	 * @see \ArrayAccess::offsetExists()
	 */
	public function offsetExists($index) {
		$index = strval($index);
		return isset($this->{$index});
	}

	/**
	 * @see \ArrayAccess::offsetGet()
	 */
	public function offsetGet($index) {
		$index = strval($index);

		if(!$this->offsetExists($index)) {
			return null;
		}

		return $this->{$index};
	}

	/**
	 * gets a variable assigned to the config, if its not defined
	 * the default value is returned.
	 * @param mixed $index the value to get.
	 * @param mixed $defaultValue the value to return if the $index value is not defined.
	 * @param \Closure|null $comparable a function which is ran on the var to determine its
	 * validility. The closure must return a boolean value to determine if the value is correct.
	 * @return mixed the $index value or if that not defined the $defaultValue
	 */
	public function get($index, $defaultValue = null, $comparable = null) {
		if(!$this->offsetExists($index)) {
			return $defaultValue;
		}

		$var = $this->offsetGet($index);

		//get the result from the comparable if its an instanceof a closure.
		if($comparable instanceof \Closure) {
			$result = $comparable($var);
			if(!is_bool($result) || !$result) {
				return $defaultValue;
			}
		}

		return $var;
	}

	/**
	 * @see \ArrayAccess::offsetSet()
	 */
	public function offsetSet($index, $value) {
		$index = strval($index);
		$this->{$index} = (is_array($value)) ? new Configuration($value) : $value;
	}

	/**
	 * @see \ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($index) {
		$index = strval($index);
		$this->{$index} = null;
	}

	/**
	 * @see \Countable::count()
	 */
	public function count() {
		return count(get_object_vars($this));
	}

}