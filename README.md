# moe
A PHP framework based on [Fatfree Framework](http://fatfreeframework.com)

> It's for my personal use only. May be the code not safe for you because my ugly coding style.

## Differences with Fatfree Framework
- Every class namespaced with \moe
- F3 Autoloader deleted, and now it depends on composer autoloader
- Template auto parse, just declare TEMPLATE vars to activate it
- Delete F3 DB ORM
- Add new class Database, AbstractModel, Validation

## Installation
Use [Composer](http://getcomposer.org) to install this package

	{
	    "require": {
	        "eghojansu/moe": "dev-master"
	    },
	    "minimum-stability": "dev"
	}