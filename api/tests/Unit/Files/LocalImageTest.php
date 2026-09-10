<?php

namespace Tests\Unit\Files;

use App\Models\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalImageTest extends TestCase
{
    public function test_local_images_prefer_prod_without_server_requests_or_changing_write_disk(): void
    {
        $this->app->instance('env', 'local');
        config(['filesystems.local_image_prod_fallback' => true,
            'filesystems.image_prod_root' => 'prod',
            'filesystems.disks.s3' => [
                'driver' => 's3', 'key' => 'test', 'secret' => 'test',
                'region' => 'eu-north-1', 'bucket' => 'test',
                'url' => 'https://images.example.test', 'root' => 'dev',
            ],
        ]);
        Storage::forgetDisk('s3');
        Http::preventStrayRequests();
        $file = new File(['disk' => 's3', 'mime_type' => 'image/jpeg', 'thumb' => 'event/thumb.jpg']);
        $dev = 'https://images.example.test/dev/event/thumb.jpg';
        $this->assertSame('https://images.example.test/prod/event/thumb.jpg#local-image-fallback='.rawurlencode($dev), $file->thumb_image_url);
        $this->assertSame($dev, Storage::disk('s3')->url('event/thumb.jpg'));
        $this->app->instance('env', 'production');
        $this->assertSame($dev, $file->thumb_image_url);
        Http::assertNothingSent();
    }
}
