<?php

declare(strict_types=1);

namespace Nesk\Rialto\Tests;

use Nesk\Rialto\{Data\JsFunction, Exceptions\Node, Data\BasicResource};
use Nesk\Rialto\Tests\Implementation\{FsWithProcessDelegation, FsWithoutProcessDelegation, Resources\Stats};
use PHPUnit\Framework\Attributes\{Group, RequiresOperatingSystem, Test};
use Symfony\Component\Process\Process;

final class ImplementationTest extends TestCase
{
    private string $dirPath;
    private string $filePath;

    private FsWithProcessDelegation|FsWithoutProcessDelegation|null $fs;

    public const JS_FUNCTION_CREATE_DEPRECATION_PATTERN = '/^Nesk\\\\Rialto\\\\Data\\\\JsFunction::create\(\)/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dirPath = realpath(__DIR__ . '/resources');
        $this->filePath = "{$this->dirPath}/file";

        $this->fs = $this->canPopulateProperty('fs') ? new FsWithProcessDelegation() : null;
    }

    protected function tearDown(): void
    {
        $this->fs = null;
    }

    #[Test]
    public function can_call_method_and_get_its_return_value()
    {
        $content = $this->fs->readFileSync($this->filePath, 'utf8');

        $this->assertEquals('Hello world!', $content);
    }

    #[Test]
    public function can_get_property()
    {
        $constants = $this->fs->constants;

        $this->assertIsArray($constants);
    }

    #[Test]
    public function can_set_property()
    {
        $this->fs->foo = 'bar';
        $this->assertEquals('bar', $this->fs->foo);

        $this->fs->foo = null;
        $this->assertNull($this->fs->foo);
    }

    #[Test]
    public function can_return_basic_resources()
    {
        $resource = $this->fs->readFileSync($this->filePath);

        $this->assertInstanceOf(BasicResource::class, $resource);
    }

    #[Test]
    public function can_return_specific_resources()
    {
        $resource = $this->fs->statSync($this->filePath);

        $this->assertInstanceOf(Stats::class, $resource);
    }

    #[Test]
    public function can_cast_resources_to_string()
    {
        $resource = $this->fs->statSync($this->filePath);

        $this->assertEquals('[object Object]', (string) $resource);
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    public function can_omit_process_delegation()
    {
        $this->fs = new FsWithoutProcessDelegation();

        $resource = $this->fs->statSync($this->filePath);

        $this->assertInstanceOf(BasicResource::class, $resource);
        $this->assertNotInstanceOf(Stats::class, $resource);
    }

    #[Test]
    public function can_use_nested_resources()
    {
        $resources = $this->fs->multipleStatSync($this->dirPath, $this->filePath);

        $this->assertCount(2, $resources);
        $this->assertContainsOnlyInstancesOf(Stats::class, $resources);

        $isFile = $this->fs->multipleResourcesIsFile($resources);

        $this->assertFalse($isFile[0]);
        $this->assertTrue($isFile[1]);
    }

    #[Test]
    public function can_use_multiple_resources_without_confusion()
    {
        $dirStats = $this->fs->statSync($this->dirPath);
        $fileStats = $this->fs->statSync($this->filePath);

        $this->assertInstanceOf(Stats::class, $dirStats);
        $this->assertInstanceOf(Stats::class, $fileStats);

        $this->assertTrue($dirStats->isDirectory());
        $this->assertTrue($fileStats->isFile());
    }

    #[Test]
    public function can_return_multiple_times_the_same_resource()
    {
        $stats1 = $this->fs->Stats;
        $stats2 = $this->fs->Stats;

        $this->assertEquals($stats1, $stats2);
    }

    #[Test]
    #[Group('js-functions')]
    public function can_use_js_functions_with_a_body()
    {
        $functions = [
            $this->ignoreUserDeprecation(self::JS_FUNCTION_CREATE_DEPRECATION_PATTERN, function () {
                return JsFunction::create("return 'Simple callback';");
            }),
            JsFunction::createWithBody("return 'Simple callback';"),
        ];

        foreach ($functions as $function) {
            $value = $this->fs->runCallback($function);
            $this->assertEquals('Simple callback', $value);
        }
    }

    #[Test]
    #[Group('js-functions')]
    public function can_use_js_functions_with_parameters()
    {
        $functions = [
            $this->ignoreUserDeprecation(self::JS_FUNCTION_CREATE_DEPRECATION_PATTERN, function () {
                return JsFunction::create(
                    ['fs'],
                    "
                    return 'Callback using arguments: ' + fs.constructor.name;
                ",
                );
            }),
            JsFunction::createWithParameters(['fs'])->body(
                "return 'Callback using arguments: ' + fs.constructor.name;",
            ),
        ];

        foreach ($functions as $function) {
            $value = $this->fs->runCallback($function);
            $this->assertEquals('Callback using arguments: Object', $value);
        }
    }

    #[Test]
    #[Group('js-functions')]
    public function can_use_js_functions_with_scope()
    {
        $functions = [
            $this->ignoreUserDeprecation(self::JS_FUNCTION_CREATE_DEPRECATION_PATTERN, function () {
                return JsFunction::create(
                    "
                    return 'Callback using scope: ' + foo;
                ",
                    ['foo' => 'bar'],
                );
            }),
            JsFunction::createWithScope(['foo' => 'bar'])->body("return 'Callback using scope: ' + foo;"),
        ];

        foreach ($functions as $function) {
            $value = $this->fs->runCallback($function);
            $this->assertEquals('Callback using scope: bar', $value);
        }
    }

    #[Test]
    #[Group('js-functions')]
    public function can_use_resources_in_js_functions()
    {
        $fileStats = $this->fs->statSync($this->filePath);

        $functions = [
            JsFunction::createWithParameters(['fs', 'fileStats' => $fileStats])->body('return fileStats.isFile();'),
            JsFunction::createWithScope(['fileStats' => $fileStats])->body('return fileStats.isFile();'),
        ];

        foreach ($functions as $function) {
            $isFile = $this->fs->runCallback($function);
            $this->assertTrue($isFile);
        }
    }

    #[Test]
    #[Group('js-functions')]
    public function can_use_async_with_js_functions()
    {
        $function = JsFunction::createWithAsync()->body("
                await Promise.resolve();
                return true;
            ");

        $this->assertTrue($this->fs->runCallback($function));

        $function = $function->async(false);

        $this->expectException(Node\FatalException::class);
        $this->expectExceptionMessage('await is only valid in async function');

        $this->fs->runCallback($function);
    }

    #[Test]
    #[Group('js-functions')]
    public function js_functions_are_sync_by_default()
    {
        $function = JsFunction::createWithBody('await null');

        $this->expectException(Node\FatalException::class);
        $this->expectExceptionMessage('await is only valid in async function');

        $this->fs->runCallback($function);
    }

    #[Test]
    public function can_receive_heavy_payloads_with_non_ascii_chars()
    {
        $payload = $this->fs->getHeavyPayloadWithNonAsciiChars();

        $this->assertStringStartsWith('😘', $payload);
        $this->assertStringEndsWith('😘', $payload);
    }

    #[Test]
    public function node_crash_throws_a_fatal_exception()
    {
        self::expectException(\Nesk\Rialto\Exceptions\Node\FatalException::class);
        self::expectExceptionMessage('Object.__inexistantMethod__ is not a function');
        $this->fs->__inexistantMethod__();
    }

    #[Test]
    public function can_catch_errors()
    {
        self::expectException(\Nesk\Rialto\Exceptions\Node\Exception::class);
        self::expectExceptionMessage('Object.__inexistantMethod__ is not a function');
        $this->fs->tryCatch->__inexistantMethod__();
    }

    #[Test]
    public function catching_a_node_exception_doesnt_catch_fatal_exceptions()
    {
        self::expectException(\Nesk\Rialto\Exceptions\Node\FatalException::class);
        self::expectExceptionMessage('Object.__inexistantMethod__ is not a function');
        try {
            $this->fs->__inexistantMethod__();
        } catch (Node\Exception $exception) {
            //
        }
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    public function in_debug_mode_node_exceptions_contain_stack_trace_in_message()
    {
        $this->fs = new FsWithProcessDelegation(['debug' => true]);

        $regex = '/\n\nError: "Object\.__inexistantMethod__ is not a function"\n\s+at /';

        try {
            $this->fs->tryCatch->__inexistantMethod__();
        } catch (Node\Exception $exception) {
            $this->assertMatchesRegularExpression($regex, $exception->getMessage());
        }

        try {
            $this->fs->__inexistantMethod__();
        } catch (Node\FatalException $exception) {
            $this->assertMatchesRegularExpression($regex, $exception->getMessage());
        }
    }

    #[Test]
    public function node_current_working_directory_is_the_same_as_php()
    {
        $result = $this->fs->accessSync('tests/resources/file');

        $this->assertNull($result);
    }

    #[Test]
    public function executable_path_option_changes_the_process_prefix()
    {
        self::expectException(\Symfony\Component\Process\Exception\ProcessFailedException::class);
        //self::expectExceptionMessageMatches('/Error Output:\n=+\n.*__inexistant_process__.*not found/');
        self::expectExceptionMessageMatches(
            '/Error Output:\n=+\n.*__inexistant_process__.*is not recognized as an internal or external command/',
        );
        new FsWithProcessDelegation(['executable_path' => '__inexistant_process__']);
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    public function idle_timeout_option_closes_node_once_timer_is_reached()
    {
        $this->fs = new FsWithProcessDelegation(['idle_timeout' => 0.5]);

        $this->fs->constants;

        sleep(1);

        $this->expectException(\Nesk\Rialto\Exceptions\IdleTimeoutException::class);
        $this->expectExceptionMessageMatches('/^The idle timeout \(0\.500 seconds\) has been exceeded/');

        $this->fs->constants;
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    public function read_timeout_option_throws_an_exception_on_long_actions()
    {
        self::expectException(\Nesk\Rialto\Exceptions\ReadSocketTimeoutException::class);
        self::expectExceptionMessageMatches('/^The timeout \(0\.010 seconds\) has been exceeded/');
        $this->fs = new FsWithProcessDelegation(['read_timeout' => 0.01]);

        $this->fs->wait(20);
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    #[Group('logs')]
    public function forbidden_options_are_removed()
    {
        // any, once, atLeastOnce, exactly, atMost
        $matcher = $this->atLeast(2);
        $this->fs = new FsWithProcessDelegation([
            'logger' => $this->loggerMock(
                $matcher,
                $this->isLogLevel(),
                $this->callback(function ($message) use ($matcher) {
                    $numberOfInvocations = $matcher->numberOfInvocations();
                    if ($numberOfInvocations === 1) {
                        $this->assertSame('Applying options...', $message);
                    } elseif ($numberOfInvocations === 2) {
                        $this->assertSame('Options applied and merged with defaults', $message);
                    }
                    return true;
                }),
                $this->callback(function ($context) use ($matcher) {
                    $numberOfInvocations = $matcher->numberOfInvocations();
                    if ($numberOfInvocations === 1) {
                        $this->assertArrayNotHasKey('foo', $context['options']);
                        $this->assertArrayHasKey('read_timeout', $context['options']);
                        $this->assertArrayNotHasKey('stop_timeout', $context['options']);
                    } elseif ($numberOfInvocations === 2) {
                        $this->assertArrayNotHasKey('foo', $context['options']);
                        $this->assertArrayHasKey('idle_timeout', $context['options']);
                        $this->assertArrayHasKey('read_timeout', $context['options']);
                        $this->assertArrayHasKey('stop_timeout', $context['options']);
                    }
                    return true;
                }),
            ),
            'read_timeout' => 5,
            'stop_timeout' => 0,
            'foo' => 'bar',
        ]);
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    public function connection_delegate_receives_options()
    {
        $this->fs = new FsWithProcessDelegation([
            'log_node_console' => true,
            'new_option' => false,
        ]);

        $this->assertNull($this->fs->getOption('read_timeout')); // Assert this option is stripped by the supervisor
        $this->assertTrue($this->fs->getOption('log_node_console'));
        $this->assertFalse($this->fs->getOption('new_option'));
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    #[RequiresOperatingSystem("^(?!Win32|WINNT|Windows).*$")]
    public function process_status_is_tracked()
    {
        if ((new Process(['which', 'pgrep']))->run() !== 0) {
            $this->markTestSkipped('The "pgrep" command is not available.');
        }

        $oldPids = $this->getPidsForProcessName('node');
        $this->fs = new FsWithProcessDelegation();
        $newPids = $this->getPidsForProcessName('node');

        $newNodeProcesses = array_values(array_diff($newPids, $oldPids));
        $newNodeProcessesCount = count($newNodeProcesses);
        $this->assertCount(
            1,
            $newNodeProcesses,
            "One Node process should have been created instead of $newNodeProcessesCount. Try running again.",
        );

        $processKilled = posix_kill($newNodeProcesses[0], SIGKILL);
        $this->assertTrue($processKilled);

        \usleep(10_000); # To make sure the process had enough time to be killed.
        $this->expectException(\Nesk\Rialto\Exceptions\ProcessUnexpectedlyTerminatedException::class);
        $this->expectExceptionMessage('The process has been unexpectedly terminated.');

        $this->fs->foo;
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    #[Group('logs')]
    public function logger_is_used_when_provided()
    {
        $this->fs = new FsWithProcessDelegation([
            'logger' => $this->loggerMock($this->atLeastOnce(), $this->isLogLevel(), $this->isType('string')),
        ]);
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    #[Group('logs')]
    public function node_console_calls_are_logged()
    {
        $consoleMessage = 'Hello World!';
        $setups = [[false, "Received data on stdout: $consoleMessage"], [true, "Received a Node log: $consoleMessage"]];
        foreach ($setups as [$logNodeConsole, $startsWith]) {
            $matcher = $this->atLeast(6);
            $this->fs = new FsWithProcessDelegation([
                'log_node_console' => $logNodeConsole,
                'logger' => $this->loggerMock(
                    $matcher,
                    $this->isLogLevel(),
                    $this->callback(function ($message) use ($matcher, $startsWith) {
                        $numberOfInvocations = $matcher->numberOfInvocations();
                        if ($numberOfInvocations === 6) {
                            $this->assertSame($startsWith, rtrim($message, "\r\n"));
                        }
                        return true;
                    }),
                ),
            ]);

            $this->fs->runCallback(JsFunction::createWithBody("console.log('$consoleMessage')"));
        }
    }

    /**
     * @dontPopulateProperties fs
     */
    #[Test]
    #[Group('logs')]
    public function delayed_node_console_calls_and_data_on_standard_streams_are_logged()
    {
        $matcher = $this->atLeast(8);
        $this->fs = new FsWithProcessDelegation([
            'log_node_console' => true,
            'logger' => $this->loggerMock(
                $matcher,
                $this->isLogLevel(),
                $this->callback(function ($message) use ($matcher) {
                    $numberOfInvocations = $matcher->numberOfInvocations();
                    if ($numberOfInvocations === 7) {
                        $this->assertStringStartsWith('Received data on stdout: Hello', $message);
                    } elseif ($numberOfInvocations === 8) {
                        $this->assertStringStartsWith('Received a Node log:', $message);
                    }
                    return true;
                }),
            ),
        ]);

        $javascript = <<<'JSFUNC'
        setTimeout(() => {
            process.stdout.write('Hello Stdout!');
            console.log('Hello Console!');
        });
        JSFUNC;
        $this->fs->runCallback(JsFunction::createWithBody($javascript));

        \usleep(10_000); // 10ms, to be sure the delayed instructions just above are executed.
        $this->fs = null;
    }
}
