<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_cleans_and_preserves_allowed_rich_formatting(): void
    {
        $input = '<p>Hello <span style="color: #ef4444; font-weight: bold;">World</span></p><h2>Subtitle</h2><img src="/storage/posts/photo.jpg" alt="Photo" />';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringContainsString('style="color: #ef4444; font-weight: bold;"', $output);
        $this->assertStringContainsString('<h2>Subtitle</h2>', $output);
        $this->assertStringContainsString('<img src="/storage/posts/photo.jpg"', $output);
    }

    public function test_strips_dangerous_scripts_and_handlers(): void
    {
        $input = '<p>Test<script>alert("xss")</script><img src="/storage/posts/1.jpg" onerror="alert(1)" /><a href="javascript:alert(1)">Click</a></p>';
        $output = HtmlSanitizer::clean($input);

        $this->assertStringNotContainsString('script', $output);
        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringNotContainsString('javascript:', $output);
    }
}
