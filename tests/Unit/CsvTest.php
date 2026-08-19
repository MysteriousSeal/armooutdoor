<?php

namespace Tests\Unit;

use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class CsvTest extends TestCase
{
    use RefreshDatabase;

    private function captureStream(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_it_streams_a_header_row_and_data_rows(): void
    {
        $response = Csv::download('test.csv', ['Name', 'Price'], [
            ['Gourde', '24.90'],
            ['Sac', '89.00'],
        ]);

        $content = $this->captureStream($response);

        $this->assertSame("Name,Price\nGourde,24.90\nSac,89.00\n", $content);
    }

    public function test_it_sets_the_csv_content_type(): void
    {
        $response = Csv::download('test.csv', ['A'], [['1']]);

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_it_escapes_values_containing_commas(): void
    {
        $response = Csv::download('test.csv', ['Name'], [['Gourde, inox 1L']]);

        $this->assertStringContainsString('"Gourde, inox 1L"', $this->captureStream($response));
    }

    public function test_it_escapes_values_containing_quotes(): void
    {
        $response = Csv::download('test.csv', ['Name'], [['12" screen']]);

        $this->assertStringContainsString('"12"" screen"', $this->captureStream($response));
    }

    public function test_it_escapes_values_containing_newlines(): void
    {
        $response = Csv::download('test.csv', ['Note'], [["Line one\nLine two"]]);

        $this->assertStringContainsString("\"Line one\nLine two\"", $this->captureStream($response));
    }

    public function test_an_empty_row_set_still_produces_a_header_only_csv(): void
    {
        $response = Csv::download('test.csv', ['A', 'B'], []);

        $this->assertSame("A,B\n", $this->captureStream($response));
    }
}
