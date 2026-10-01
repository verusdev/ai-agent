<?php

namespace App\Support;

use App\Models\Agent;

/**
 * Файловая песочница задачи: агент пишет только внутрь своей рабочей папки.
 */
class Workspace
{
    public function ensure(Agent $agent): string
    {
        $path = $agent->workspacePath();

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * Превращает путь от модели в абсолютный, не выпуская за пределы песочницы.
     */
    public function resolve(Agent $agent, string $relative): string
    {
        $relative = str_replace(['\\', "\0"], '/', trim($relative));
        $relative = ltrim($relative, '/');

        if ($relative === '') {
            throw new \InvalidArgumentException('Путь не указан.');
        }

        $base = $this->ensure($agent);
        $absolute = realpath($base).DIRECTORY_SEPARATOR.$relative;

        // Если файл уже существует, проверяем его настоящее расположение.
        if (file_exists($absolute)) {
            $real = realpath($absolute);
            $realBase = realpath($base);

            if ($real === false || ! str_starts_with($real, $realBase.DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Выход за пределы рабочей папки запрещён.');
            }

            return $real;
        }

        // Для нового файла проверяем, что нормализованный путь не выходит вверх.
        $normalized = $this->normalize($base.DIRECTORY_SEPARATOR.$relative);

        if (! str_starts_with($normalized, realpath($base).DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Выход за пределы рабочей папки запрещён.');
        }

        return $normalized;
    }

    public function write(Agent $agent, string $relative, string $content): string
    {
        $absolute = $this->resolve($agent, $relative);
        $dir = dirname($absolute);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать папку '.$dir);
        }

        $max = (int) config('agents.workspace.max_file_bytes', 2_000_000);
        if (strlen($content) > $max) {
            throw new \RuntimeException('Файл слишком большой: '.round(strlen($content) / 1024).' КБ при лимите '.round($max / 1024).' КБ.');
        }

        file_put_contents($absolute, $content);

        return $absolute;
    }

    public function read(Agent $agent, string $relative, int $maxChars = 20000): string
    {
        $absolute = $this->resolve($agent, $relative);

        if (! is_file($absolute)) {
            throw new \RuntimeException('Файл не найден: '.$relative);
        }

        $content = (string) file_get_contents($absolute);

        return mb_strlen($content) > $maxChars
            ? mb_substr($content, 0, $maxChars)."\n… (файл показан частично)"
            : $content;
    }

    /**
     * @return array<int, array{path: string, size: int, modified: string}>
     */
    public function listFiles(Agent $agent): array
    {
        $base = $this->ensure($agent);
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $files[] = [
                'path' => str_replace($base.DIRECTORY_SEPARATOR, '', $file->getPathname()),
                'size' => $file->getSize(),
                'modified' => date('d.m.Y H:i', (int) $file->getMTime()),
            ];
        }

        usort($files, fn (array $a, array $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    private function normalize(string $path): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}