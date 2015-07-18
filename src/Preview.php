<?php

namespace moe;

//! Lightweight template engine
class Preview extends View {

	protected
		//! MIME type
		$mime,
		//! token filter
		$filter = array(
			'esc'=>'$this->esc',
			'raw'=>'$this->raw',
			'alias'=>'moe\Instance::alias',
			'format'=>'moe\Instance::format',
			'decode'=>'moe\Instance::decode',
			'encode'=>'moe\Instance::encode',
			'url'=>'moe\Instance::siteUrl',
		);

	/**
	*	Convert token to variable
	*	@return string
	*	@param $str string
	**/
	function token($str) {
		return trim(preg_replace('/\{\{(.+?)\}\}/s',trim('\1'),
			Base::instance()->compile($str)));
	}

	/**
	*	register token filter
	*	@param string $key
	*	@param string $func
	*	@return array
	*/
	function filter($key=NULL,$func=NULL) {
		if (!$key)
			return array_keys($this->filter);
		if (!$func)
			return $this->filter[$key];
		$this->filter[$key]=$func;
	}

	/**
	*	Assemble markup
	*	@return string
	*	@param $node string
	**/
	protected function build($node) {
		$self=$this;
		return preg_replace_callback(
			'/\{\-(.+?)\-\}|\{\{(.+?)\}\}(\n+)?/s',
			function($expr) use($self) {
				if ($expr[1])
					return $expr[1];
				$str=trim($self->token($expr[2]));
				if (preg_match('/^([^|]+?)\h*\|(\h*\w+(?:\h*[,;]\h*\w+)*)/',
					$str,$parts)) {
					$str=$parts[1];
					foreach (Base::instance()->split($parts[2]) as $func)
						$str=$self->filter($func).'('.$str.')';
				}
				return '<?php echo '.$str.'; ?>'.
					(isset($expr[3])?$expr[3]."\n":'');
			},
			preg_replace_callback(
				'/\{~(.+?)~\}/s',
				function($expr) use($self) {
					return '<?php '.$self->token($expr[1]).' ?>';
				},
				$node
			)
		);
	}

	/**
	*	Render template string
	*	@return string
	*	@param $str string
	*	@param $hive array
	**/
	function resolve($str,array $hive=NULL) {
		if (!$hive)
			$hive=Base::instance()->hive();
		extract($hive);
		ob_start();
		eval(' ?>'.$this->build($str).'<?php ');
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
		if (!is_dir($tmp=$fw->get('TEMP')))
			mkdir($tmp,Base::MODE,TRUE);
		foreach ($fw->split($fw->get('UI')) as $dir) {
			$cached=$cache->exists($hash=$fw->hash($dir.$file),$data);
			if ($cached && $cached[0]+$ttl>microtime(TRUE))
				return $data;
			if (is_file($view=$fw->fixslashes($dir.$file))) {
				if (!is_file($this->view=($tmp.
					$fw->hash($fw->get('ROOT').$fw->get('BASE')).'.'.
					$fw->hash($view).'.php')) ||
					filemtime($this->view)<filemtime($view)) {
					// Remove PHP code and comments
					$text=preg_replace(
						'/(?<!["\'])\h*<\?(?:php|\s*=).+?\?>\h*'.
						'(?!["\'])|\{\*.+?\*\}/is','',
						$fw->read($view));
					if (method_exists($this,'parse'))
						$text=$this->parse($text);
					$fw->write($this->view,$this->build($text));
				}
				if (isset($_COOKIE[session_name()]))
					@session_start();
				$fw->sync('SESSION');
				if ($mime && PHP_SAPI!='cli' && !headers_sent())
					header('Content-Type: '.($this->mime=$mime).'; '.
						'charset='.$fw->get('ENCODING'));
				$data=$this->sandbox($hive);
				if(isset($this->trigger['afterrender']))
					foreach ($this->trigger['afterrender'] as $func)
						$data = $fw->call($func, $data);
				if ($ttl)
					$cache->set($hash,$data);
				return $data;
			}
		}
		user_error(sprintf(Base::E_Open,$file),E_USER_ERROR);
	}

}
