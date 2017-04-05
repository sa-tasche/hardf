<?php

namespace pietercolpaert\hardf;
/** a clone of the N3Lexer class from the N3js code by Ruben Verborgh **/
// **N3Lexer** tokenizes N3 documents.
class N3Lexer
{
    //private $fromCharCode = String.fromCharCode; //TODO

    // Regular expression and replacement string to escape N3 strings.
    // Note how we catch invalid unicode sequences separately (they will trigger an error).
    private $escapeSequence = '/\\[uU]|\\\(.)/';
    private $escapeReplacements = [
      '\\' => '\\', "'"=> "'", '"' => '"',
      'n' => '\n', 'r' => '\r', 't' => '\t', 'f' => '\f', 'b' => '\b',
      '_' => '_', '~' => '~', '.' => '.', '-' => '-', '!' => '!', '$' => '$', '&' => '&',
      '(' => '(', ')' => ')', '*' => '*', '+' => '+', ',' => ',', ';' => ';', '=' => '=',
      '/' => '/', '?' => '?', '#' => '#', '@' => '@', '%' => '%'
    ];
    private $illegalIriChars = '/[\x00-\x20<>\\"\{\}\|\^\`]/';

    private $input;
    private $line = 1;

    private $prevTokenType;
    
    public function __construct($options = []) {
        
        // In line mode (N-Triples or N-Quads), only simple features may be parsed
        if ($options["lineMode"]) {
            // Don't tokenize special literals
            $this->tripleQuotedString = '/$0^/';
            $this->number = '/$0^/';
            $this->boolean = '/$0^/';
            // Swap the tokenize method for a restricted version
            /*$this->tokenize = $this->tokenize; //TODO: what was this originally?
            $this->tokenize = function ($input, $callback) {
                $this->tokenize($input, function ($error, $token) {
                    if (!$error && preg_match('/^(?:IRI|prefixed|literal|langcode|type|\.|eof)$/',$token["type"]))
                        $callback && callback($error, $token);
                    else
                        $callback && callback($error || self::_syntaxError($token['type'], $callback = null));
                });
                };*/
        }
        // Enable N3 functionality by default
        $this->n3Mode = $options["n3"] !== false;
        // Disable comment tokens by default
        $this->comments = isset($options["comments"])?$options["comments"]:null;
    }

    // ## Regular expressions
    private $iri ='/^<((?:[^ <>{}\\]|\\[uU])+)>[ \t]*/'; // IRI with escape sequences; needs sanity check after unescaping
    private $unescapedIri =  '/^<([^\x00-\x20<>\\"\{\}\|\^\`]*)>[ \t]*/'; // IRI without escape sequences; no unescaping
    private $unescapedString= '/^"[^"\\\]+"(?=[^"\\\])/'; // non-empty string without escape sequences 
    private $singleQuotedString= '/^"[^"\\]*(?:\\.[^"\\]*)*"(?=[^"\\])|^\'[^\'\\]*(?:\\.[^\'\\]*)*\'(?=[^\'\\])/';
    private $tripleQuotedString = '/^""("[^"\\]*(?:(?:\\.|"(?!""))[^"\\]*)*")""|^\'\'(\'[^\'\\]*(?:(?:\\.|\'(?!\'\'))[^\'\\]*)*\')\'\'/';
    private $langcode =  '/^@([a-z]+(?:-[a-z0-9]+)*)(?=[^a-z0-9\-])/i';
    private $prefix = '/^((?:[A-Za-z\xc0-\xd6\xd8-\xf6])(?:\.?[\-0-9A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6])*)?:(?=[#\s<])/';

