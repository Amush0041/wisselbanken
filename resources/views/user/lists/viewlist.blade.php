@extends('user.layouts.app')

@section('seo')
<title>My Lists - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection 

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page user-lists">
    
    <div class="mb-3">
        <h4 class="page-title mb-1">My Lists</h4>
        <p class="page-description mb-0">Name and save your part lists, generate quotes to lock pricing.</p>
    </div>
    <div class="card">
        <div class="card-header py-3">
            <div class="section-header">
                <h5 class="mb-0">Lists &amp; Quotes</h5>
                <div class="search-tabs">
                    <input type="text" placeholder="Search" class="search-box" id="searchInput">
                </div>
            </div>
        </div>
        <div class="card-body">
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
                                    @canDo('project_management', 'O')
                                    <button class="btn-remove" onclick="confirmDelete(this)" data-list-id="{{ $list->id }}">
                                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                                            <path d="M135.2 17.7L128 32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320l-7.2-14.3C307.4 6.8 296.3 0 284.2 0H163.8c-12.1 0-23.2 6.8-28.6 17.7zM416 128H32L53.2 467c1.6 25.3 22.6 45 47.9 45H346.9c25.3 0 46.3-19.7 47.9-45L416 128z"/>
                                        </svg>
                                        Remove
                                    </button>
                                    @endCanDo
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        var table = $('#listsTable').DataTable({
            paging: true,
            searching: true,
            info: false,
            lengthChange: false,
            dom: '<"top"f>rt<"bottom"p>',
            language: {
                search: "",
                searchPlaceholder: "Search lists..."
            },
            initComplete: function() {
                $('.dataTables_filter').hide();
            }
        });

        $('#searchInput').keyup(function() {
            table.search($(this).val()).draw();
        });
    });

    function confirmDelete(listIdOrEl) {
        const listId = (typeof listIdOrEl === 'object' && listIdOrEl)
            ? listIdOrEl.getAttribute('data-list-id')
            : listIdOrEl;
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
