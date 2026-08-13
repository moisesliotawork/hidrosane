<div class="flex shrink-0 items-center gap-2" wire:key="nro-contrato-admin-toolbar-search">
    <label
        for="nro-contrato-admin-busqueda"
        class="whitespace-nowrap text-sm font-bold"
        style="color:#dc2626;"
    >
        Nº contrato admin
    </label>
    <input
        id="nro-contrato-admin-busqueda"
        type="search"
        placeholder="Ej: 1 o 001"
        autocomplete="off"
        wire:model.live.debounce.400ms="nroContratoBusqueda"
        class="fi-input block w-40 rounded-lg border-0 bg-white py-1.5 text-sm text-gray-950 shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:ring-2 dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
        style="box-shadow: inset 0 0 0 2px #dc2626;"
    />
</div>
