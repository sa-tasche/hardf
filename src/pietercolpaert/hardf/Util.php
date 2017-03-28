<?php

namespace pietercolpaert\hardf;

/** a clone of the N3Util class from the N3js code by Ruben Verborgh **/
class Util
{
    const XSD = 'http://www.w3.org/2001/XMLSchema#';
    const XSDSTRING  = self::XSD . 'string';
    const XSDINTEGER = self::XSD . 'integer';
    const XSDDECIMAL = self::XSD . 'decimal';
    const XSDFLOAT = self::XSD . 'float';
    const XSDDOUBLE = self::XSD . 'double';
    const XSDBOOLEAN = self::XSD . 'boolean';
    const RDFLANGSTRING = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString';
    
    // Tests whether the given entity (triple object) represents an IRI in the N3 library
    public static function isIRI($term) {
        if (!$term) { 
            return $term;
        }
        $firstChar = substr($term,0,1);
        return $firstChar !== '"' && $firstChar !== '_';
    }
    
    public static function isLiteral ($term)
    {
        return $term && substr($term,0,1) === '"';
    }

    public static function isBlank ($term)
    {
        return $term && substr($term,0,2) === '_:';
    }

    public static function isDefaultGraph ($term)
    {
        return empty($term);
    }

    // Tests whether the given $triple is in the default graph
    public static function inDefaultGraph ($triple)
    {
        return !(isset($triple) && $triple["graph"]);
    }

    // Gets the string value of a literal in the N3 library
    public static function getLiteralValue ($literal)
    {
        preg_match("/^\"(.*)\"/", $literal, $match); //TODO: somehow the copied regex did not work. To be checked. Contained [^]
        if (empty($match)) {
            throw new Error($literal + ' is not a literal');
        }
        return $match[1];
    }
    // Gets the type of a literal in the N3 library
    public static function getLiteralType ($literal)
    {
        preg_match('/^".*"(?:\^\^([^"]+)|(@)[^@"]+)?$/',$literal,$match);//TODO: somehow the copied regex did not work. To be checked. Contained [^] instead of the .
        if (empty($match))
            throw new Error($literal + ' is not a literal');
        if (isset($match[1])) {
            
            return $match[1];
        } else {
            return isset($match[2]) ? self::RDFLANGSTRING : self::XSDSTRING;
        }
    }
    

    // Gets the language of a literal in the N3 library
    public static function getLiteralLanguage ($literal)
    {
        preg_match('/^".*"(?:@([^@"]+)|\^\^[^"]+)?$/', $literal, $match); //TODO: [^] substituted by .
        if (empty($match))
            throw new Error($literal + ' is not a literal');
        return isset($match[1]) ? strtolower($match[1]) : '';
    }
            
    // Tests whether the given entity ($triple object) represents a prefixed name
    public static function isPrefixedName ($term)
    {
        return !empty($term) && preg_match("/^[^:\/\"']*:[^:\/\"']+$/", $term);
    }

    // Expands the prefixed name to a full IRI (also when it occurs as a literal's type)
    public static function expandPrefixedName ($prefixedName, $prefixes)
    {
        preg_match("/(?:^|\"\^\^)([^:\/#\"'\^_]*):[^\/]*$/", $prefixedName, $match, PREG_OFFSET_CAPTURE);
        $prefix = "";
        $base = "";
        $index = "";
        if (!empty($match)) {
            $prefix = $match[1][0];
            $base = $prefixes[$prefix];
            $index = $match[1][1];// TODO??
        }
        if ($base === 'undefined')
            return $prefixedName;

        // The match index is non-zero when expanding a literal's type
        if ($index === 0) {
            return $base . substr($prefixedName, sizeof($prefix) + 1);
        } else {
            return substr($prefixedName, 0, $index + 3) . $base . substr($prefixedName, $index + sizeof($prefix) + 4);
        }
            
    }

    // Creates an IRI in N3.js representation
    public static function createIRI ($iri)
    {
        return !empty($iri) && substr($iri,0,1) === '"' ? self::getLiteralValue($iri) : $iri;
    }
    

    // Creates a literal in N3.js representation
    public static function createLiteral ($value, $modifier = null)
    {        
        if (!$modifier) {       
            switch (gettype($value)) {
                case 'boolean':
                    $value = $value ? "true":"false";
                    $modifier = self::XSDBOOLEAN;
                    break;
                case 'integer':
                    $modifier = self::XSDINTEGER;
                    break;
                case 'double':
                    $modifier = self::XSDDOUBLE;
                    break;
                case 'float':
                    $modifier = self::XSDFLOAT;
                    break;
                default:
                    return '"' . $value . '"';
            }
        }
        return '"' . $value . (preg_match("/^[a-z]+(-[a-z0-9]+)*$/i", $modifier) ? '"@'  . strtolower($modifier) : '"^^' . $modifier);
    }
    
/* TODO -- CONVERT TO proper PHP
    // Creates a function that prepends the given IRI to a local name
    public static function prefix ($iri)
    {
        return self::prefixes({ '': $iri })('');
    };
        
    // Creates a function that allows registering and expanding prefixes
    public static function prefixes ($defaultPrefixes)
    {
        // Add all of the default prefixes
        var $prefixes = {};
        
        for ($defaultPrefixes as $index => $prefix) {
            processPrefix($prefix, defaultPrefixes[$prefix]);
        }
        
        // Registers a new prefix (if an IRI was specified)
        // or retrieves a function that expands an existing prefix (if no IRI was specified)
        function processPrefix($prefix, $iri) {
            // Create a new prefix if an IRI is specified or the prefix doesn't exist
            if ($iri || !($prefix in $prefixes)) {
                var $cache = {};
                $iri = $iri || '';
                // Create a function that expands the prefix
                $prefixes[$prefix] = function ($localName) {
                    return $cache[$localName] || ($cache[$localName] = $iri . $localName);
                };
            }
            return $prefixes[$prefix];
        }
        return $processPrefix;
    }
*/
};

