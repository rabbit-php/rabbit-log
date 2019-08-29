<?php


namespace rabbit\log;

use rabbit\contract\InitInterface;

/**
 * Class SeaslogConfig
 * @package rabbit\log
 */
class SeaslogConfig extends AbstractConfig implements InitInterface
{
    /** @var string */
    private $appName = 'Rabbit';
    /** @var Seaslog */
    private $logger;

    /**
     * SeaslogConfig constructor.
     * @param array $target
     * @throws \Exception
     */
    public function __construct(array $target, float $tick = 0)
    {
        parent::__construct($target, $tick);
        $this->appName = getDI('appName', false, 'Rabbit');
        $this->logger = new \Seaslog();
    }

    public function init()
    {
        ini_set('seaslog.recall_depth', $this->recall_depth);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $template = $this->getTemplate();
        $module = isset($context['module']) ? $context['module'] : null;
        if ($module !== null) {
            $this->logger->setLogger($this->appName . '_' . $module);
        }
        isset($template['%Q']) && $this->logger->setRequestID($template['%Q']);
        $this->logger->setRequestVariable(array_filter([
            SEASLOG_REQUEST_VARIABLE_DOMAIN_PORT => isset($template['%D']) ? $template['%D'] : null,
            SEASLOG_REQUEST_VARIABLE_REQUEST_URI => isset($template['%R']) ? $template['%R'] : null,
            SEASLOG_REQUEST_VARIABLE_REQUEST_METHOD => isset($template['%m']) ? $template['%m'] : null,
            SEASLOG_REQUEST_VARIABLE_CLIENT_IP => isset($template['%I']) ? $template['%I'] : null
        ]));
        $this->logger->$level($message);
        $this->flush();
    }

    /**
     * @param bool $flush
     */
    public function flush(bool $flush = false): void
    {
        $total = $this->logger->getBufferCount();
        if ($flush || $total >= $this->bufferSize) {
            $buffer = $this->logger->getBuffer();
            $this->logger->flushBuffer(0);
            foreach ($this->targetList as $index => $target) {
                rgo(function () use ($target, $buffer, $flush) {
                    $target->export($buffer, $flush);
                });
            }
            unset($buffer);
        }
    }
}