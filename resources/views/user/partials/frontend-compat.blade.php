@push('css')
<style>
    :root {
        --user-primary: #4a171e;
        --user-accent: #e3b143;
        --user-surface: #f6f2ec;
        --user-muted: #8a6f73;
    }

    .user-page {
        color: #2f2f2f;
    }

    .user-page .main {
        padding: 0;
        background: transparent;
    }

    .user-page .page-header,
    .user-page .breadcrumb-wrap {
        display: none;
    }

    .user-page .container-fluid,
    .user-page .container {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .user-page .page-title {
        color: var(--user-primary);
        font-size: 24px;
        margin-bottom: 6px;
    }

    .user-page .page-description {
        color: var(--user-muted);
        margin-bottom: 16px;
    }

    .user-page .page-title,
    .user-page .page-description {
        display: none;
    }

    .user-page .divider {
        border-top-color: rgba(74, 23, 30, 0.15);
        margin: 14px 0 18px;
    }

    .user-page .section-header h2 {
        color: var(--user-primary);
        font-size: 18px;
    }

    .user-page .btn-view {
        background-color: var(--user-primary);
        border-color: var(--user-primary);
        color: #fff;
    }

    .user-page .btn-view:hover {
        background-color: #5c2028;
    }

    .user-page .btn-remove {
        background-color: #a53939;
        border-color: #a53939;
        color: #fff;
    }

    .user-page .btn-remove:hover {
        background-color: #8f2f2f;
    }

    .user-page .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        border: 1px solid #efe2d7;
    }

    .user-page .dataTable thead th {
        background: var(--user-primary);
        color: #fff;
        border-bottom: 1px solid #3b1016;
    }

    .user-page .dataTable tbody tr:nth-child(even) {
        background: #faf6f1;
    }

    .user-page .dataTable tbody tr:hover {
        background: #f3e7dd;
    }

    .user-page .dataTable tbody td a {
        color: var(--user-primary);
    }

    .user-page .breadcrumb,
    .user-page .breadcrumb a {
        color: var(--user-muted);
    }

    .user-page .search-box {
        border-color: #ead9cc;
    }

    .user-page .search-box:focus {
        outline: none;
        border-color: var(--user-accent);
        box-shadow: 0 0 0 0.2rem rgba(227, 177, 67, 0.15);
    }

    .user-page .card {
        background: #fff;
        border: 1px solid #efe2d7;
    }

    .user-page .card .card-body {
        background: #fff;
    }

    .user-page .card,
    .user-page .table-responsive {
        box-shadow: 0 6px 20px rgba(74, 23, 30, 0.08);
    }

    .user-page .page-header,
    .user-page .page-title {
        font-weight: 700;
    }

    .user-page .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 4px 10px;
        border-radius: 6px;
        border: 1px solid transparent;
        color: var(--user-primary) !important;
        margin: 0 2px;
    }

    .user-page .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .user-page .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--user-primary) !important;
        color: #fff !important;
        border-color: var(--user-primary);
    }

    .user-page .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: rgba(74, 23, 30, 0.08) !important;
        border-color: rgba(74, 23, 30, 0.2);
    }

    .user-page .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        color: #b3a7a9 !important;
    }
</style>
@endpush
