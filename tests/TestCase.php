<?php

namespace Nesk\Rialto\Tests;

use Monolog\Logger;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use ReflectionClass;
use Psr\Log\LogLevel;
use PHPUnit\Util\ErrorHandler;
use Symfony\Component\Process\Process;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    private $dontPopulateProperties = [];

    protected function setUp(): void
    {
        parent::setUp();

        $testMethod = new \ReflectionMethod($this, $this->getName());
        $docComment = $testMethod->getDocComment();

        if (preg_match('/@dontPopulateProperties (.*)/', $docComment, $matches)) {
            $this->dontPopulateProperties = array_values(array_filter(explode(' ', $matches[1])));
        }
    }

    public function canPopulateProperty(string $propertyName): bool
    {
        return !in_array($propertyName, $this->dontPopulateProperties);
    }

    public function ignoreUserDeprecation(string $messagePattern, callable $callback) {
        set_error_handler(
            function (int $errorNumber, string $errorString, string $errorFile, int $errorLine) use ($messagePattern) {
                if ($errorNumber !== E_USER_DEPRECATED || preg_match($messagePattern, $errorString) !== 1) {
                    (new ErrorHandler(true, true, true, true))($errorNumber, $errorString, $errorFile, $errorLine);
                }
            }
        );

        $value = $callback();

        restore_error_handler();

        return $value;
    }

    public function getPidsForProcessName(string $processName) {
        $pgrep = new Process(['pgrep', $processName]);
        $pgrep->run();

        $pids = explode("\n", $pgrep->getOutput());

        $pids = array_filter($pids, function ($pid) {
            return !empty($pid);
        });

        $pids = array_map(function ($pid) {
            return (int) $pid;
        }, $pids);

        return $pids;
    }

    public function loggerMock($expectations) {
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->setConstructorArgs(['rialto'])
            ->onlyMethods(['log'])
            ->getMock();
        if ($expectations instanceof InvocationOrder) {
            $expectations = [func_get_args()];
        }

        foreach ($expectations as $expectation) {
            [$matcher] = $expectation;
            $with = array_slice($expectation, 1);

            $loggerMock->expects($matcher)
                ->method('log')
                ->with(...$with);
        }

        return $loggerMock;
    }

    public function isLogLevel(): Callback {
        $psrLogLevels = (new ReflectionClass(LogLevel::class))->getConstants();
        $monologLevels = (new ReflectionClass(Logger::class))->getConstants();
        $monologLevels = array_intersect_key($monologLevels, $psrLogLevels);

        return $this->callback(function ($level) use ($psrLogLevels, $monologLevels) {
            if (is_string($level)) {
                return in_array($level, $psrLogLevels, true);
            } else if (is_int($level)) {
                return in_array($level, $monologLevels, true);
            }

            return false;
        });
    }
}
