<?php

namespace App\Support;

/**
 * Генерация SVG-графиков без внешних библиотек — артефакт получается
 * самодостаточным файлом, который можно открыть или скачать.
 */
class SvgChart
{
    private const PALETTE = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#dc2626', '#0891b2'];

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, array{name: string, data: array<int, float|int>}>  $series
     */
    public function line(array $labels, array $series, string $title = '', bool $money = false, int $width = 760, int $height = 380): string
    {
        $series = array_map(function (array $s, int $i): array {
            $s['data'] = array_map('floatval', $s['data'] ?? []);
            $s['color'] ??= self::PALETTE[$i % count(self::PALETTE)];

            return $s;
        }, $series, array_keys($series));

        $m = ['t' => 44, 'r' => 20, 'b' => 44, 'l' => 78];
        $iw = $width - $m['l'] - $m['r'];
        $ih = $height - $m['t'] - $m['b'];

        $max = $this->niceMax($this->maxOf($series) * 1.15);
        $count = max(count($labels), 1);
        $stepX = $count > 1 ? $iw / ($count - 1) : 0;

        $svg = $this->open($width, $height, $title);

        for ($g = 0; $g <= 4; $g++) {
            $y = $m['t'] + $ih - $ih * $g / 4;
            $svg .= '<line x1="'.$m['l'].'" y1="'.$y.'" x2="'.($width - $m['r']).'" y2="'.$y.'" stroke="#e2e8f0"/>';
            $value = $max * $g / 4;
            $svg .= $this->text($m['l'] - 10, $y + 4, $this->fmt($value, $money), 'end', '#94a3b8', 11);
        }

        foreach ($labels as $i => $label) {
            if (count($labels) > 12 && $i % 2) {
                continue;
            }
            $x = $m['l'] + $i * $stepX;
            $anchor = $i === 0 ? 'start' : ($i === count($labels) - 1 ? 'end' : 'middle');
            $svg .= $this->text($x, $height - 16, (string) $label, $anchor, '#94a3b8', 11);
        }

        foreach ($series as $s) {
            $points = [];
            foreach ($s['data'] as $i => $v) {
                $points[] = ($m['l'] + $i * $stepX).','.round($m['t'] + $ih - ($v / max($max, 0.0001)) * $ih, 2);
            }
            if ($points === []) {
                continue;
            }

            $svg .= '<polyline points="'.implode(' ', $points).'" fill="none" stroke="'.$s['color'].'" stroke-width="2.5" stroke-linejoin="round"/>';
            foreach ($points as $i => $point) {
                [$x, $y] = explode(',', $point);
                $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="3.5" fill="'.$s['color'].'" stroke="#fff" stroke-width="2"/>';
            }
        }

        return $svg.$this->legend($series, $width, $height).'</svg>';
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, float|int>  $data
     */
    public function bar(array $labels, array $data, string $title = '', bool $money = false, int $width = 760, int $height = 380): string
    {
        $data = array_map('floatval', $data);
        $m = ['t' => 44, 'r' => 20, 'b' => 44, 'l' => 78];
        $iw = $width - $m['l'] - $m['r'];
        $ih = $height - $m['t'] - $m['b'];
        $max = $this->niceMax((float) max($data ?: [0]) * 1.15);

        $svg = $this->open($width, $height, $title);

        for ($g = 0; $g <= 4; $g++) {
            $y = $m['t'] + $ih - $ih * $g / 4;
            $svg .= '<line x1="'.$m['l'].'" y1="'.$y.'" x2="'.($width - $m['r']).'" y2="'.$y.'" stroke="#e2e8f0"/>';
            $svg .= $this->text($m['l'] - 10, $y + 4, $this->fmt($max * $g / 4, $money), 'end', '#94a3b8', 11);
        }

        $slot = $iw / max(count($data), 1);
        $bw = max(6, $slot * 0.56);

        foreach ($data as $i => $value) {
            $h = $value / max($max, 0.0001) * $ih;
            $x = $m['l'] + $i * $slot + ($slot - $bw) / 2;
            $svg .= '<rect x="'.round($x, 2).'" y="'.round($m['t'] + $ih - $h, 2).'" width="'.round($bw, 2).'" height="'.round(max($h, 1), 2).'" rx="4" fill="'.self::PALETTE[0].'"/>';

            if (count($data) <= 14) {
                $svg .= $this->text($m['l'] + $i * $slot + $slot / 2, $height - 16, (string) ($labels[$i] ?? ''), 'middle', '#94a3b8', 11);
            }
        }

        return $svg.'</svg>';
    }

    /**
     * @param  array<int, array{label: string, value: float|int}>  $slices
     */
    public function donut(array $slices, string $title = '', bool $money = false, int $size = 340): string
    {
        $slices = array_values(array_filter($slices, fn (array $s) => (float) $s['value'] > 0));
        $total = array_sum(array_column($slices, 'value'));

        $r = (int) ($size * 0.36);
        $thick = (int) ($size * 0.14);
        $c = (int) ($size / 2);

        $svg = $this->open($size, $size, $title);

        if ($total <= 0) {
            $svg .= '<circle cx="'.$c.'" cy="'.$c.'" r="'.$r.'" fill="none" stroke="#e2e8f0" stroke-width="'.$thick.'"/>';
        } else {
            $acc = -M_PI / 2;
            foreach ($slices as $i => $slice) {
                $angle = (float) $slice['value'] / $total * M_PI * 2;
                $x1 = $c + $r * cos($acc);
                $y1 = $c + $r * sin($acc);
                $x2 = $c + $r * cos($acc + $angle);
                $y2 = $c + $r * sin($acc + $angle);
                $large = $angle > M_PI ? 1 : 0;
                $color = $slice['color'] ?? self::PALETTE[$i % count(self::PALETTE)];

                $svg .= '<path d="M '.round($x1, 2).' '.round($y1, 2).' A '.$r.' '.$r.' 0 '.$large.' 1 '.round($x2, 2).' '.round($y2, 2).'" fill="none" stroke="'.$color.'" stroke-width="'.$thick.'"/>';
                $acc += $angle;
            }
        }

        $svg .= '<text x="'.$c.'" y="' . ($c - 2) . '" text-anchor="middle" font-size="22" font-weight="700" fill="#0f172a">'.$this->escape($this->fmt((float) $total, $money)).'</text>';
        $svg .= '<text x="'.$c.'" y="' . ($c + 18) . '" text-anchor="middle" font-size="11" fill="#94a3b8">всего</text>';

        return $svg.'</svg>';
    }

    private function open(int $width, int $height, string $title): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" font-family="Segoe UI, system-ui, sans-serif">'
            .'<rect width="100%" height="100%" fill="#ffffff"/>'
            .($title !== '' ? '<text x="16" y="26" font-size="15" font-weight="700" fill="#0f172a">'.$this->escape($title).'</text>' : '');
    }

