<?php

declare(strict_types=1);

namespace Rabbit\Log;

/**
 * Class ConsoleColor
 * @package Rabbit\Log
 */
class ConsoleColor
{
    const FOREGROUND = '38',
        BACKGROUND = '48';
    const COLOR256_REGEXP = '~^(bg_)?color_([0-9]{1,3})$~';
    const RESET_STYLE = '0';
    /** @var bool */
    private bool $isSupported;
    /** @var bool */
    private bool $forceStyle = false;
    /** @var array */
    private array $styles = array(
        'none' => null,
        'bold' => '1',
        'dark' => '2',
        'italic' => '3',
        'underline' => '4',
        'blink' => '5',
        'reverse' => '7',
        'concealed' => '8',
        'default' => '39',
        'black' => '30',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
        'light_gray' => '37',
        'dark_gray' => '90',
        'light_red' => '91',
        'light_green' => '92',
        'light_yellow' => '93',
        'light_blue' => '94',
        'light_magenta' => '95',
        'light_cyan' => '96',
        'white' => '97',
        'bg_default' => '49',
        'bg_black' => '40',
        'bg_red' => '41',
        'bg_green' => '42',
        'bg_yellow' => '43',
        'bg_blue' => '44',
        'bg_magenta' => '45',
        'bg_cyan' => '46',
        'bg_light_gray' => '47',
        'bg_dark_gray' => '100',
        'bg_light_red' => '101',
        'bg_light_green' => '102',
        'bg_light_yellow' => '103',
        'bg_light_blue' => '104',
        'bg_light_magenta' => '105',
        'bg_light_cyan' => '106',
        'bg_white' => '107',
    );
    /** @var array */
    private array $themes = array();

    /**
     * ConsoleColor constructor.
     * @param bool $forceStyle
     */
    public function __construct(bool $forceStyle = true)
    {
        $this->forceStyle = $forceStyle;
        $this->isSupported = $this->isSupported();
    }

    /**
     * @param $style
     * @param $text
     * @return string
     */
    public function apply($style, string $text): string
    {
        if (empty($style)) {
            return $text;
        }
        if (!$this->isStyleForced() && !$this->isSupported()) {
            return $text;
        }
        if (is_string($style)) {
            $style = array($style);
        }
        if (!is_array($style)) {
            throw new \InvalidArgumentException("Style must be string or array.");
        }
        $sequences = array();
        foreach ($style as $s) {
            if (isset($this->themes[$s])) {
                $sequences = [...$sequences, ...$this->themeSequence($s)];
            } else {
                if ($this->isValidStyle($s)) {
                    $sequences[] = $this->styleSequence($s);
                } else {
                    throw new \InvalidArgumentException($s);
                }
            }
        }
        $sequences = array_filter($sequences, function (mixed $val): bool {
            return $val !== null;
        });
        if (empty($sequences)) {
            return $text;
        }
        return $this->escSequence(implode(';', $sequences)) . $text . $this->escSequence(self::RESET_STYLE);
    }

    /**
     * @param bool $forceStyle
     */
    public function setForceStyle(bool $forceStyle)
    {
        $this->forceStyle = (bool)$forceStyle;
    }

    /**
     * @return bool
     */
    public function isStyleForced(): bool
    {
        return $this->forceStyle;
    }

    /**
     * @param array $themes
     */
    public function setThemes(array $themes): void
    {
        $this->themes = array();
        foreach ($themes as $name => $styles) {
            $this->addTheme($name, $styles);
        }
    }

    /**
     * @param string $name
     * @param $styles
     */
    public function addTheme(string $name, $styles): void
    {
        if (is_string($styles)) {
            $styles = array($styles);
        }
        if (!is_array($styles)) {
            throw new \InvalidArgumentException("Style must be string or array.");
        }
        foreach ($styles as $style) {
            if (!$this->isValidStyle($style)) {
                throw new \InvalidArgumentException($style);
            }
        }
        $this->themes[$name] = $styles;
    }

    /**
     * @return array
     */
    public function getThemes(): array
    {
        return $this->themes;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasTheme(string $name): bool
    {
        return isset($this->themes[$name]);
    }

    /**
     * @param string $name
     */
    public function removeTheme(string $name): void
    {
        unset($this->themes[$name]);
    }

    /**
     * @return bool
     */
    public function isSupported(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            if (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT)) {
                return true;
            } elseif (getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON') {
                return true;
            }
            return false;
        } else {
            return function_exists('posix_isatty') && @posix_isatty(STDOUT);
        }
    }

    /**
     * @return bool
     */
    public function are256ColorsSupported(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT);
        } else {
            return strpos(getenv('TERM'), '256color') !== false;
        }
    }

    /**
     * @return array
     */
    public function getPossibleStyles(): array
    {
        return array_keys($this->styles);
    }

    /**
     * @param string $name
     * @return string[]
     */
    private function themeSequence(string $name): array
    {
        $sequences = array();
        foreach ($this->themes[$name] as $style) {
            $sequences[] = $this->styleSequence($style);
        }
        return $sequences;
    }

    /**
     * @param string $style
     * @return string
     */
    private function styleSequence(string $style): ?string
    {
        if (array_key_exists($style, $this->styles)) {
            return $this->styles[$style];
        }
        if (!$this->are256ColorsSupported()) {
            return null;
        }
        preg_match(self::COLOR256_REGEXP, $style, $matches);
        $type = $matches[1] === 'bg_' ? self::BACKGROUND : self::FOREGROUND;
        $value = $matches[2];
        return "$type;5;$value";
    }

    /**
     * @param string $style
     * @return bool
     */
    private function isValidStyle(string $style): bool
    {
        return array_key_exists($style, $this->styles) || preg_match(self::COLOR256_REGEXP, $style);
    }

    /**
     * @param string|int $value
     * @return string
     */
    private function escSequence(string $value): string
    {
        return "\033[{$value}m";
    }
}
