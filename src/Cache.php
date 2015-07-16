<?php

namespace moe;

//! Cache engine
class Cache extends Prefab {

	protected
		//! Cache DSN
		$dsn,
		//! Prefix for cache entries
		$prefix,
		//! MemCache or Redis object
		$ref;

	/**
	*	Return timestamp and TTL of cache entry or FALSE if not found
	*	@return array|FALSE
	*	@param $key string
	*	@param $val mixed
	**/
	function exists($key,&$val=NULL) {
		$fw=Base::instance();
		if (!$this->dsn)
			return FALSE;
		$ndx=$this->prefix.'.'.$key;
		$parts=explode('=',$this->dsn,2);
		switch ($parts[0]) {
			case 'apc':
			case 'apcu':
				$raw=apc_fetch($ndx);
				break;
			case 'redis':
				$raw=$this->ref->get($ndx);
				break;
			case 'memcache':
				$raw=memcache_get($this->ref,$ndx);
				break;
			case 'wincache':
				$raw=wincache_ucache_get($ndx);
				break;
			case 'xcache':
				$raw=xcache_get($ndx);
				break;
			case 'folder':
				$raw=$fw->read($parts[1].$ndx);
				break;
		}
		if (!empty($raw)) {
			list($val,$time,$ttl)=(array)$fw->unserialize($raw);
			if ($ttl===0 || $time+$ttl>microtime(TRUE))
				return array($time,$ttl);
			$val=null;
			$this->clear($key);
		}
		return FALSE;
	}

	/**
	*	Store value in cache
	*	@return mixed|FALSE
	*	@param $key string
	*	@param $val mixed
	*	@param $ttl int
	**/
	function set($key,$val,$ttl=0) {
		$fw=Base::instance();
		if (!$this->dsn)
			return TRUE;
		$ndx=$this->prefix.'.'.$key;
		$time=microtime(TRUE);
		if ($cached=$this->exists($key))
			list($time,$ttl)=$cached;
		$data=$fw->serialize(array($val,$time,$ttl));
		$parts=explode('=',$this->dsn,2);
		switch ($parts[0]) {
			case 'apc':
			case 'apcu':
				return apc_store($ndx,$data,$ttl);
			case 'redis':
				return $this->ref->set($ndx,$data,array('ex'=>$ttl));
			case 'memcache':
				return memcache_set($this->ref,$ndx,$data,0,$ttl);
			case 'wincache':
				return wincache_ucache_set($ndx,$data,$ttl);
			case 'xcache':
				return xcache_set($ndx,$data,$ttl);
			case 'folder':
				return $fw->write($parts[1].$ndx,$data);
		}
		return FALSE;
	}

	/**
	*	Retrieve value of cache entry
	*	@return mixed|FALSE
	*	@param $key string
	**/
	function get($key) {
		return $this->dsn && $this->exists($key,$data)?$data:FALSE;
	}

	/**
	*	Delete cache entry
	*	@return bool
	*	@param $key string
	**/
	function clear($key) {
		if (!$this->dsn)
			return;
		$ndx=$this->prefix.'.'.$key;
		$parts=explode('=',$this->dsn,2);
		switch ($parts[0]) {
			case 'apc':
			case 'apcu':
				return apc_delete($ndx);
			case 'redis':
				return $this->ref->del($ndx);
			case 'memcache':
				return memcache_delete($this->ref,$ndx);
			case 'wincache':
				return wincache_ucache_delete($ndx);
			case 'xcache':
				return xcache_unset($ndx);
			case 'folder':
				return @unlink($parts[1].$ndx);
		}
		return FALSE;
	}

