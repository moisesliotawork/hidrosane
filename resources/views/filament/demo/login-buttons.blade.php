@if (config('demo.login'))
    {{--
        Clases limitadas a las que trae el CSS precompilado de Filament
        (solo paletas gray y primary): la app no compila Tailwind propio,
        así que una utilidad de otra paleta no tendría estilo detrás.
    --}}
    <div class="mt-6 border-t border-gray-200 pt-4 dark:border-white/10">
        <p class="mb-2 text-center text-xs text-gray-500 dark:text-gray-400">
            Acceso de demostración
        </p>

        <div class="flex flex-wrap justify-center gap-2">
            @foreach (config('demo.perfiles') as $clave => $perfil)
                <form method="POST" action="{{ route('demo.login', $clave) }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                    >
                        Entrar como {{ $perfil['etiqueta'] }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif
