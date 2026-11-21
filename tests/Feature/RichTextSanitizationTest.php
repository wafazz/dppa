<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RichTextSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizer_removes_scripts_handlers_and_unsafe_links(): void
    {
        $clean = app(HtmlSanitizer::class)->sanitize(
            '<p onclick="x()">Hai</p><script>alert(1)</script><a href="javascript:alert(2)">klik</a>'
            .'<a href="https://contoh.my/laman">laman</a><iframe src="https://evil.example"></iframe>',
        );

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('href="https://contoh.my/laman"', $clean);
    }

    public function test_sanitizer_keeps_the_allowed_formatting_set(): void
    {
        $clean = app(HtmlSanitizer::class)->sanitize(
            '<p><strong>Tebal</strong> <em>miring</em> <u>garis</u></p><ol><li>Satu</li></ol><ul><li>Dua</li></ul>',
        );

        $this->assertStringContainsString('<strong>Tebal</strong>', $clean);
        $this->assertStringContainsString('<em>miring</em>', $clean);
        $this->assertStringContainsString('<ol>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
    }

    public function test_plain_text_conversion_keeps_line_breaks_and_decodes_entities(): void
    {
        $text = app(HtmlSanitizer::class)->toPlainText('<p>Baris 1</p><p>Baris &amp; 2</p><ul><li>Item</li></ul>');

        $this->assertSame("Baris 1\nBaris & 2\nItem", $text);
    }

    public function test_empty_editor_output_is_treated_as_no_text(): void
    {
        $sanitizer = app(HtmlSanitizer::class);

        $this->assertFalse($sanitizer->hasText('<p><br></p>'));
        $this->assertFalse($sanitizer->hasText(null));
        $this->assertTrue($sanitizer->hasText('<p>Ada teks</p>'));
    }

    public function test_stored_unsafe_html_is_sanitized_again_when_served(): void
    {
        $member = Member::factory()->create([
            'business_description' => '<p onmouseover="steal()">Perihal</p><script>alert(1)</script>',
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.members.show', $member))
            ->assertOk()
            ->assertDontSee('onmouseover', escape: false)
            ->assertDontSee('<script', escape: false);
    }
}