	/**
	*	Clear contents of cache backend
	*	@return bool
	*	@param $suffix string
	*	@param $lifetime int
	**/
	function reset($suffix=NULL,$lifetime=0) {
		if (!$this->dsn)
			return TRUE;
		$regex='/'.preg_quote($this->prefix.'.','/').'.+?'.
			preg_quote($suffix,'/').'/';
		$parts=explode('=',$this->dsn,2);
		switch ($parts[0]) {
			case 'apc':
			case 'apcu':
				$info=apc_cache_info('user');
				if (!empty($info['cache_list'])) {
					$key=array_key_exists('info',$info['cache_list'][0])?'info':'key';
					$mtkey=array_key_exists('mtime',$info['cache_list'][0])?
						'mtime':'modification_time';
					foreach ($info['cache_list'] as $item)
						if (preg_match($regex,$item[$key]) &&
							$item[$mtkey]+$lifetime<time())
							apc_delete($item[$key]);
				}
				return TRUE;
			case 'redis':
				$fw=Base::instance();
				$keys=$this->ref->keys($this->prefix.'.*'.$suffix);
				foreach($keys as $key) {
					$val=$fw->unserialize($this->ref->get($key));
					if ($val[1]+$lifetime<time())
						$this->ref->del($key);
				}
				return TRUE;
			case 'memcache':
				foreach (memcache_get_extended_stats(
					$this->ref,'slabs') as $slabs)
					foreach (array_filter(array_keys($slabs),'is_numeric')
						as $id)
						foreach (memcache_get_extended_stats(
							$this->ref,'cachedump',$id) as $data)
							if (is_array($data))
								foreach ($data as $key=>$val)
									if (preg_match($regex,$key) &&
										$val[1]+$lifetime<time())
										memcache_delete($this->ref,$key);
				return TRUE;
			case 'wincache':
				$info=wincache_ucache_info();
				foreach ($info['ucache_entries'] as $item)
					if (preg_match($regex,$item['key_name']) &&
						$item['use_time']+$lifetime<time())
					wincache_ucache_delete($item['key_name']);
				return TRUE;
			case 'xcache':
				return TRUE; /* Not supported */
			case 'folder':
				if ($glob=@glob($parts[1].'*'))
					foreach ($glob as $file)
						if (preg_match($regex,basename($file)) &&
							filemtime($file)+$lifetime<time())
							@unlink($file);
				return TRUE;
		}
		return FALSE;
	}

	/**
	*	Load/auto-detect cache backend
	*	@return string
	*	@param $dsn bool|string
	**/
	function load($dsn) {
		$fw=Base::instance();
		if ($dsn=trim($dsn)) {
			if (preg_match('/^redis=(.+)/',$dsn,$parts) &&
				extension_loaded('redis')) {
				$port=6379;
				$parts=explode(':',$parts[1],2);
				if (count($parts)>1)
					list($host,$port)=$parts;
				else
					$host=$parts[0];
				$this->ref=new Redis;
				if(!$this->ref->connect($host,$port,2))
					$this->ref=NULL;
			}
			elseif (preg_match('/^memcache=(.+)/',$dsn,$parts) &&
				extension_loaded('memcache'))
				foreach ($fw->split($parts[1]) as $server) {
					$port=11211;
					$parts=explode(':',$server,2);
					if (count($parts)>1)
						list($host,$port)=$parts;
					else
						$host=$parts[0];
					if (empty($this->ref))
						$this->ref=@memcache_connect($host,$port)?:NULL;
					else
						memcache_add_server($this->ref,$host,$port);
				}
			if (empty($this->ref) && !preg_match('/^folder\h*=/',$dsn))
				$dsn=($grep=preg_grep('/^(apc|wincache|xcache)/',
					array_map('strtolower',get_loaded_extensions())))?
						// Auto-detect
						current($grep):
						// Use filesystem as fallback
						('folder='.$fw->get('TEMP').'cache/');
			if (preg_match('/^folder\h*=\h*(.+)/',$dsn,$parts) &&
				!is_dir($parts[1]))
				mkdir($parts[1],Base::MODE,TRUE);
		}
		$this->prefix=$fw->hash($_SERVER['SERVER_NAME'].$fw->get('BASE'));
		return $this->dsn=$dsn;
	}

	/**
	*	Class constructor
	*	@return object
	*	@param $dsn bool|string
	**/
	function __construct($dsn=FALSE) {
		if ($dsn)
			$this->load($dsn);
	}

}