    private $prefixed = "/^((?:[A-Za-z\xc0-\xd6\xd8-\xf6])(?:\.?[\-0-9A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6])*)?:((?:(?:[0-:A-Z_a-z\xc0-\xd6\xd8-\xf6]|%[0-9a-fA-F]{2}|\\[!#-\/;=?\-@_~])(?:(?:[\.\-0-:A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6]|%[0-9a-fA-F]{2}|\\[!#-\/;=?\-@_~])*(?:[\-0-:A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6]|%[0-9a-fA-F]{2}|\\[!#-\/;=?\-@_~]))?)?)(?:[ \t]+|(?=\.?[,;!\^\s#()\[\]\{\}\"'<]))/";

    //private $prefixed = "/^((?:[A-Za-z\xc0-\xd6\xd8-\xf6])(?:\.?[\-0-9A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6\xf8-\u037d\u037f-\u1fff\u200c\u200d\u203f\u2040\u2070-\u218f\u2c00-\u2fef\u3001-\ud7ff\uf900-\ufdcf\ufdf0-\ufffd]|[\ud800-\udb7f][\udc00-\udfff])*)?:((?:(?:[0-:A-Z_a-z\xc0-\xd6\xd8-\xf6]|%[0-9a-fA-F]{2}|\\[!#-\/;=?\-@_~])(?:(?:[\.\-0-:A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6\xf8-\u037d\u037f-\u1fff\u200c\u200d\u203f\u2040\u2070-\u218f\u2c00-\u2fef\u3001-\ud7ff\uf900-\ufdcf\ufdf0-\ufffd]|[\ud800-\udb7f][\udc00-\udfff]|%[0-9a-fA-F]{2}|\\[!#-\/;=?\-@_~])*(?:[\-0-:A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6\xf8-\u037d\u037f-\u1fff\u200c\u200d\u203f\u2040\u2070-\u218f\u2c00-\u2fef\u3001-\ud7ff\uf900-\ufdcf\ufdf0-\ufffd]|[\ud800-\udb7f][\udc00-\udfff]|%[0-9a-fA-F]{2}|\\[!#-\/;=?\-@_~]))?)?)(?:[ \t]+|(?=\.?[,;!\^\s#()\[\]\{\}\"'<]))/";
    private $variable = '/^\?(?:(?:[A-Z_a-z\xc0-\xd6\xd8-\xf6])(?:[\-0-:A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6])*)(?=[.,;!\^\s#()\[\]\{\}"\'<])/';
    
    private $blank = '/^_:((?:[0-9A-Z_a-z\xc0-\xd6\xd8-\xf6])(?:\.?[\-0-9A-Z_a-z\xb7\xc0-\xd6\xd8-\xf6])*)(?:[ \t]+|(?=\.?[,;:\s#()\[\]\{\}"\'<]))/';
    //TODO: this doesn't work
    private $number = "/^[\-+]?(?:\d+\.?\d*([eE](?:[\-\+])?\d+)|\d*\.?\d+)(?=[.,;:\s#()\[\]\{\}\"'<])/";
    private $boolean = '/^(?:true|false)(?=[.,;\s#()\[\]\{\}"\'<])/';
    private $keyword = '/^@[a-z]+(?=[\s#<])/i';
    private $sparqlKeyword= '/^(?:PREFIX|BASE|GRAPH)(?=[\s#<])/i';
    private $shortPredicates= '/^a(?=\s+|<)/';
    private $newline= '/^[ \t]*(?:#[^\n\r]*)?(?:\r\n|\n|\r)[ \t]*/';
    private $comment= '/#([^\n\r]*)/';
    private $whitespace= '/^[ \t]+/';
    private $endOfFile= '/^(?:#[^\n\r]*)?$/';
    
