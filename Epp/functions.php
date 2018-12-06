<?php

namespace Domreg\Epp;

use DateTimeImmutable;

/**
 * Queries an XML document using xpath, and returns the first entry found.
 *
 * @param  SimpleXMLElement $xml
 * @param  string $xpath
 * @return string
 */
function xml_query($xml, $xpath) {
    if ($xml === null || $xml === false) {
        return null;
    }
    $nodes = $xml->xpath($xpath);
    $node = reset($nodes);
    if (!$node) {
        return null;
    }
    return (string) $node;
}

/**
 * Queries an XML document using xpath, and returns the first entry found.
 *
 * This variation returns a DateTimeImmutable object.
 *
 * @param  SimpleXMLElement $xml
 * @param  string $xpath
 * @return DateTimeImmutable
 */
function xml_query_as_datetime($xml, $xpath) {
    $x = xml_query($xml, $xpath);
    return $x === null ? null : new DateTimeImmutable($x);
}

/**
 * Queries an XML document using xpath, and returns the first entry found.
 *
 * This variation returns an integer value.
 *
 * @param  SimpleXMLElement $xml
 * @param  string $xpath
 * @return int
 */
function xml_query_as_int($xml, $xpath) {
    $x = xml_query($xml, $xpath);
    return $x === null ? null : (int) $x;
}

/**
 * Queries an XML document using xpath, and returns all entries found.
 *
 * @param  SimpleXMLElement $xml
 * @param  string $xpath
 * @return string
 */
function xml_query_all($xml, $xpath) {
    if ($xml === null || $xml === false) {
        return null;
    }
    $nodes = $xml->xpath($xpath);
    $result = [];
    foreach ($nodes as $node) {
        $result[] = (string) $node;
    }
    return $result;
}
