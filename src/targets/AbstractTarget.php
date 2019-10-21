<?php


namespace rabbit\log\targets;

use rabbit\contract\InitInterface;

/**
 * Class AbstractTarget
 * @package rabbit\log\targets
 */
abstract class AbstractTarget implements InitInterface
{
    /** @var string */
    protected $split = ' | ';
    /** @var array */
    protected $levelList = [];
    /** @var int */
    protected $levelIndex = 1;

    /**
     * AbstractTarget constructor.
     * @param string $split
     */
    public function __construct(string $split = ' | ')
    {
        $this->split = $split;
    }

    public function init()
    {
    }


    /**
     * @param array $messages
     * @param bool $flush
     */
    abstract public function export(array $messages, bool $flush = true): void;
}