    // ## Private methods
    // ### `_tokenizeToEnd` tokenizes as for as possible, emitting tokens through the callback
    private function tokenizeToEnd($callback, $inputFinished) {
        
        // Continue parsing as far as possible; the loop will return eventually
        $input = $this->input;
        // Signals the syntax error through the callback
        $reportSyntaxError = function ($self)  use ($callback, $input) { $callback($self->syntaxError(preg_match("/^\S*/", $input)[0]), null); };

        $outputComments = $this->comments;
        while (true) { //TODO
            // Count and skip whitespace lines
            $whiteSpaceMatch;
            $comment;
            while (preg_match($this->newline, $input, $whiteSpaceMatch)) {
                // Try to find a comment
                if ($outputComments && preg_match($this->comment, $whiteSpaceMatch[0], $comment))
                    callback(null, [ "line"=> $this->line, "type" => 'comment', "value"=> $comment[1], "prefix"=> '' ]);
                // Advance the input
                $input = substr($input,strlen($whiteSpaceMatch[0]), strlen($input));
                $this->line++;
            }
            // Skip whitespace on current line
            if (preg_match($this->whitespace, $input, $whiteSpaceMatch))
                $input = substr($input,strlen($whiteSpaceMatch[0]), strlen($input));

            // Stop for now if we're at the end
            if (preg_match($this->endOfFile, $input)) {
                // If the $input is finished, emit EOF
                if ($inputFinished) {
                    // Try to find a final comment
                    if ($outputComments && preg_match($this->comment, $input, $comment))
                        $callback(null, [ "line"=> $this->line, "type"=> 'comment', "value"=> $comment[1], "prefix"=> '' ]);
                    $callback($input = null, [ "line"=> $this->line, "type"=> 'eof', "value"=> '', "prefix"=> '' ]);
                }
                $this->input = $input;
                return $input;
            }

            // Look for specific token types based on the first character
            $line = $this->line;
            $type = '';
            $value = '';
            $prefix = '';
            $firstChar = $input[0];
            $match = null;
            $matchLength = 0;
            $unescaped = null;
            $inconclusive = false;
            switch ($firstChar) {
                case '^':
                    // We need at least 3 tokens lookahead to distinguish ^^<IRI> and ^^pre:fixed
                    if (strlen($input) < 3)
                        break;
                    // Try to match a type
                    else if ($input[1] === '^') {
                        $this->prevTokenType = '^^';
                        // Move to type IRI or prefixed name
                        $input = substr($input,2);
                        if ($input[0] !== '<') {
                            $inconclusive = true;
                            break;
                        }
                    }
                    // If no type, it must be a path expression
                    else {
                        if ($this->n3Mode) {
                            $matchLength = 1;
                            $type = '^';
                        }
                        break;
                    }
                    // Fall through in case the type is an IRI
                case '<':
                    // Try to find a full IRI without escape sequences
                    if (preg_match($this->unescapedIri, $input, $match)){
                        $type = 'IRI';
                        $value = $match[1];
                    }
                    
                    // Try to find a full IRI with escape sequences
                    else if (preg_match($this->iri, $input, $match)) {
                        $unescaped = $this->unescape(match[1]);
                        if ($unescaped === null || preg_match($illegalIriChars,$unescaped))
                            return $reportSyntaxError($this);
                        $type = 'IRI';
                        $value = $unescaped;
                    }
                    // Try to find a backwards implication arrow
                    else if ($this->n3Mode && strlen($input) > 1 && $input[1] === '=') {
                        $type = 'inverse';
                        $matchLength = 2;
                        $value = 'http://www.w3.org/2000/10/swap/log#implies';
                    }
                    
                    break;
                case '_':
                    // Try to find a blank node. Since it can contain (but not end with) a dot,
                    // we always need a non-dot character before deciding it is a prefixed name.
                    // Therefore, try inserting a space if we're at the end of the $input.
                    if ((preg_match($this->blank, $input, $match)) ||
                    $inputFinished && (preg_match($this->blank, $input . ' ', $match))) {
                        $type = 'blank';
                        $prefix = '_';
                        $value = $match[1];
                    }
                    
                    break;

                case '"':
                case "'":
                    // Try to find a non-empty double-quoted literal without escape sequences
                    if (preg_match($this->unescapedString, $input, $match)){
                        $type = 'literal';
                        $value = $match[0];
                    }
                // Try to find any other literal wrapped in a pair of single or double quotes
                    else if (preg_match($this->singleQuotedString, $input, $match)) {
                        $unescaped = $this->unescape($match[0]);
                        if ($unescaped === null)
                            return $reportSyntaxError($this);
                        $type = 'literal';
                        $value = preg_replace('/^'|'$/g', '"',$unescaped);
                    }
                    // Try to find a literal wrapped in three pairs of single or double quotes
                    else if (preg_match($this->tripleQuotedString, $input, $match)) {
                        $unescaped = isset($match[1])?$match[1]:$match[2];
                        // Count the newlines and advance line counter
                        $this->line .= strlen(preg_split('/\r\n|\r|\n/',$unescaped)) - 1;
                        $unescaped = $this->unescape($unescaped);
                        if ($unescaped === null)
                            return $reportSyntaxError($this);
                        $type = 'literal';
                        $value = preg_replace("/^'|'$/g", '"',$unescaped);
                    }
                break;

                case '?':
                    // Try to find a variable
                    if ($this->n3Mode && (preg_match($this->variable, $input, $match))) {    
                        $type = 'var';
                        $value = $match[0];
                    }
                    break;

                case '@':
                    // Try to find a language code
                    if ($this->prevTokenType === 'literal' && preg_match($this->langcode, $input, $match)){   
                        $type = 'langcode';
                        $value = $match[1];
                    }
            
                    // Try to find a keyword
                    else if (preg_match($this->keyword, $input, $match))
                        $type = $match[0];
                    break;

                case '.':
                    // Try to find a dot as punctuation
                    if (strlen($input) === 1 ? $inputFinished : ($input[1] < '0' || $input[1] > '9')) {
                        $type = '.';
                        $matchLength = 1;
                        break;
                    }
                    // Fall through to numerical case (could be a decimal dot)

                case '0':
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                case '8':
                case '9':
                case '+':
                case '-':
                    // Try to find a number
                    if (preg_match($this->number, $input, $match)) {
                        $type = 'literal';
                        $value = '"' . $match[0] . '"^^http://www.w3.org/2001/XMLSchema#' . (isset($match[1]) ? 'double' : (preg_match("/^[+\-]?\d+$/",$match[0]) ? 'integer' : 'decimal'));
                    }
                    break;
                case 'B':
                case 'b':
                case 'p':
                case 'P':
                case 'G':
                case 'g':
                    // Try to find a SPARQL-style keyword
                    if (preg_match($this->sparqlKeyword, $input, $match))
                        $type = strtoupper($match[0]);
                    else
                        $inconclusive = true;
                    break;

                case 'f':
                case 't':
                    // Try to match a boolean
                    if (preg_match($this->boolean, $input, $match)){
                        $type = 'literal';
                        $value = '"' . $match[0] . '"^^http://www.w3.org/2001/XMLSchema#boolean';
                    } else
                        $inconclusive = true;
                    break;

                case 'a':
                    // Try to find an abbreviated predicate
                    if (preg_match($this->shortPredicates, $input, $match)) {
                        $type = 'abbreviation';
                        $value = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
                    }
                    else
                        $inconclusive = true;
                    break;
                case '=':
                    // Try to find an implication arrow or equals sign
                    if ($this->n3Mode && strlen($input) > 1) {
                        $type = 'abbreviation';
                        if ($input[1] !== '>') {
                            $matchLength = 1;
                            $value = 'http://www.w3.org/2002/07/owl#sameAs';
                        }
                        else{   
                            $matchLength = 2;
                            $value = 'http://www.w3.org/2000/10/swap/log#implies';
                        }
                    }
                    break;

                case '!':
                    if (!$this->n3Mode)
                        break;
                case ',':
                case ';':
                case '[':
                case ']':
                case '(':
                case ')':
                case '{':
                case '}':
                    // The next token is punctuation
                    $matchLength = 1;
                    $type = $firstChar;
                    break;
                default:
                    $inconclusive = true;
            }

            // Some first characters do not allow an immediate decision, so inspect more
            if ($inconclusive) {
                // Try to find a prefix
                if (($this->prevTokenType === '@prefix' || $this->prevTokenType === 'PREFIX') && preg_match($this->prefix, $input, $match)){
                    $type = 'prefix';
                    $value = isset($match[1])?$match[1]:'';
                }
                // Try to find a prefixed name. Since it can contain (but not end with) a dot,
                // we always need a non-dot character before deciding it is a prefixed name.
                // Therefore, try inserting a space if we're at the end of the input.
                else if (preg_match($this->prefixed, $input, $match) || $inputFinished && (preg_match($this->prefixed, $input . ' ', $match))) {
                    $type = 'prefixed';
                    $prefix = isset($match[1])?$match[1]:'';
                    $value = $this->unescape($match[2]);
                }
            }

            // A type token is special: it can only be emitted after an IRI or prefixed name is read
            if ($this->prevTokenType === '^^') {
                switch ($type) {
                    case 'prefixed': $type = 'type';    break;
                    case 'IRI':      $type = 'typeIRI'; break;
                    default:         $type = '';
                }
            }

            // What if nothing of the above was found?
            if (!$type) {
                // We could be in streaming mode, and then we just wait for more input to arrive.
                // Otherwise, a syntax error has occurred in the input.
                // One exception: error on an unaccounted linebreak (= not inside a triple-quoted literal).
                if ($inputFinished || (!preg_match("/^'''|^\"\"\"/",$input) && preg_match("/\n|\r/",$input)))
                    return $reportSyntaxError($this);
                else {
                    $this->input = $input;
                    return $input;
                }
            }
            // Emit the parsed token
            $callback(null, [ "line"=> $line, "type"=> $type, "value"=>$value, "prefix"=> $prefix ]);
            $this->prevTokenType = $type;
            
            // Advance to next part to tokenize
            $input = substr($input,$matchLength>0?$matchLength:strlen($match[0]), strlen($input));
        }

    }

