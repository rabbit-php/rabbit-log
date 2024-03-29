<?php

declare(strict_types=1);

namespace Rabbit\Log;

use Throwable;
use Rabbit\Base\App;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\ExceptionHelper;
use Rabbit\Base\Exception\InvalidConfigException;

/**
 * Class LoggerConfig
 * @package Rabbit\Log
 */
class LoggerConfig extends AbstractConfig
{
    /** @var string */
    protected string $datetime_format = "Y-m-d H:i:s";
    /** @var string */
    protected string $split = ' | ';
    /** @var int */
    protected int $isMicrotime = 3;
    /** @var string */
    private string $appName = 'Rabbit';
    /** @var bool */
    protected bool $useBasename = false;
    /** @var array */
    private static array $supportTemplate = [
        '%W',
        '%L',
        '%M',
        '%T',
        '%t',
        '%Q',
        '%H',
        '%P',
        '%D',
        '%R',
        '%m',
        '%I',
        '%F',
        '%U',
        '%u',
        '%C',
        '%n',
    ];

    private int $pid = 0;

    /**
     * LoggerConfig constructor.
     * @param array $target
     * @param float $tick
     * @param array $template
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function __construct(
        protected array $targetList,
        protected bool $realMem = true,
        protected array $template = ['%n', '%T', '%L', '%R', '%m', '%I', '%Q', '%F', '%U', '%M']
    ) {
        foreach ($template as $tmp) {
            if (!in_array($tmp, self::$supportTemplate)) {
                throw new InvalidConfigException("$tmp not supported!");
            }
        }
        $this->appName = (string)config('appName', $this->appName);
        $this->pid = getmypid();
    }

    /**
     * @return string
     */
    public function getSplit(): string
    {
        return $this->split;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     * @throws Throwable
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $template = $this->getTemplate();
        $msg = [];
        foreach ($this->template as $tmp) {
            switch ($tmp) {
                case '%n':
                    $msg[] = $this->appName;
                    break;
                case '%W':
                    $msg[] = ArrayHelper::getValue($template, $tmp, -1);
                    break;
                case '%L':
                    $msg[] = $level;
                    break;
                case '%M':
                    $msg[] = strtr(str_replace($this->split, ' ', $message), $context);
                    break;
                case '%T':
                    $micsec = in_array($this->isMicrotime, [3, 6]) ? $this->isMicrotime : 3;
                    $mtimestamp = sprintf("%.{$micsec}f", microtime(true));
                    [$timestamp, $milliseconds] = explode('.', $mtimestamp);
                    $msg[] = date($this->datetime_format, (int)$timestamp) . '.' . $milliseconds;
                    break;
                case '%t':
                    $timestamp = time();
                    $msg[] = date($this->datetime_format, $timestamp);
                    break;
                case '%Q':
                    $msg[] = ArrayHelper::getValue($template, $tmp, uniqid());
                    break;
                case '%H':
                    $msg[] = ArrayHelper::getValue(
                        $template,
                        $tmp,
                        isset($_SERVER['HOSTNAME']) ? $_SERVER['HOSTNAME'] : 'local'
                    );
                    break;
                case '%P':
                    $msg[] = ArrayHelper::getValue($template, $tmp, getmypid());
                    break;
                case '%D':
                    $msg[] = ArrayHelper::getValue($template, $tmp, 'cli');
                    break;
                case '%R':
                    $msg[] = ArrayHelper::getValue(
                        $template,
                        $tmp,
                        isset($_SERVER['SCRIPT_FILENAME']) ? str_replace(App::getAlias('@root', false) . '/', '', $_SERVER['SCRIPT_FILENAME']) : '/'
                    );
                    break;
                case '%m':
                    $msg[] = strtolower(ArrayHelper::getValue($template, $tmp, ArrayHelper::getValue($_SERVER, 'SHELL', 'SHELL')));
                    break;
                case '%I':
                    $msg[] = ArrayHelper::getValue($template, $tmp, current(swoole_get_local_ip()));
                    break;
                case '%F':
                case '%C':
                    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->recall_depth);
                    if ($tmp === '%F') {
                        $trace = $trace[$this->recall_depth] ?? end($trace);
                        $file = $this->useBasename ? basename($trace['file']) . ':' . $trace['line'] : (($path = App::getAlias('@root', false)) ? str_replace($path . '/', '', $trace['file']) :
                            $trace['file']) . ':' . $trace['line'];
                    } else {
                        $trace = $trace[$this->recall_depth + 1];
                        $file = $trace['class'] . $trace['type'] . $trace['function'];
                    }
                    $msg[] = "pid:{$this->pid}@{$file}";
                    break;
                case '%U':
                    $msg[] = memory_get_usage($this->realMem);
                    break;
                case '%u':
                    $msg[] = memory_get_peak_usage($this->realMem);
                    break;
            }
        }
        $color = ArrayHelper::getValue($template, '%c');
        $color !== null && $msg['%c'] = $color;
        $this->flush($msg);
    }

    /**
     * @param array $buffer
     * @throws Throwable
     */
    public function flush(array &$buffer = []): void
    {
        if (!empty($buffer)) {
            foreach ($this->targetList as $target) {
                rgo(function () use ($target, &$buffer): void {
                    try {
                        $target->export($buffer);
                    } catch (Throwable $exception) {
                        print_r(ExceptionHelper::convertExceptionToArray($exception));
                    }
                });
            }
        }
    }
}
