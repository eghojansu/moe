<?php

namespace moe\tools;

//! Unit test kit
class Test {

	//@{ Reporting level
	const
		FLAG_False=0,
		FLAG_True=1,
		FLAG_Both=2;
	//@}

	protected
		//! Test results
		$data=array(),
		//! Success indicator
		$passed=TRUE;

	/**
	*	Return test results
	*	@return array
	**/
	function results() {
		return $this->data;
	}

	/**
	*	Return FALSE if at least one test case fails
	*	@return bool
	**/
	function passed() {
		return $this->passed;
	}

	/**
	*	Evaluate condition and save test result
	*	@return object
	*	@param $cond bool
	*	@param $text string
	**/
	function expect($cond,$text=NULL) {
		$out=(bool)$cond;
		if ($this->level==$out || $this->level==self::FLAG_Both) {
			$data=array('status'=>$out,'text'=>$text,'source'=>NULL);
			foreach (debug_backtrace() as $frame)
				if (isset($frame['file'])) {
					$data['source']=Base::instance()->
						fixslashes($frame['file']).':'.$frame['line'];
					break;
				}
			$this->data[]=$data;
		}
		if (!$out && $this->passed)
			$this->passed=FALSE;
		return $this;
	}

	/**
	*	Append message to test results
	*	@return NULL
	*	@param $text string
	**/
	function message($text) {
		$this->expect(TRUE,$text);
	}

	/**
	*	Class constructor
	*	@return NULL
	*	@param $level int
	**/
	function __construct($level=self::FLAG_Both) {
		$this->level=$level;
	}

}
