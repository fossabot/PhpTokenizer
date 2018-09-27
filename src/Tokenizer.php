<?php
/**
 * Created by PhpStorm.
 * User: m.korobitsyn
 * Date: 27.09.18
 * Time: 11:42
 */

namespace Tsukasa\PhpTokenizer;

class Tokenizer {

    /** @var RulesInterface  */
    private $ruleset;

    /** @var array */
    private $map;

    public function __construct(RulesInterface $rules)
    {
        $this->ruleset = $rules;
        $this->map = $rules->getMap();
    }

    public function parse($content) {

        $result = [];

        if (preg_match_all('/(\<\?(php|\=|)).*?(\?\>|$)/s', $content, $match)) {

            $len = 0;
            foreach ($match[0] as $i => $code) {
                $str = $code;
                $len += strlen($str);
                list($line, $col) = self::getCoordinates($content, $len);

                $result[] = $this->tokenize($code, $line);
            }

        }

        if (count($result) <= 1) {
            return current($result);
        }

        return array_merge(...$result);
    }


    protected function constructRules($ruleset = null)
    {
        $patterns = [];
        $types = [];
        $rules = [];

        foreach ($this->ruleset->getPatterns($ruleset) as $type => $pattern) {

            if (is_array($pattern)) {
                $rules[] = $pattern[1];
                $patterns[] = $pattern[0];
            }
            else {
                $rules[] = null;
                $patterns[] = $pattern;
            }

            $types[] = $type = !is_numeric($type)
                ? $type
                : '__ANY__';
        }

        return [
            '~(' . implode(')|(', $patterns) . ')~As',
            $types,
            $rules
        ];
    }

    protected function tokenize($input, $start_line = 0, $ruleset = null)
    {

        $result = [];
        $len = 0;

        list($re, $types, $rules) = $this->constructRules($ruleset);
        preg_match_all($re, $input, $tokens, PREG_SET_ORDER);
        $count = count($types);

        foreach ($tokens as $k => $token) {
            $type = null;
            $rule = null;

            for ($i = 1; $i <= $count; $i++) {
                if (!isset($token[$i])) {
                    break;
                } elseif ($token[$i] != null) {
                    $type = $types[$i - 1];
                    $rule = $rules[$i - 1];
                    break;
                }
            }

            $str = $token[0];
            $len += strlen($str);
            list($line, $col) = static::getCoordinates($input, $len);

            $line += $start_line;

            if (isset($this->map[$type])) {
                $type = $this->map[$type];
            }

            if ($rule) {
                $result = array_merge($result,
                    $this->tokenize($str, $line, $rule)
                );
            }
            else {
                $result[] = defined($type)
                    ? [ constant($type), $str, $line, $type]
                    : $str;
            }


        }
        if ($len !== strlen($input)) {

            list($line, $col) = static::getCoordinates($input, $len);
            $token = str_replace("\n", '\n', substr($input, $len, 10));
            throw new \RuntimeException("Unexpected '$token' on line $line, column $col.");
        }
        return $result;
    }


    /**
     * Returns position of token in input string.
     * @param  string
     * @param  int
     * @return array of [line, column]
     */
    public static function getCoordinates($text, $offset)
    {
        $text = substr($text, 0, $offset);
        return [substr_count($text, "\n") + 1, $offset - strrpos("\n" . $text, "\n") + 1];
    }
}