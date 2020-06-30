<?php
    declare(strict_types=1);

    namespace com\femastudios\jtrace;

    /**
     * A class that helps creating a Java-like stacktrace, instead of the default one
     *
     * @package com\femastudios\jtrace
     */
    final class JTrace {

        /** @var bool weather to include function args */
        private $includeArgs = false;
        /** @var bool weather to include non-trivial function args. Always false if $includeArgs is false */
        private $includeComplexArgs = false;
        /** @var int|null max length of the function arguments in their entirety. null for unlimited */
        private $argsMaxLength = 1024;

        /** @var int|null maximum number of stacktrace items, per exception. null for unlimited */
        private $maxItems = 256;
        /** @var int|null maximum number of chained exceptions (causes). null for unlimited */
        private $maxCauses = 64;

        /** @var string the separator of function arguments */
        private const ARGS_SEPARATOR = ', ';

        private function __construct() {
        }

        /**
         * Creates a new {@link JStacktrace} with the following default values:
         * <ul>
         *    <li>Do not include function arguments (disabled by default for security concerns)</li>
         *    <li>Maximum length of function arguments (if enabled): 1024</li>
         *    <li>Maximum number of stacktrace items: 256</li>
         *    <li>Maximum number of chained exceptions (causes): 64</li>
         * </ul>
         */
        public static function new() : self {
            return new self();
        }

        /**
         * Enables the printing of primitive types of arguments of functions (string, int, float & null).
         * If not enabled, complex objects will print their type instead of the value
         * @return JTrace this object
         */
        public function includeBaseArgs() : self {
            $this->includeArgs = true;
            return $this;
        }

        /**
         * Enables the printing of all arguments, including objects and resources.
         * For complex objects, if it's not possible to convert them to string, their type will be printed
         * @return JTrace this object
         */
        public function includeAllArgs() : self {
            $this->includeComplexArgs = $this->includeArgs = true;
            return $this;
        }

        /**
         * Excludes all the function arguments from printing. An empty <code>()</code> will be printed
         * @return JTrace this object
         */
        public function excludeArgs() : self {
            $this->includeComplexArgs = $this->includeArgs = false;
            return $this;
        }

        /**
         * Excludes the printing of complex function arguments. In this case, only their type will be printed
         * @return JTrace this object
         */
        public function excludeComplexArgs() : self {
            $this->includeComplexArgs = false;
            return $this;
        }

        /**
         * Sets the maximum length of the entirety of the function arguments.
         * The arguments are automatically separately shortened according to their size.
         * @param int|null $argsMaxLength the max value. A null value sets it to unlimited. The minimum value is 3. The default value is 1024
         * @return JTrace this object
         */
        public function argsMaxLength(?int $argsMaxLength) : self {
            if ($argsMaxLength < 3) {
                throw new \DomainException('maxLength must be >=3');
            }
            $this->argsMaxLength = $argsMaxLength;
            return $this;
        }

        /**
         * @param int|null $maxCauses the maximum number of chained exceptions to print. A null value sets it to unlimited. The default value is 64
         * @return JTrace this object
         */
        public function maxCauses(?int $maxCauses) : self {
            if ($maxCauses !== null && $maxCauses < 0) {
                throw new \DomainException('maxCauses < 0');
            }
            $this->maxCauses = $maxCauses;
            return $this;
        }

        /**
         * Sets the the maximum number of stacktrace items to print per exception (so for each cause).
         * If a stacktrace happens to be exactly <code>maxItems+1</code> items long, the last line will be printed anyway, to avoid the unproductive behavior of writing all items except one, and writing "1 more..." at the end
         * @param int|null $maxItems max value. A null value sets it to unlimited. The default value is 256
         * @return JTrace this object
         */
        public function maxItems(?int $maxItems) : self {
            if ($maxItems !== null && $maxItems < 0) {
                throw new \DomainException('maxItems < 0');
            }
            $this->maxItems = $maxItems;
            return $this;
        }

        /**
         * Generates and returns the corresponding Java-like stacktrace to the passed {@link \Throwable}
         * @param \Throwable $t the throwable to generate the stacktrace of
         * @return string the stacktrace
         */
        public function fromThrowable(\Throwable $t) : string {
            return $this->getJStacktrace($t, 0);
        }

        /**
         * <code>echo</code>es the Java-like stacktrace corresponding to the passed {@link \Throwable}
         * @param \Throwable $t the throwable to print the stacktrace of
         */
        public function printFromThrowable(\Throwable $t) : void {
            echo $this->fromThrowable($t);
        }

        /**
         * This super fancy algorithm truncates an array of arguments, optimizing space.
         * @param string[] $args the arguments to truncate
         * @param int $maxLength the maximum length of the totality of the args
         * @return string[]|null the array of shortened args, or null if the maxLength is too short to to anything about them
         */
        private static function truncateArgs(array $args, int $maxLength) : ?array {
            if ($maxLength < 0) {
                throw new \DomainException('maxLength < 0');
            }
            $argsTotLen = 0; // Total length of arguments
            foreach ($args as $arg) {
                $argsTotLen += mb_strlen($arg);
            }
            if ($argsTotLen <= $maxLength) {
                return $args; // if we do not exceed max length, just return them as-is
            } else {
                // Calculate the average argument length to respect the given max length
                $avgLength = (int)floor($maxLength / \count($args));
                $rest = $maxLength % \count($args); // Remaining breadcrumbs character that cannot be partitioned
                if ($avgLength <= 0) {
                    // If we have a maxLength/numberOfArgs ratio that is too low, we cannot do anything useful
                    return null;
                } else {
                    $longArgs = []; // Keeping args that are longer than the average length
                    $spareChars = $rest; // Number of unused characters
                    foreach ($args as $k => $arg) {
                        $len = mb_strlen($arg);
                        if ($len <= $avgLength) {
                            // If arg can be contained in avg length, I "use" it by NOT putting it in longArgs, and add the remaining characters to the spare count
                            $spareChars += $avgLength - $len;
                        } elseif ($len > $avgLength) {
                            // If arg is greater than avg, I store it for later, and add avg length to the spare count
                            $longArgs[$k] = $arg;
                            $spareChars += $avgLength;
                        }
                    }
                    if (\count($longArgs) < \count($args)) {
                        // Recursively call truncate with the args longer than average, with a new max length that is the spare chars count
                        $rec = self::truncateArgs($longArgs, $spareChars);
                    } else {
                        // If I put every arg in longArgs, this means that there is no other way than to truncate them all
                        $rec = null;
                    }
                    if ($rec !== null) {
                        // If recursion is successful, I don't need to truncate everything, I just use what recursion told me
                        $longArgs = $rec;
                    } else {
                        // If recursion is null or cannot be called, I can finally truncate the longArgs
                        // This for truncates each arg to average length, optionally using the remaining breadcrumbs spare characters, one for each arg until depletion
                        foreach ($longArgs as $k => $arg) {
                            $len = $avgLength - 3;
                            if ($rest > 0) {
                                $len++;
                                $rest--;
                            }
                            if ($len < 0) {
                                return null;
                            }
                            $longArgs[$k] = mb_substr($arg, 0, $len) . '...';
                        }
                    }
                    // I put the long arguments back into the args array
                    foreach ($longArgs as $k => $arg) {
                        $args[$k] = $arg;
                    }
                    // Return the finally adjusted args array
                    return $args;
                }
            }
        }

        /**
         * Given an array of args, shortens them according to the argsMaxLength property and formats them
         * @param string[] $args the args
         * @return string the formatted items
         */
        private function formatArgs(array $args) : string {
            if ($this->argsMaxLength !== null) {
                // Calculate max args length, without the args separator that will be added later in this function
                $max = $this->argsMaxLength - max(0, (\count($args) - 1) * mb_strlen(self::ARGS_SEPARATOR));
                if ($max <= 0) {
                    return '';
                } else {
                    $ret = static::truncateArgs($args, $max);
                }
            } else {
                $ret = $args;
            }
            if ($ret === null) {
                // If args cannot be truncated, I just return three dots (hence why the minimum value fro argsMaxLength is 3)
                return '...';
            } else {
                // If truncation is successful, join together args using the args separator
                return implode(self::ARGS_SEPARATOR, $ret);
            }
        }

        /**
         * @param $value mixed a value
         * @return string its string representation. If its string representation cannot be found, its type from {@link self::getType} will be returned
         */
        private static function itemToString($value) : string {
            if ($value === null) {
                return 'null';
            } elseif ($value instanceof \DateTimeInterface) {
                return $value->format(\DateTimeInterface::ATOM);
            } elseif (\is_array($value)) {
                $ret = [];
                foreach ($value as $k => $v) {
                    $ret[] = self::itemToString($k) . ' => ' . self::itemToString($v);
                }
                return '[' . implode(', ', $ret) . ']';
            } elseif (self::canBeCastedToString($value)) {
                if (\is_bool($value)) {
                    return $value ? 'true' : 'false';
                } elseif (\is_int($value) || \is_float($value) || \is_resource($value)) {
                    return (string)$value;
                } else {
                    return "'" . str_replace("'", "\\'", $value) . "'";
                }
            } else {
                return static::getType($value);
            }
        }

        /**
         * Returns the type name of the given value. Uses a combination of gettype() and get_class().<br>
         * Examples:
         * <ul>
         *    <li>getType("a") -> "string"</li>
         *    <li>getType(5) -> "int"</li>
         *    <li>getType(new \com\example\package\Class()) -> "com\example\package\Class"</li>
         *    <li>getType(new \Exception()) -> "Exception"</li>
         * </ul>
         * @param $value mixed the value to obtain the type of
         * @return string the type name
         */
        private static function getType($value) : string {
            if (\is_object($value)) {
                return \get_class($value);
            } else {
                return \gettype($value);
            }
        }

        /**
         * Checks if the given value can be casted safely to string, like this:<br>
         * <code>(string) $value</code>
         * @param mixed $value a value to test
         * @return bool true if value can be casted to string
         */
        private static function canBeCastedToString($value) : bool {
            return !\is_array($value) && (
                    (!\is_object($value) && settype($value, 'string') !== false) ||
                    (\is_object($value) && method_exists($value, '__toString'))
                );
        }

        private function getJStacktrace(\Throwable $t, int $depth) : string {
            $code = $t->getCode();
            if ($code === null) {
                $code = /** @lang text */
                    '<no code>';
            } elseif (\is_string($code) || \is_int($code) || \is_float($code) || \is_bool($code)) {
                $code = self::itemToString($code);
            } else {
                $code = self::getType($code);
            }
            $message = $t->getMessage();
            if ($message === null) {
                $message = '';
            } else {
                $message = ': ' . $message;
            }
            $ret = \get_class($t) . ' (' . $code . ')' . $message; // e.g. com\company\project\Exception (4): Bla bla bla
            if ($this->maxItems === null || $this->maxItems > 0) { // If I should print at least one trace item
                // Create traces array: as first element we put the file and line of the exception; following the elements obtained through getTrace()
                $traces = [[
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]];
                $trace = $t->getTrace();
                if (\count($trace) > 0) {
                    array_push($traces, ...$trace);
                }


                $i = $this->maxItems;
                if (\count($traces) - $this->maxItems === 1) {
                    // If I would only miss one line, print it anyway (see maxItems() doc for better explanation)
                    $i++;
                }
                foreach ($traces as $trace) {
                    if ($i !== null && --$i < 0) {
                        // If we reached the maximum number of traces, inform the user and break the for
                        $ret .= "\n    " . (\count($traces) - $this->maxItems) . ' more...';
                        break;
                    } else {
                        $ret .= "\n    at ";

                        // Print file or class, depending on what we have
                        if (isset($trace['file'])) {
                            $ret .= $trace['file'];
                        } elseif (isset($trace['class'])) {
                            $ret .= '\\' . $trace['class'];
                        } else {
                            throw new \LogicException('Must have file or class in trace');
                        }

                        // Put line, if we have it
                        if (isset($trace['line'])) {
                            $ret .= ':' . $trace['line'];
                        }

                        // Put function call, if we have it
                        if (isset($trace['function'], $trace['type'])) {
                            $functionParts = explode('\\', $trace['function']);
                            $function = $functionParts[\count($functionParts) - 1];
                            $ret .= $trace['type'] . $function . '(';

                            // Put args, if we have them and we want to print them
                            if ($this->includeArgs && isset($trace['args'])) {
                                $args = [];
                                /** @noinspection ForeachSourceInspection */
                                foreach ($trace['args'] as $arg) {
                                    if ($this->includeComplexArgs || $arg === null || \is_string($arg) || \is_int($arg) || \is_float($arg) || \is_bool($arg)) {
                                        $txt = self::itemToString($arg);
                                    } else {
                                        $txt = self::getType($arg);
                                    }
                                    $args[] = $txt;
                                }
                                // Formats args, truncating them if necessary
                                $ret .= $this->formatArgs($args);
                            }
                            $ret .= ')';
                        }
                    }
                }
            }
            if ($t->getPrevious() !== null) {
                // If we have the previous exception (the cause)
                if ($this->maxCauses === null || $this->maxCauses > $depth) {
                    // If depth isn't a problem, call method recursively
                    $ret .= "\n\nCaused by:\n";
                    $ret .= $this->getJStacktrace($t->getPrevious(), $depth + 1);
                } elseif ($this->maxCauses > 0) {
                    // If have reached max causes depth, and I did print at least one cause, print how many causes are missing
                    $i = 0;
                    $cause = $t->getPrevious();
                    while ($cause !== null) {
                        $i++;
                        $cause = $cause->getPrevious();
                    }
                    $ret .= "\n\nAnd other $i causes...";
                }
            }
            return $ret;
        }
    }