    /**
     * @param  array<int, array{name: string, color: string}>  $series
     */
    private function legend(array $series, int $width, int $height): string
    {
        if (count($series) < 2) {
            return '';
        }

        $svg = '';
        $x = 16;
        $y = $height - 6;

        foreach ($series as $s) {
            $svg .= '<rect x="'.$x.'" y="'.($y - 9).'" width="10" height="10" rx="2" fill="'.$s['color'].'"/>';
            $svg .= $this->text($x + 15, $y, $s['name'], 'start', '#64748b', 11);
            $x += 30 + mb_strlen($s['name']) * 7;
        }

        return $svg;
    }

    private function text(float $x, float $y, string $text, string $anchor, string $color, int $size): string
    {
        return '<text x="'.round($x, 2).'" y="'.round($y, 2).'" text-anchor="'.$anchor.'" font-size="'.$size.'" fill="'.$color.'">'.$this->escape($text).'</text>';
    }

    /**
     * @param  array<int, array{data: array<int, float|int>}>  $series
     */
    private function maxOf(array $series): float
    {
        $max = 0.0;
        foreach ($series as $s) {
            foreach ($s['data'] as $v) {
                $max = max($max, (float) $v);
            }
        }

        return $max;
    }

    private function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 10.0;
        }

        $exp = 10 ** (int) floor(log10($value));
        $f = $value / $exp;
        $step = match (true) {
            $f <= 1 => 1,
            $f <= 2 => 2,
            $f <= 2.5 => 2.5,
            $f <= 5 => 5,
            default => 10,
        };

        return $step * $exp;
    }

    private function fmt(float $value, bool $money): string
    {
        if (! $money) {
            return number_format($value, abs($value) < 10 && $value != (int) $value ? 1 : 0, ',', ' ');
        }

        $abs = abs($value);

        if ($abs >= 1_000_000) {
            return round($value / 1_000_000, 1).' млн ₽';
        }
        if ($abs >= 1000) {
            return round($value / 1000).' тыс ₽';
        }

        return number_format($value, 0, ',', ' ').' ₽';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}