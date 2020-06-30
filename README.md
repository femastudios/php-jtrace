# jtrace
A PHP library to create Java-like stacktraces.

Installation through composer package `femastudios/jtrace`

## Usage
```php
// Create a JTrace class
$jtrace = JTrace::new();
// $e is a \Throwable instance
$jtrace->fromThrowable($e); // Returns the stacktrace as a string
$jtrace->printFromThrowable($e); // Prints the stacktrace
```

Sample output: 
```
LogicException (0): Inner threw
    at C:\Users\username\Desktop\php\jtrace\tests\Code.php:16
    at C:\Users\username\Desktop\php\jtrace\tests\test.php:14->run()
    at C:\Users\username\Desktop\php\jtrace\tests\test.php:18

Caused by:
Exception (101): Inner message
    at C:\Users\username\Desktop\php\jtrace\tests\Code.php:9
    at C:\Users\username\Desktop\php\jtrace\tests\Code.php:14->runInner()
    at C:\Users\username\Desktop\php\jtrace\tests\test.php:14->run()
    at C:\Users\username\Desktop\php\jtrace\tests\test.php:18
```

## Options
Each `JTrace` instance can be customized with the following parameters:
* **Max items**: the maximum number of lines to print for each `\Throwable`. Defaults to `256`.  
* **Max causes**: the maximum number of causes to print. Defaults to `64`.
* **Include arguments**: whether to include arguments of function calls in the stacktrace. Defaults to `false`.
    * **Include complex arguments**: whether to include non-trivial arguments in function calls. When `false` scalar types are written normally, for other types the type is written. When `true` an attempt to convert classes an arrays to string is made. Defaults to `false`.
    * **Arguments max length**: the maximum length for **all** the arguments for each line. When exceeded arguments are automatically separately shortened according to their size. Defaults to `1024`. 

Example of customization:
```php
JTrace::new()
    ->includeBaseArgs() // Includes only scalar args
    ->maxItems(null) // Removes the items limit
    ->maxCauses(8); // Limits the number of chained exceptions to 8
```