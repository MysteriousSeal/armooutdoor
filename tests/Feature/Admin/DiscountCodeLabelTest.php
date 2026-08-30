<?php

namespace Tests\Feature\Admin;

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La carte PDF d'un code de réduction : 70 × 50 mm, paysage, et rien
 * d'autre que le code dessus.
 */
class DiscountCodeLabelTest extends TestCase
{
    use RefreshDatabase;

    private function code(string $code = 'NOEL2026'): DiscountCode
    {
        return DiscountCode::query()->create(['code' => $code, 'type' => 'percentage', 'value' => 10]);
    }

    public function test_the_card_downloads_as_a_pdf_named_after_the_code(): void
    {
        $code = $this->code();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/discount-codes/'.$code->id.'/label')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('code-noel2026.pdf');
    }

    public function test_the_card_carries_the_code_and_nothing_else(): void
    {
        $html = view('admin.discounts.code-pdf', ['code' => 'NOEL2026'])->render();

        $this->assertStringContainsString('NOEL2026', $html);
        // Rien d'autre, à la demande : pas d'enseigne. Le montant, lui, ne
        // peut pas fuir — la vue ne reçoit que le code.
        $this->assertStringNotContainsString('Armo', $html);
        $this->assertStringNotContainsString('Outdoor', $html);
    }

    public function test_the_size_follows_the_length_and_always_fits_the_card(): void
    {
        $short = view('admin.discounts.code-pdf', ['code' => 'NOEL'])->render();
        $long = view('admin.discounts.code-pdf', ['code' => 'BIENVENUE-CLUB-HIVER-2026'])->render();

        $this->assertStringContainsString('font-size: 30pt', $short);
        $this->assertStringContainsString('font-size: 7pt', $long);

        // Le cas qui rognait : chaque longueur de 1 à 40 doit tenir dans
        // les 180 pt utiles, au pire glyphe près (0,8 em) interlettrage
        // compris — la même arithmétique que la vue, vérifiée de bout en
        // bout plutôt que sur deux exemples.
        foreach (range(1, 40) as $length) {
            $html = view('admin.discounts.code-pdf', ['code' => str_repeat('W', $length)])->render();
            preg_match('/font-size: (\d+)pt/', $html, $size);
            preg_match('/letter-spacing: ([\d.]+)pt/', $html, $spacing);

            $width = $length * ((float) $size[1] * 0.8 + (float) $spacing[1]);
            $this->assertLessThanOrEqual(180, $width, 'A code of '.$length.' characters overflows the card.');
        }
    }

    public function test_guests_are_refused(): void
    {
        $code = $this->code();

        $this->get('/admin/discount-codes/'.$code->id.'/label')
            ->assertRedirect('/admin');
    }
}
