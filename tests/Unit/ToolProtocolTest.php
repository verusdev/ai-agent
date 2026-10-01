<?php

namespace Tests\Unit;

use App\Services\Llm\ToolProtocol;
use PHPUnit\Framework\TestCase;

class ToolProtocolTest extends TestCase
{
    public function test_it_parses_a_tool_call_block(): void
    {
        $text = 'Сделаю это.'."\n".ToolProtocol::OPEN.'{"tool": "write_file", "args": {"path": "a.md", "content": "Привет"}}'.ToolProtocol::CLOSE;

        $call = ToolProtocol::parse($text);

        $this->assertSame('write_file', $call['name']);
        $this->assertSame('a.md', $call['args']['path']);
        $this->assertSame('Привет', $call['args']['content']);
    }

    public function test_it_parses_a_fenced_json_block(): void
    {
        $text = ToolProtocol::OPEN."```json\n{\"tool\": \"calculate\", \"args\": {\"expression\": \"2+2\"}}\n```".ToolProtocol::CLOSE;

        $call = ToolProtocol::parse($text);

        $this->assertSame('calculate', $call['name']);
        $this->assertSame('2+2', $call['args']['expression']);
    }

    public function test_it_accepts_arguments_as_a_json_string(): void
    {
        $text = ToolProtocol::OPEN.'{"tool": "list_files", "arguments": "{\"path\": \"x\"}"}'.ToolProtocol::CLOSE;

        $call = ToolProtocol::parse($text);

        $this->assertSame('list_files', $call['name']);
        $this->assertSame('x', $call['args']['path']);
    }

    public function test_it_returns_null_without_a_block(): void
    {
        $this->assertNull(ToolProtocol::parse('Просто текст ответа.'));
    }

    public function test_it_strips_the_block_from_user_text(): void
    {
        $text = 'До '.ToolProtocol::OPEN.'{"tool": "x", "args": {}}'.ToolProtocol::CLOSE.' после';

        $this->assertSame('До  после', ToolProtocol::strip($text));
    }
}