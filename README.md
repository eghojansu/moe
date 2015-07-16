# moe
A PHP framework based on [Fatfree Framework](http://fatfreeframework.com)

## Differences with Fatfree Framework
- Every class namespaced with \moe
- F3 object not passed when function/method run
- F3 Autoloader deleted, and now it depends on composer autoloader
- Template auto parse, just declare TEMPLATE vars to activate it
- Delete database class and its mapper, changed to Medoo
- Add new AbstractModel, Validator

## Depends
- [Medoo Database Framework](https://github.com/catfan/Medoo)
- [Wixel/GUMP](https://github.com/Wixel/GUMP) Validator