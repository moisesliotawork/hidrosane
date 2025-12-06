{{-- resources/views/cream-transfers/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Solicitud de transferencia de crema')

@section('content')
    <div class="min-h-screen flex items-center justify-center py-10">
        <div class="w-full max-w-xl px-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl backdrop-blur-sm p-6 space-y-4">

                {{-- Encabezado --}}
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Solicitud de transferencia de crema
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Revisa la solicitud y decide si la aceptas o la rechazas.
                    </p>
                </div>

                {{-- Datos de la solicitud --}}
                <div class="space-y-1 text-sm">
                    <p>
                        <span class="font-semibold text-slate-300">De:</span>
                        <span class="text-slate-100">{{ $transfer->fromComercial->name }}</span>
                    </p>
                    <p>
                        <span class="font-semibold text-slate-300">Para:</span>
                        <span class="text-slate-100">{{ $transfer->toComercial->name }}</span>
                    </p>
                    <p>
                        <span class="font-semibold text-slate-300">Cantidad:</span>
                        <span class="text-slate-100">{{ $transfer->amount }} crema(s)</span>
                    </p>
                    @if($transfer->date)
                        <p class="text-xs text-slate-500 mt-2">
                            Fecha: {{ \Carbon\Carbon::parse($transfer->date)->format('d/m/Y') }}
                        </p>
                    @endif
                </div>

                {{-- Botones de acción --}}
                <div class="mt-4 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('cream-transfers.accept', $transfer) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                            Aceptar
                        </button>
                    </form>

                    <form method="POST" action="{{ route('cream-transfers.reject', $transfer) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                            Rechazar
                        </button>
                    </form>
                </div>

                {{-- Enlace para volver opcional --}}
                <div class="pt-4 border-t border-slate-800 text-right">
                    <a href="{{ url('/comercial') }}"
                        class="text-xs text-slate-400 hover:text-slate-200 underline decoration-dotted">
                        Ir al panel comercial
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection