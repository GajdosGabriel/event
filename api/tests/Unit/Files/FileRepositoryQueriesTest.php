<?php

namespace Tests\Unit\Files;

use App\Enums\FileType;
use App\Models\File;
use App\Models\User;
use App\Repositories\Contracts\FileRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FileRepositoryQueriesTest extends TestCase
{
    use DatabaseTransactions;

    private FileRepository $fileRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileRepository = app(FileRepository::class);
    }

    public function test_public_index_query_returns_only_primary_files(): void
    {
        $primary = $this->createFile(['is_primary' => true]);
        $secondary = $this->createFile(['is_primary' => false]);

        $results = $this->fileRepository->publicIndexQuery()->get();

        $this->assertTrue($results->contains('id', $primary->id));
        $this->assertFalse($results->contains('id', $secondary->id));
    }

    public function test_admin_index_query_includes_soft_deleted_files(): void
    {
        $active = $this->createFile();
        $deleted = $this->createFile();
        $deleted->delete();

        $results = $this->fileRepository->adminIndexQuery()->get();

        $this->assertTrue($results->contains('id', $active->id));
        $this->assertTrue($results->contains('id', $deleted->id));
    }

    private function createFile(array $overrides = []): File
    {
        $user = User::factory()->create();

        return File::create(array_merge([
            'fileable_id' => $user->id,
            'fileable_type' => User::class,
            'name' => 'test-file',
            'original_name' => 'test-file.jpg',
            'extension' => 'jpg',
            'size' => 1234,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'path' => 'uploads/test-file.jpg',
            'thumb' => null,
            'checksum' => null,
            'type' => FileType::IMAGE->value,
            'is_primary' => true,
            'meta' => null,
        ], $overrides));
    }
}
