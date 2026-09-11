<?php

namespace Tests\Unit;

use App\Models\ProductReview;
use App\Models\User;
use Tests\TestCase;

/**
 * The monogram worn by the testimonial avatars.
 */
class ReviewerInitialsTest extends TestCase
{
    private function guest(string $name): ProductReview
    {
        return new ProductReview(['author_name' => $name]);
    }

    public function test_an_account_signs_with_both_initials(): void
    {
        $review = new ProductReview;
        $review->setRelation('user', new User(['first_name' => 'Colas', 'last_name' => 'dupont']));

        // The name reads « Colas D. », so the monogram reads CD.
        $this->assertSame('CD', $review->reviewerInitials());
    }

    public function test_a_one_word_name_wears_its_first_two_letters(): void
    {
        // A marketplace username: a lone L said less than the name does.
        $this->assertSame('LY', $this->guest('lynxronin')->reviewerInitials());
    }

    public function test_a_one_letter_name_wears_that_letter(): void
    {
        $this->assertSame('J', $this->guest('j')->reviewerInitials());
    }

    public function test_a_one_word_accented_name_keeps_its_accent(): void
    {
        $this->assertSame('ÉL', $this->guest('élodie')->reviewerInitials());
    }

    public function test_a_longer_name_stops_at_two(): void
    {
        $this->assertSame('JP', $this->guest('Jean Pierre Martin')->reviewerInitials());
    }

    public function test_an_accented_name_keeps_its_letter(): void
    {
        $this->assertSame('ÉM', $this->guest('élodie martin')->reviewerInitials());
    }

    public function test_a_nameless_review_wears_nothing(): void
    {
        $this->assertSame('', $this->guest('   ')->reviewerInitials());
    }
}
