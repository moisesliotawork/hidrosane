<x-filament-widgets::widget>
    <style>
        @keyframes parpadeo-escritorio {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0; }
        }

        .escritorio-notas {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .75rem 1rem;
            padding: .85rem 1rem;
            border-radius: .85rem;
            border: 1px solid rgba(22, 101, 52, .18);
            background: rgba(22, 101, 52, .06);
        }

        .dark .escritorio-notas {
            border-color: rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .05);
        }

        .escritorio-notas-counter {
            font-weight: 700;
            font-size: .95rem;
            color: #166534;
            margin: 0;
        }

        .dark .escritorio-notas-counter {
            color: #ffffff;
        }

        .escritorio-notas-counter .num {
            animation: parpadeo-escritorio 1s step-start infinite;
            color: #dc2626;
            font-size: 1.35rem;
        }

        .escritorio-ver-notas {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .4rem .85rem;
            border-radius: 999px;
            background: #65a30d;
            color: #000;
            font-weight: 800;
            font-size: .78rem;
            letter-spacing: .02em;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .12);
            transition: filter .15s ease, transform .08s ease;
        }

        .escritorio-ver-notas:hover {
            filter: brightness(0.96);
        }

        .escritorio-ver-notas:active {
            transform: scale(.98);
        }
    </style>

    <div class="escritorio-notas">
        <p class="escritorio-notas-counter">
            Tienes &nbsp;&nbsp;<span class="num">{{ $this->getNotasHoyCount() }}</span>&nbsp;&nbsp;&nbsp; notas para hoy
        </p>

        <a href="{{ $this->getNotasUrl() }}" class="escritorio-ver-notas">
            VER NOTAS
        </a>
    </div>
</x-filament-widgets::widget>
