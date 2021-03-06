<?php
/**
 * Created by PhpStorm.
 * User: m.korobitsyn
 * Date: 27.09.18
 * Time: 11:42
 */

namespace Tsukasa\PhpTokenizer;

use Tsukasa\PhpTokenizer\Checkers\CheckerInterface;

class Tokenizer {

    /** @var RulesInterface  */
    protected $ruleset;

    /** @var array */
    protected $map;

    protected $_prepared;

    public function __construct(RulesInterface $rules)
    {
        $this->ruleset = $rules;
        $this->map = $rules->getMap();
    }

    public function parse($content)
    {
        return $this->tokenize($content);
    }


    protected function constructRules($ruleset = null)
    {
        if ($ruleset && isset($this->_prepared[$ruleset])) {
            return $this->_prepared[$ruleset];
        }

        $patterns = [];
        $types = [];
        $rules = [];

        foreach ($this->ruleset->getPatterns($ruleset) as $type => $set)
        {
            $rule = null;
            $pattern = $set;

            if (is_array($set)) {
                @list($pattern, $rule) = $set;
            }

            $type = $type = !is_numeric($type)
                ? $type
                : '__ANY__';

            if (!is_array($pattern)) {
                $pattern = [$pattern];
            }

            foreach ($pattern as $item) {
                $patterns[] = $item;
                $types[] = $type;
                $rules[] = $rule;
            }
        }

        return $this->_prepared[$ruleset] = [
            '~(' . implode(')|(', $patterns) . ')~Aus',
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
            $len += mb_strlen($str);
            list($line, $col) = static::getCoordinates($input, $len);

            $line += $start_line;

            if (isset($this->map[$type])) {
                $type = $this->map[$type];
            }

            if ($rule instanceof CheckerInterface) {
                $type = $rule->check($str);
            }
            elseif ($rule) {
                $result[] = $this->tokenize($str, $line, $rule);
                continue;
            }

            $result[] = [
                isset(Helper::$constants[$type])
                    ? [ Helper::$constants[$type], $str, $line, $type]
                    : $str
            ];
        }

        if ($len !== strlen($input)) {

            list($line, $col) = static::getCoordinates($input, $len);
            $token = str_replace(
                ["\n", "\r"], ['\n', '\r'],
                mb_substr($input, $len, 10)
            );
            throw new \RuntimeException("Unexpected '$token' on line $line, column $col.");
        }
        return array_merge(...$result);
    }


    /**
     * Returns position of token in input string.
     * @param  string
     * @param  int
     * @return array of [line, column]
     */
    public static function getCoordinates($text, $offset)
    {
        $text = mb_substr($text, 0, $offset);
        return [
            mb_substr_count($text, "\n") + 1,
            $offset - mb_strrpos("\n" . $text, "\n") + 1
        ];
    }
}