<?php

namespace Domreg\Epp;

/**
 * React-like extension to SimpleXMLElement
 */
class XMLElement {

    const XML_HEADER = '<?xml version="1.0" encoding="UTF-8"?>';

    public static function make($name, $attrs = null, $children = null) {
        return new self($name, $attrs, $children);
    }

    /**
     * Make an XML element only if it has children.
     *
     * @param  string $name
     * @param  array|null $attrs
     * @param  XMLElement[]|string|null $children
     * @return XMLElement|null
     */
    public static function optional($name, $attrs = null, $children = null) {
        if (!$children) {
            return null;
        }
        if (is_array($children)) {
            $values = array_filter($children, function ($x) {
                return ((string) $x) !== '';
            });
            if (count($values) <= 0) {
                return null;
            }
        }
        return new self($name, $attrs, $children);
    }

    public $name;
    public $attrs = [];
    public $children = [];

    public function __construct($name, $attrs = null, $children = null) {
        $this->name = $name;
        if (is_array($attrs)) {
            $this->attrs = $attrs;
        }
        if (is_array($children)) {
            $this->children = $children;
        }
        else if (isset($children)) {
            $this->children[] = $children;
        }
    }

    public function __toString() {
        return $this->render();
    }

    public function asXML() {
        return $this->render();
    }

    public function render($topLevel = true) {
        // Prepend a header
        $out = $topLevel ? self::XML_HEADER : '';
        // Build attribute string
        $attrs = '';
        foreach ($this->attrs as $i => $value) {
            if (!isset($value) || $value === false) {
                continue;
            }
            if ($value === true) {
                $attrs .= ' ' . $i;
                continue;
            }
            $value = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $attrs .= ' ' . $i . '="' . $value . '"';
        }
        // Build content string
        $content = '';
        array_walk_recursive($this->children, function ($x) use (&$content) {
            if ($x instanceof XMLElement) {
                $content .= $x->render(false);
            }
            else {
                $content .= htmlspecialchars((string) $x, ENT_XML1, 'UTF-8');
            }
        });
        // Return the string
        if (strlen($content) === 0) {
            $out .= "<{$this->name}{$attrs} />";
        }
        else {
            $out .= "<{$this->name}{$attrs}>{$content}</{$this->name}>";
        }
        // Beautify
        if ($topLevel) {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = FALSE;
            @$dom->loadXML($out);
            $dom->formatOutput = TRUE;
            return $dom->saveXML();
        }
        return $out;
    }

}
