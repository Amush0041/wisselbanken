@push('css')
<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
<style>
    .breadcrumb {
        margin-bottom: 20px; 
        color: #666; 
        font-size: 14px;
    }
    .breadcrumb span {
        margin: 0 5px;
    }
    .page-title {
        font-size: 28px; 
        color:#4a171e;
        font-weight: bold; 
        margin-bottom: 10px;
    }
    .page-description {
        color: #666; 
        margin-bottom: 20px;
    }
    .action-buttons {
        margin-bottom: 30px;
    }
    .divider {
        border: none; 
        border-top: 1px solid #eee; 
        margin: 20px 0;
    }
    .section-header {
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 15px;
    }
    .search-tabs {
        display: flex; 
        align-items: center;
    }
    .search-box {
        padding: 6px 12px; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        margin-right: 15px;
    }
    .tab-button {
        background: none; 
        border: none; 
        font-weight: bold; 
        margin-right: 15px;
        cursor: pointer;
    }
    .tab-button.inactive {
        color: #666;
    }
    /* DataTables customization */
    #ordersTable {
        width: 100% !important;
    }
    #ordersTable thead th {
        text-align: left;
        padding: 12px;
        font-weight: bold;
        border-bottom: 1px solid #eee;
    }
    #ordersTable tbody td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
  
    /* Action buttons styles */
    .action-btns {
        display: flex;
        gap: 8px;
    }
    .btn-view {
        background-color: #4a171e;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 5px 10px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .btn-view:hover {
        background-color: #5d1d26;
    }
    .status-pending {
        color: #ffc107;
        font-weight: bold;
    }
    .status-completed {
        color: #28a745;
        font-weight: bold;
    }
    .status-cancelled {
        color: #dc3545;
        font-weight: bold;
    }
    .btn-icon {
        width: 14px;
        height: 14px;
    }
    /* Responsive table styles */
    @media screen and (max-width: 768px) {
        #ordersTable thead {
            display: none;
        }
        #ordersTable tr {
            display: block;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        #ordersTable td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
        }
        #ordersTable td:before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 10px;
            color: #4a171e;
        }
    }
</style>
@endpush

<main class="main">

    <div id="toast-container"></div>
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{url('/')}}" rel="nofollow">Home</a>
                <span></span> Orders
            </div>
        </div>
    </div>
    <section class="mt-4 mb-4">
        <div class="container-fluid px-4">

            <!-- Main Heading -->
            <h1 class="page-title">My Orders</h1>
            
            <hr class="divider">
            
            <!-- Orders Section -->
            <div>
                <div class="section-header">
                    <h2>Orders</h2>
                    <div class="search-tabs">
                        <input type="text" placeholder="Search" class="search-box" id="searchInput"> 
                    </div>
                </div>
                
                <!-- DataTable -->
                <div class="table-responsive">
                    <table id="ordersTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Order No</th>
                                <th>Project Name</th>
                                <th>Job Contractor</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)  
                            <tr>
                                <td data-label="Order No">
                                    {{$order['order_number'] ?? ''}}
                                </td> 
                                <td data-label="Name">
                                    {{$order['name'] ?? ''}}
                                </td>
                                <td data-label="projectTitle">
                                    {{$order['project_title'] ?? ''}}
                                </td>
                                <td data-label="Date">
                                    {{ \Carbon\Carbon::parse($order['created_at'])->format('M d, Y') }}
                                </td>
                                <td data-label="Status" class="status-{{$order['status']}}">
                                    {{ ucfirst($order['status']) }}
                                </td>
                                <td data-label="Items">
                                    {{ count($order['items']) ?? 0 }}
                                </td>
                                <td data-label="Total">
                                    {{ number_format($order['total'], 2) }}
                                </td>
                                <td data-label="Payment">
                                    {{ strtoupper($order['payment_method']) }}
                                </td>
                                <td data-label="Actions">
                                    <div class="action-btns">
                                        <a href="{{ route('order.details', $order['id']) }}" class="btn-view">
                                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                                                <path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/>
                                            </svg>
                                            View
                                        </a> 
                                    </div>
                                </td>
                            </tr> 
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

@push('scripts')
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS -->
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<!-- SweetAlert for confirmation dialog -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable with responsive feature
        var table = $('#ordersTable').DataTable({
            responsive: true,
            paging: true,
            searching: true,
            info: false,
            lengthChange: false,
            dom: '<"top"f>rt<"bottom"p>',
            language: {
                search: "",
                searchPlaceholder: "Search orders..."
            },
            initComplete: function() {
                // Move search input to match your design
                $('.dataTables_filter').hide();
            },
            columnDefs: [
                { responsivePriority: 1, targets: 0 }, // Order No
                { responsivePriority: 2, targets: 6 }, // Actions
                { responsivePriority: 3, targets: 2 }, // Status
                { responsivePriority: 4, targets: 4 }  // Total
            ]
        });

        // Custom search input functionality
        $('#searchInput').keyup(function(){
            table.search($(this).val()).draw();
        });

        // Tab functionality
        $('.tab-button').click(function() {
            $('.tab-button').removeClass('active').addClass('inactive');
            $(this).removeClass('inactive').addClass('active');
            // Add your tab switching logic here
        });
    });
</script>
@endpush