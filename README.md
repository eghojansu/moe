# moe
A Light PHP framework based on [Fatfree Framework](http://fatfreeframework.com)

> Currently there is no documentation and unit test for this framework.
> But version 0.2.0+ was stable (by manual test :D)

## Differences with Fatfree Framework
- Every class namespaced with moe\
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