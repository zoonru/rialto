<?php

declare(strict_types=1);

namespace Nesk\Rialto\Tests;

use Monolog\Logger;
use PHPUnit\Framework\{Constraint\Callback, TestCase as BaseTestCase};
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Util\ErrorHandler;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Process;

use function Symfony\Component\Translation\t;

class TestCase extends BaseTestCase
{
    private $dontPopulateProperties = [];

    protected function setUp(): void
    {
        parent::setUp();

        $testMethod = new \ReflectionMethod($this, $this->name());
        $docComment = $testMethod->getDocComment();

        if (!empty($docComment) && preg_match('/@dontPopulateProperties (.*)/', $docComment, $matches)) {
            $this->dontPopulateProperties = array_values(array_filter(explode(' ', $matches[1])));
        }
    }

    public function canPopulateProperty(string $propertyName): bool
    {
        return !in_array($propertyName, $this->dontPopulateProperties);
    }

    public function ignoreUserDeprecation(string $messagePattern, callable $callback)
    {
        set_error_handler(function (int $errorNumber, string $errorString, string $errorFile, int $errorLine) use (
            $messagePattern,
        ) {
            if ($errorNumber !== E_USER_DEPRECATED || preg_match($messagePattern, $errorString) !== 1) {
                (new ErrorHandler(true, true, true, true))($errorNumber, $errorString, $errorFile, $errorLine);
            }
        });

        $value = $callback();

        restore_error_handler();

        return $value;
    }

    public function getPidsForProcessName(string $processName)
    {
        $pgrep = new Process(['pgrep', $processName]);
        $pgrep->run();

        $pids = explode("\n", $pgrep->getOutput());
        $pids = array_filter($pids, fn($pid) => !empty($pid));
        $pids = array_map(fn($pid) => (int) $pid, $pids);

        return $pids;
    }

    public function loggerMock(InvocationOrder|array $expectations)
    {
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->setConstructorArgs(['rialto'])
            ->onlyMethods(['log'])
            ->getMock();
        if ($expectations instanceof InvocationOrder) {
            $expectations = [func_get_args()];
        }

        foreach ($expectations as $with) {
            $matcher = array_shift($with);
            $loggerMock->expects($matcher)->method('log')->with(...$with);
        }

        return $loggerMock;
    }

    public function isLogLevel(): Callback
    {
        $psrLogLevels = (new \ReflectionClass(LogLevel::class))->getConstants();
        $monologLevels = (new \ReflectionClass(Logger::class))->getConstants();
        $monologLevels = array_intersect_key($monologLevels, $psrLogLevels);

        return $this->callback(function ($level) use ($psrLogLevels, $monologLevels) {
            if (is_string($level)) {
                return in_array($level, $psrLogLevels, true);
            } elseif (is_int($level)) {
                return in_array($level, $monologLevels, true);
            }

            return false;
        });
    }
}
