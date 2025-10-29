<?php

namespace Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\CsvTaskImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CsvImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(40);
        $this->withSession(['_token' => $this->token]);

        $this->admin = User::factory()->create(['role' => Role::ADMIN->value]);
    }

    public function test_show_displays_import_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('csv-import.index'));

        $response->assertOk();
        $response->assertViewIs('csv-import.index');
    }

    public function test_import_requires_csv_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('csv-import.import'), []);

        $response->assertSessionHasErrors(['csv_file']);
    }

    public function test_import_with_valid_csv_calls_service_and_flashes_results(): void
    {
        $csvContent = "Locatie,Activiteit,Omschrijving,Prioriteit,Team 1,Geplande datum,Medewerker\n" .
            'Amsterdam Isolatorweg 30,To do,Controleronde,Hoog,,01/12/2024,' . "\n";

        // Fake uploaded CSV file with specific content
        $file = UploadedFile::fake()->createWithContent('tasks.csv', $csvContent);

        // Mock the service and bind to container
        $mock = Mockery::mock(CsvTaskImportService::class);
        $this->app->instance(CsvTaskImportService::class, $mock);

        $parsed = [['some' => 'data']];
        $results = ['success_count' => 2, 'error_count' => 1, 'errors' => ['row 3 invalid']];

        $mock->shouldReceive('parseCsvContent')
            ->once()
            ->with($csvContent)
            ->andReturn($parsed);

        $mock->shouldReceive('importTasks')
            ->once()
            ->with($parsed)
            ->andReturn($results);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('csv-import.import'), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('import_results', $results);

        // Check the success message composition
        $this->assertStringContainsString('Import voltooid: 2 taken succesvol geïmporteerd.', session('success'));
        $this->assertStringContainsString('1 fouten opgetreden.', session('success'));
    }

    public function test_import_with_empty_parsed_data_returns_error(): void
    {
        $csvContent = "header1,header2\nvalue1,value2\n";
        $file = UploadedFile::fake()->createWithContent('tasks.csv', $csvContent);

        $mock = Mockery::mock(CsvTaskImportService::class);
        $this->app->instance(CsvTaskImportService::class, $mock);

        $mock->shouldReceive('parseCsvContent')
            ->once()
            ->with($csvContent)
            ->andReturn([]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('csv-import.import'), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['csv_file']);
    }

    public function test_import_handles_service_exception(): void
    {
        $csvContent = "a,b\n1,2\n";
        $file = UploadedFile::fake()->createWithContent('tasks.csv', $csvContent);

        $mock = Mockery::mock(CsvTaskImportService::class);
        $this->app->instance(CsvTaskImportService::class, $mock);

        $mock->shouldReceive('parseCsvContent')
            ->once()
            ->with($csvContent)
            ->andThrow(new \Exception('Boom'));

        $response = $this->actingAs($this->admin)
            ->withHeader('X-CSRF-TOKEN', $this->token)
            ->post(route('csv-import.import'), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['csv_file']);
        $this->assertStringContainsString('Er is een fout opgetreden bij het importeren: Boom', session('errors')->first('csv_file'));
    }

    public function test_download_template_returns_csv_with_headers_and_attachment(): void
    {
        $response = $this->actingAs($this->admin)->get(route('csv-import.template'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="taken_import_template.csv"');

        $content = $response->getContent();
        $this->assertStringContainsString('"Locatie","Activiteit","Omschrijving","Prioriteit","Team 1","Geplande datum","Medewerker"', $content);
        // also contains at least one sample row
        $this->assertStringContainsString('"Amsterdam Isolatorweg 30"', $content);
    }
}
