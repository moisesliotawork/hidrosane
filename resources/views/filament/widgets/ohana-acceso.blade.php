<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Ohana</x-slot>

        <x-slot name="description">
            Vuelve a Ohana sin escribir credenciales. El enlace caduca al minuto y sirve una sola vez.
        </x-slot>

        <div class="flex flex-wrap gap-2">
            @foreach (\App\Filament\Widgets\OhanaAccesoWidget::perfiles() as $clave => $perfil)
                <x-filament::button
                    tag="a"
                    color="ohana"
                    href="{{ route('ohana.ir', $clave) }}"
                    target="_blank"
                    rel="noopener"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Entrar como {{ $perfil['etiqueta'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
