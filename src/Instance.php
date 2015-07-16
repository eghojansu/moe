<?php

namespace moe;

//! Legacy mode enabler
class Instance
{

	static
		//! Framework instance
		$fw;

	/**
	*	Forward function calls to framework
	*	@return mixed
	*	@param $func callback
	*	@param $args array
	**/
	static function __callstatic($func,array $args) {
		if (!self::$fw)
			self::$fw=Base::instance();
		return call_user_func_array(array(self::$fw,$func),$args);
	}

}
