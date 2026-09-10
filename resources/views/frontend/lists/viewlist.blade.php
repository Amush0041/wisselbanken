@push('css')
<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
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
    #listsTable {
        width: 100% !important;
    }
    #listsTable thead th {
        text-align: left;
        padding: 12px;
        font-weight: bold;
        border-bottom: 1px solid #eee;
    }
    #listsTable tbody td {
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
    .btn-remove {
        background-color: #dc3545;
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
    .btn-remove:hover {
        background-color: #c82333;
    }
    .btn-icon {
        width: 14px;
        height: 14px;
    }
</style>
@endpush

<main class="main">

    <div id="toast-container"></div>
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{url('/')}}" rel="nofollow">Home</a>
                <span></span> Products
            </div>
        </div>
    </div>
    <section class="mt-4 mb-4">
        <div class="container-fluid px-4">

            <!-- Main Heading -->
            <h1 class="page-title">My Lists</h1>
            
            <!-- Description -->
            <p class="page-description">
                Name and save your part lists, generate quotes to lock pricing.
                  <!-- Action Buttons -->
           
            </p>
            
           
            <!-- Lists Section -->
            <div>
                <div class="section-header">
                    <h2>Lists & Quotes</h2>
                    <div class="search-tabs">
                        <input type="text" placeholder="Search" class="search-box" id="searchInput"> 
                    </div>
                </div>
                
                <!-- DataTable -->

                <div class="table-responsive">
                    <table id="listsTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>List name</th>
                                <th>Items count</th> 
                                <th>Modified at</th>
                                <th>Created at</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lists as $list)  
                            <tr>
                                <td><a href="{{url('list-view/'.$list->id.'/'.$list->name)}}"><strong>{{$list->name ?? ''}}</strong></a></td>
                                <td>{{$list->items->count() ?? ''}}</td>
                                <td>{{$list->update_at ?? 'N/A'}}</td>
                                <td>{{$list->created_at}}</td> 
                                <td>
                                    <div class="action-btns">
                                        <a href="{{url('list-view/'.$list->id.'/'.$list->name)}}" class="btn-view">
                                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor">
                                                <path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/>
                                            </svg>
                                            View
                                        </a>
                                        <button class="btn-remove" onclick="confirmDelete({{ $list->id }})">
                                            <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                                                <path d="M135.2 17.7L128 32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320l-7.2-14.3C307.4 6.8 296.3 0 284.2 0H163.8c-12.1 0-23.2 6.8-28.6 17.7zM416 128H32L53.2 467c1.6 25.3 22.6 45 47.9 45H346.9c25.3 0 46.3-19.7 47.9-45L416 128z"/>
                                            </svg>
                                            Remove
                                        </button>
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
<!-- SweetAlert for confirmation dialog -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#listsTable').DataTable({
            "paging": true,
            "searching": true,
            "info": false,
            "lengthChange": false,
            "dom": '<"top"f>rt<"bottom"p>',
            "language": {
                "search": "",
                "searchPlaceholder": "Search lists..."
            },
            "initComplete": function() {
                // Move search input to match your design
                $('.dataTables_filter').hide();
            }
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

    function confirmDelete(listId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Send AJAX request to delete the list
                $.ajax({
                    url: '/remove-list/' + listId,
                    type: 'Post',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Deleted!',
                                'Your list has been deleted.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Error!',
                                'Something went wrong.',
                                'error'
                            );
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Error!',
                            'Something went wrong.',
                            'error'
                        );
                    }
                });
            }
        });
    }
</script>
@endpush