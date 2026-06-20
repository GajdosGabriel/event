<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\IndexFilterRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IndexFilterRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_filters_with_published(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['published' => 'true']);
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertTrue($filters['published']);
    }

    public function test_get_filters_with_unpublished(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['unpublished' => 'true']);
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertFalse($filters['published']);
    }

    public function test_get_filters_with_status(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['status' => 'published']);
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertEquals('published', $filters['status']);
    }

    public function test_get_filters_with_search(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['search' => '  letny festival  ']);
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertEquals('letny festival', $filters['search']);
    }

    public function test_get_filters_with_per_page(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['per_page' => '50']);
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertEquals(50, $filters['per_page']);
    }

    public function test_get_filters_default_per_page(): void
    {
        $request = new IndexFilterRequest();
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertEquals(15, $filters['per_page']);
        $this->assertNull($filters['blocked']);
        $this->assertNull($filters['deleted']);
        $this->assertNull($filters['published']);
        $this->assertNull($filters['search']);
    }

    public function test_get_filters_with_blocked(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['blocked' => 'true']);
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertTrue($filters['blocked']);
    }

    public function test_get_filters_with_deleted(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['deleted' => 'false']);
        $request->setMethod('GET');

        $filters = $request->getFilters();

        $this->assertFalse($filters['deleted']);
    }

    public function test_validation_accepts_true_string_for_boolean_filters(): void
    {
        $request = IndexFilterRequest::create('/api/admin/events', 'GET', [
            'published' => 'true',
        ]);

        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertTrue($request->boolean('published'));
    }

    public function test_validation_invalid_status(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['status' => 'invalid_status']);
        $request->setMethod('GET');

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_validation_per_page_max(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['per_page' => '200']);
        $request->setMethod('GET');

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_validation_search_max(): void
    {
        $request = new IndexFilterRequest();
        $request->merge(['search' => str_repeat('a', 251)]);
        $request->setMethod('GET');

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
    }
}
