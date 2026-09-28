<?php

namespace Tests\Unit;

use App\Support\PublicMedia;
use Tests\TestCase;

class PublicMediaTest extends TestCase
{
    public function test_url_builds_storage_path_from_relative_file(): void
    {
        config(['app.url' => 'https://example.test']);

        $this->assertSame(
            'https://example.test/storage/doctors/profile/a.jpg',
            PublicMedia::url('doctors/profile/a.jpg')
        );
        $this->assertSame(
            'https://example.test/storage/doctors/certificates/c.pdf',
            PublicMedia::url('storage/doctors/certificates/c.pdf')
        );
    }

    public function test_url_leaves_absolute_http_untouched(): void
    {
        $this->assertSame(
            'https://cdn.example/file.jpg',
            PublicMedia::url('https://cdn.example/file.jpg')
        );
    }

    public function test_url_returns_null_for_empty_path(): void
    {
        $this->assertNull(PublicMedia::url(null));
        $this->assertNull(PublicMedia::url(''));
    }
}
