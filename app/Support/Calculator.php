<?php

namespace App\Support;

/**
 * Безопасный калькулятор для агента: рекурсивный спуск вместо eval().
 * Поддерживает + - * / % ^ скобки и функции sqrt, abs, round, min, max, log, ln, sin, cos, tan, exp, pow.
 */
class Calculator
{
    private const FUNCTIONS = [
        'sqrt', 'abs', 'round', 'floor', 'ceil', 'min', 'max', 'pow',
        'log', 'log10', 'ln', 'exp', 'sin', 'cos', 'tan', 'sign',
    ];

    public function evaluate(string $expression): float
    {
        // Пробелы убираем, запятая остаётся разделителем аргументов функций.
        $expression = trim(str_replace([' ', "\t", "\n"], '', $expression));

        if ($expression === '') {
            throw new \InvalidArgumentException('Пустое выражение.');
        }

        if (! preg_match('/^[0-9+\-*\/%^().,a-zA-Z_]+$/', $expression)) {
            throw new \InvalidArgumentException('Недопустимые символы в выражении.');
        }

        $position = 0;
        $value = $this->parseExpression($expression, $position);

        if ($position < strlen($expression)) {
            throw new \InvalidArgumentException('Не удалось разобрать выражение целиком.');
        }

        return round($value, 10);
    }

    private function parseExpression(string $s, int &$i): float
    {
        $value = $this->parseTerm($s, $i);

        while ($i < strlen($s) && ($s[$i] === '+' || $s[$i] === '-')) {
            $operator = $s[$i++];
            $right = $this->parseTerm($s, $i);
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(string $s, int &$i): float
    {
        $value = $this->parseUnary($s, $i);

        while ($i < strlen($s) && in_array($s[$i], ['*', '/', '%'], true)) {
            $operator = $s[$i++];
            $right = $this->parseUnary($s, $i);

            $value = match ($operator) {
                '*' => $value * $right,
                '/' => $this->divide($value, $right),
                default => $this->modulo($value, $right),
            };
        }

        return $value;
    }

    private function parseUnary(string $s, int &$i): float
    {
        if ($i < strlen($s) && ($s[$i] === '+' || $s[$i] === '-')) {
            $operator = $s[$i++];

            return $operator === '-' ? -$this->parseUnary($s, $i) : $this->parseUnary($s, $i);
        }

        return $this->parsePower($s, $i);
    }

    private function parsePower(string $s, int &$i): float
    {
        $base = $this->parsePrimary($s, $i);

        if ($i < strlen($s) && $s[$i] === '^') {
            $i++;
            $exponent = $this->parseUnary($s, $i); // правоассоциативно

            return $base ** $exponent;
        }

        return $base;
    }

    private function parsePrimary(string $s, int &$i): float
    {
        $this->skipSpaces($s, $i);

        if ($i >= strlen($s)) {
            throw new \InvalidArgumentException('Незавершённое выражение.');
        }

        if ($s[$i] === '(') {
            $i++;
            $value = $this->parseExpression($s, $i);
            $this->skipSpaces($s, $i);

            if ($i >= strlen($s) || $s[$i] !== ')') {
                throw new \InvalidArgumentException('Не закрыта скобка.');
            }
            $i++;

            return $value;
        }

        if (preg_match('/^pi/i', substr($s, $i), $m)) {
            $i += strlen($m[0]);

            return M_PI;
        }

        if (preg_match('/^e/i', substr($s, $i), $m) && ! preg_match('/^e[0-9]/i', substr($s, $i))) {
            $i += strlen($m[0]);

            return M_E;
        }

        if (preg_match('/^[a-z_][a-z0-9_]*/i', substr($s, $i), $m)) {
            $name = strtolower($m[0]);
            $i += strlen($m[0]);

            if (! in_array($name, self::FUNCTIONS, true)) {
                throw new \InvalidArgumentException('Неизвестная функция: '.$name);
            }

            $this->skipSpaces($s, $i);
            if ($i >= strlen($s) || $s[$i] !== '(') {
                throw new \InvalidArgumentException('Функция '.$name.' требует скобки с аргументами.');
            }
            $i++;

            $args = [];
            $this->skipSpaces($s, $i);

            if ($i < strlen($s) && $s[$i] === ')') {
                $i++;

                return $this->call($name, $args);
            }

            while (true) {
                $args[] = $this->parseExpression($s, $i);
                $this->skipSpaces($s, $i);

                if ($i < strlen($s) && $s[$i] === ',') {
                    $i++;
                    continue;
                }

                if ($i < strlen($s) && $s[$i] === ')') {
                    $i++;
                    break;
                }

                throw new \InvalidArgumentException('Некорректные аргументы функции '.$name.'.');
            }

            return $this->call($name, $args);
        }

        if (preg_match('/^\d*\.?\d+(e[+-]?\d+)?/i', substr($s, $i), $m)) {
            $i += strlen($m[0]);

            return (float) $m[0];
        }

        throw new \InvalidArgumentException('Неожиданный символ: '.($s[$i] ?? ''));
    }

    /**
     * @param  array<int, float>  $args
     */
    private function call(string $name, array $args): float
    {
        return match ($name) {
            'sqrt' => $this->guard($args[0] < 0 ? 'Подкоренное выражение меньше нуля.' : null, sqrt($args[0])),
            'abs' => abs($args[0]),
            'round' => round($args[0], (int) ($args[1] ?? 0)),
            'floor' => floor($args[0]),
            'ceil' => ceil($args[0]),
            'min' => min($args),
            'max' => max($args),
            'pow' => $args[0] ** $args[1],
            'log' => $this->guard($args[0] <= 0 ? 'Логарифм от неположительного числа.' : null, log($args[0])),
            'log10' => $this->guard($args[0] <= 0 ? 'Логарифм от неположительного числа.' : null, log10($args[0])),
            'ln' => $this->guard($args[0] <= 0 ? 'Логарифм от неположительного числа.' : null, log($args[0])),
            'exp' => exp($args[0]),
            'sin' => sin($args[0]),
            'cos' => cos($args[0]),
            'tan' => tan($args[0]),
            'sign' => $args[0] <=> 0,
            default => throw new \InvalidArgumentException('Неизвестная функция: '.$name),
        };
    }

    private function skipSpaces(string $s, int &$i): void
    {
        while ($i < strlen($s) && ctype_space($s[$i])) {
            $i++;
        }
    }

    private function divide(float $value, float $divisor): float
    {
        if ($divisor === 0.0) {
            throw new \InvalidArgumentException('Деление на ноль.');
        }

        return $value / $divisor;
    }

    private function modulo(float $value, float $divisor): float
    {
        if ($divisor === 0.0) {
            throw new \InvalidArgumentException('Деление на ноль.');
        }

        return fmod($value, $divisor);
    }

    private function guard(?string $error, float $value): float
    {
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        return $value;
    }
}