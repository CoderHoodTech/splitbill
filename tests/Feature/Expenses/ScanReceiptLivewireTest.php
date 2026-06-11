<?php

declare(strict_types=1);

use App\Actions\Groups\CreateGroup;
use App\Livewire\Expenses\AddExpense;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('services.ocr.base_url', 'http://127.0.0.1:8001');
    config()->set('services.ocr.timeout', 5);
    Storage::fake('local');

    $this->create = app(CreateGroup::class);
});

function makeFriendsForOcr(User $a, User $b): void
{
    Friendship::query()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    Friendship::query()->create(['user_id' => $b->id, 'friend_id' => $a->id]);
}

it('prefills total_amount when the OCR service returns a guess', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response([
            'raw_text' => "TOTAL 50.000\n",
            'total_guess' => 50000,
            'line_items' => [
                ['name' => 'Nasi Padang', 'amount' => 30000],
                ['name' => 'Es Teh', 'amount' => 20000],
            ],
            'engine' => 'tesseract',
        ]),
    ]);

    $owner = User::factory()->create();
    $alice = User::factory()->create();
    makeFriendsForOcr($owner, $alice);
    $group = $this->create->execute($owner, 'Trip', [$alice->id]);

    Livewire::actingAs($owner)
        ->test(AddExpense::class, ['group' => $group])
        ->set('receipt', UploadedFile::fake()->image('struk.jpg'))
        ->assertSet('total_amount', 50000)
        ->assertSet('hasOcrResult', true)
        ->assertSet('ocrEngine', 'tesseract')
        ->assertSet('ocrSuggestedTotal', 50000)
        ->assertSet('description', 'Nasi Padang')
        ->assertSet('receipt', null);
});

it('marks the result available but with no total when the receipt has no detected total', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response([
            'raw_text' => 'blurry',
            'total_guess' => null,
            'line_items' => [],
            'engine' => 'tesseract',
        ]),
    ]);

    $owner = User::factory()->create();
    $group = $this->create->execute($owner, 'Solo');

    Livewire::actingAs($owner)
        ->test(AddExpense::class, ['group' => $group])
        ->set('receipt', UploadedFile::fake()->image('struk.jpg'))
        ->assertSet('hasOcrResult', true)
        ->assertSet('ocrSuggestedTotal', null)
        ->assertSet('total_amount', 0);
});

it('degrades gracefully when the OCR service is unreachable', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $owner = User::factory()->create();
    $group = $this->create->execute($owner, 'Solo');

    Livewire::actingAs($owner)
        ->test(AddExpense::class, ['group' => $group])
        ->set('receipt', UploadedFile::fake()->image('struk.jpg'))
        ->assertSet('hasOcrResult', true)
        ->assertSet('total_amount', 0)
        ->assertSet('receipt', null)
        ->assertSet('ocrError', fn ($e) => is_string($e) && str_contains($e, 'unreachable'));
});

it('rejects non-image uploads', function () {
    $owner = User::factory()->create();
    $group = $this->create->execute($owner, 'Solo');

    Livewire::actingAs($owner)
        ->test(AddExpense::class, ['group' => $group])
        ->set('receipt', UploadedFile::fake()->create('struk.pdf', 100, 'application/pdf'))
        ->assertHasErrors('receipt');
});

it('clears OCR state on dismissOcr', function () {
    Http::fake([
        '127.0.0.1:8001/ocr' => Http::response([
            'raw_text' => '',
            'total_guess' => 50000,
            'line_items' => [],
            'engine' => 'tesseract',
        ]),
    ]);

    $owner = User::factory()->create();
    $group = $this->create->execute($owner, 'Solo');

    Livewire::actingAs($owner)
        ->test(AddExpense::class, ['group' => $group])
        ->set('receipt', UploadedFile::fake()->image('struk.jpg'))
        ->assertSet('hasOcrResult', true)
        ->call('dismissOcr')
        ->assertSet('hasOcrResult', false)
        ->assertSet('ocrSuggestedTotal', null)
        ->assertSet('ocrEngine', null);
});
