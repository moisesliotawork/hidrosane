@once
    <style>
        .fi-main:has(.notas-page),
        .fi-main-ctn:has(.notas-page),
        .fi-page:has(.notas-page),
        .fi-page.notas-page {
            background-color: #dbeafe !important;
        }

        .notas-page {
            background-color: #dbeafe;
        }

        .dark .fi-main:has(.notas-page),
        .dark .fi-main-ctn:has(.notas-page),
        .dark .fi-page:has(.notas-page),
        .dark .fi-page.notas-page {
            background-color: #1e3a8a !important;
        }

        .dark .notas-page {
            background-color: #1e3a8a;
        }

        .notas-page:not(.fi-resource-create-record-page) .fi-section,
        .notas-page:not(.fi-resource-create-record-page) .fi-section-content,
        .notas-page:not(.fi-resource-create-record-page) .fi-section-content-ctn,
        .notas-page:not(.fi-resource-create-record-page) .fi-section-header {
            background-color: transparent !important;
            box-shadow: none !important;
        }
    </style>
@endonce
