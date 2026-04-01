<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_set_and_get_setting(): void
    {
        AppSetting::set('test_key', 'test_value');

        $this->assertEquals('test_value', AppSetting::get('test_key'));
    }

    public function test_returns_default_if_key_not_found(): void
    {
        $this->assertEquals('default', AppSetting::get('non_existent', 'default'));
    }

    public function test_can_store_arrays(): void
    {
        $data = ['a' => 1, 'b' => 2];
        AppSetting::set('array_key', $data);

        $this->assertEquals($data, AppSetting::get('array_key'));
    }
}
