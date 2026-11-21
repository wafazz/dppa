<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_utf8_bom_header_and_only_filtered_rows(): void
    {
        $approved = Member::factory()->approved()->create([
            'applicant_name' => 'Aminah Yusof',
            'member_no' => 'DPPA-2026-0001',
        ]);
        Member::factory()->create(['applicant_name' => 'Belum Lulus']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.api.members.export', ['status' => 'approved']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('No. Ahli', $content);
        $this->assertStringContainsString('Aminah Yusof', $content);
        $this->assertStringContainsString('DPPA-2026-0001', $content);
        $this->assertStringNotContainsString('Belum Lulus', $content);
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));
    }

    public function test_export_strips_html_from_rich_text_columns(): void
    {
        Member::factory()->create([
            'business_description' => '<p><strong>Kedai</strong> runcit</p>',
            'business_needs' => '<p>Latihan &amp; bantuan</p>',
        ]);

        $content = $this->actingAs(User::factory()->create())
            ->get(route('admin.api.members.export'))
            ->streamedContent();

        $this->assertStringContainsString('Kedai runcit', $content);
        $this->assertStringContainsString('Latihan & bantuan', $content);
        $this->assertStringNotContainsString('<strong>', $content);
    }

    public function test_cells_starting_with_formula_characters_are_neutralised(): void
    {
        Member::factory()->create([
            'business_name' => '=HYPERLINK("http://evil.example")',
        ]);

        $content = $this->actingAs(User::factory()->create())
            ->get(route('admin.api.members.export'))
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_export_includes_the_category_name(): void
    {
        $category = BusinessCategory::factory()->create(['name_bm' => 'Teknologi Maklumat Uji']);
        Member::factory()->create(['business_category_id' => $category->id]);

        $content = $this->actingAs(User::factory()->create())
            ->get(route('admin.api.members.export'))
            ->streamedContent();

        $this->assertStringContainsString('Teknologi Maklumat Uji', $content);
    }
}
