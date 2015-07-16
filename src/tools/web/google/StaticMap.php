<?php

namespace moe\tools\web\google;

use moe\Base;
use moe\tools\Web;

//! Google Static Maps API v2 plug-in
class StaticMap {

	const
		//! API URL
		URL_Static='http://maps.googleapis.com/maps/api/staticmap';

	protected
		//! Query arguments
		$query=array();

	/**
	*	Specify API key-value pair via magic call
	*	@return object
	*	@param $func string
	*	@param $args array
	**/
	function __call($func,array $args) {
		$this->query[]=array($func,$args[0]);
		return $this;
	}

	/**
	*	Generate map
	*	@return string
	**/
	function dump() {
		$fw=Base::instance();
		$web=Web::instance();
		$out='';
		return ($req=$web->request(
			self::URL_Static.'?'.array_reduce(
				$this->query,
				function($out,$item) {
					return ($out.=($out?'&':'').
						urlencode($item[0]).'='.urlencode($item[1]));
				}
			))) && $req['body']?$req['body']:FALSE;
	}

}
