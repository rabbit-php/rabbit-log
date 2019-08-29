<?php


namespace rabbit\log;

use rabbit\log\targets\AbstractTarget;

/**
 * Interface ConfigInterface
 * @package rabbit\log
 */
abstract class AbstractConfig
{
    /** @var int */
    protected $bufferSize = 1;
    /** @var AbstractTarget[] */
    protected $targetList = [];
    /** @var int */
    protected $tick = 0;
    /** @var int */
    protected $recall_depth = 0;
    /** @var callable */
    protected $userTemplate;

    /**
     * AbstractConfig constructor.
     * @param array $target
     */
    public function __construct(array $target, float $tick = 0)
    {
        $this->targetList = $target;
        $this->tick = $tick;
        register_shutdown_function(function () {
            $this->flush(true);
        });
        $this->tick > 0 && \Swoole\Timer::tick($this->tick * 1000, [$this, 'flush'], [true]);
    }

    /**
     * @param callable $setTemplate
     * @param callable $getTemplate
     */
    public function registerTemplate(callable $userTemplate): void
    {
        $this->userTemplate = $userTemplate;
    }

    /**
     * @return array
     */
    protected function getTemplate(): array
    {
        if ($this->userTemplate) {
            $template = call_user_func($this->userTemplate);
            $template = $template ?? [];
        } else {
            $template = [];
        }
        return $template;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    abstract public function log(string $level, string $message, array $context = []): void;

    /**
     * @param bool $flush
     */
    abstract public function flush(bool $flush = false): void;
}