    // ### `_unescape` replaces N3 escape codes by their corresponding characters
    private function unescape($item) {
        return preg_replace_callback($this->escapeSequence, function ($sequence, $unicode4, $unicode8, $escapedChar) {
            $charCode;
            if ($unicode4) {
                $charCode = parseInt($unicode4, 16);
                return fromCharCode($charCode);
            }
            else if ($unicode8) {
                $charCode = parseInt($unicode8, 16);
                if ($charCode <= 0xFFFF) return fromCharCode($charCode);
                return fromCharCode(0xD800 . (($charCode -= 0x10000) / 0x400), 0xDC00 . ($charCode & 0x3FF));
            }
            else {
                $replacement = escapeReplacements[$escapedChar];
                if (!$replacement)
                    throw new Error();
                return $replacement;
            }
        },$item);
    }

    // ### `_syntaxError` creates a syntax error for the given issue
    private function syntaxError($issue, $line = 0) {
        $this->input = null;
        return new \Exception('Unexpected "' . $issue . '" on line ' . $line . '.');
    }


    // ## Public methods

    // ### `tokenize` starts the transformation of an N3 document into an array of tokens.
    // The input can be a string or a stream.
    public function tokenize($input, $finalize = true) {
        // If the input is a string, continuously emit tokens through the callback until the end
        $this->input = $input;
        $tokens = [];
        $error = "";
        $this->tokenizeToEnd(function ($e, $t) use (&$tokens,&$error) {
            if (isset($e)) {
                $error = $e;
            }
            array_push($tokens, $t);
        }, $finalize);
        if ($error) throw $error;
        return $tokens;
    }
    
    // Adds the data chunk to the buffer and parses as far as possible        
    public function tokenizeChunk($input) 
    {
        return $this->tokenize($input, false);
    }

    public function end()
    {
        // Parses the rest
        return $this->tokenizeToEnd(true);
    }
}

