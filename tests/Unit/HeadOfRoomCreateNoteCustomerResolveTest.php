<?php

namespace Tests\Unit;

use App\Filament\HeadOfRoom\Resources\NoteResource\Pages\CreateNote;
use App\Models\Customer;
use ReflectionMethod;
use Tests\TestCase;

class HeadOfRoomCreateNoteCustomerResolveTest extends TestCase
{
    public function test_match_customer_by_name_finds_shared_phone_sibling(): void
    {
        $diana = new Customer([
            'first_names' => 'Diana',
            'last_names' => 'Oliveira Rodriguez',
        ]);
        $diana->id = 1151;

        $esteban = new Customer([
            'first_names' => 'Esteban',
            'last_names' => 'Estevez Rial',
        ]);
        $esteban->id = 3370;

        $page = new CreateNote;
        $method = new ReflectionMethod(CreateNote::class, 'matchCustomerByName');
        $method->setAccessible(true);

        $matched = $method->invoke($page, collect([$diana, $esteban]), [
            'first_names' => 'Esteban',
            'last_names' => 'Estevez Rial',
        ]);

        $this->assertNotNull($matched);
        $this->assertSame(3370, $matched->id);
    }

    public function test_match_customer_by_name_returns_null_when_no_match(): void
    {
        $diana = new Customer([
            'id' => 1151,
            'first_names' => 'Diana',
            'last_names' => 'Oliveira Rodriguez',
        ]);

        $page = new CreateNote;
        $method = new ReflectionMethod(CreateNote::class, 'matchCustomerByName');
        $method->setAccessible(true);

        $matched = $method->invoke($page, collect([$diana]), [
            'first_names' => 'Esteban',
            'last_names' => 'Estevez Rial',
        ]);

        $this->assertNull($matched);
    }
}
