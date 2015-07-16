<?php

namespace moe;

//! View handler
class View extends Prefab {

	protected
		//! Template file
		$view,
		//! post-rendering handler
		$trigger,
		//! Nesting level
		$level=0;

	/**
	*	Encode characters to equivalent HTML entities
	*	@return string
	*	@param $arg mixed
	**/
	function esc($arg) {
		$fw=Base::instance();
		return $fw->recursive($arg,
			function($val) use($fw) {
				return is_string($val)?$fw->encode($val):$val;
			}
		);
	}

	/**
	*	Decode HTML entities to equivalent characters
	*	@return string
	*	@param $arg mixed
	**/
	function raw($arg) {
		$fw=Base::instance();
		return $fw->recursive($arg,
			function($val) use($fw) {
				return is_string($val)?$fw->decode($val):$val;
			}
		);
	}

	/**
	*	Create sandbox for template execution
	*	@return string
	*	@param $hive array
	**/
	protected function sandbox(array $hive=NULL) {
		$this->level++;
		$fw=Base::instance();
		$implicit=false;
		if ($hive === null) {
			$implicit=true;
			$hive=$fw->hive();
		}
		if ($this->level<2 || $implicit) {
			if ($fw->get('ESCAPE'))
				$hive=$this->esc($hive);
			if (isset($hive['ALIASES']))
				$hive['ALIASES']=$fw->build($hive['ALIASES']);
		}
		unset($fw, $implicit);
		extract($hive);
		unset($hive);
		ob_start();
		require($this->view);
		$this->level--;
		return ob_get_clean();
	}

	/**
	*	Render template
	*	@return string
	*	@param $file string
	*	@param $mime string
	*	@param $hive array
	*	@param $ttl int
	**/
	function render($file,$mime='text/html',array $hive=NULL,$ttl=0) {
		$fw=Base::instance();
		$cache=Cache::instance();
		$cached=$cache->exists($hash=$fw->hash($file),$data);
		if ($cached && $cached[0]+$ttl>microtime(TRUE))
			return $data;
		foreach ($fw->split($fw->get('UI').';./') as $dir)
			if (is_file($this->view=$fw->fixslashes($dir.$file))) {
				if (isset($_COOKIE[session_name()]))
					@session_start();
				$fw->sync('SESSION');
				if ($mime && PHP_SAPI!='cli' && !headers_sent())
					header('Content-Type: '.$mime.'; '.
						'charset='.$fw->get('ENCODING'));
				$data=$this->sandbox($hive);
				if(isset($this->trigger['afterrender']))
					foreach($this->trigger['afterrender'] as $func)
						$data=$fw->call($func,$data);
				if ($ttl)
					$cache->set($hash,$data);
				return $data;
			}
		user_error(sprintf(Base::E_Open,$file),E_USER_ERROR);
	}

	/**
	*	post rendering handler
	*	@param $func callback
	*/
	function afterrender($func) {
		$this->trigger['afterrender'][]=$func;
	}

}
