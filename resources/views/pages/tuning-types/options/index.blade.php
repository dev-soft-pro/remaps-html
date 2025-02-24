
@extends('layouts/contentLayoutMaster')

@section('title', 'Tuning Type Options')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/animate/animate.min.css')) }}">
  <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/sweetalert2.min.css')) }}">
@endsection
@section('page-style')
  <link rel="stylesheet" href="{{asset(mix('css/base/plugins/extensions/ext-component-sweet-alerts.css'))}}">
@endsection

@section('content')
@php
  $route_prefix = "";
  if ($user->is_semi_admin) {
    $route_prefix = "staff.";
  }
@endphp

<!-- Basic Tables start -->
<div class="row" id="basic-table">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h4 class="card-title">Tuning Type Options</h4>
        <div>
          <a href="{{ route($route_prefix.'tuning-types.index') }}" class="btn btn-icon btn-secondary">
            <i data-feather="arrow-left"></i>
          </a>
          <a href="{{ route($route_prefix.'options.create', ['id' => $typeId]) }}" class="btn btn-icon btn-primary">
            <i data-feather="plus"></i>
          </a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Label</th>
              <th>Credits</th>
              <th>Tooltip</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @if (count($entries) > 0)
              @foreach ($entries as $entry)
                <tr>
                  <td>{{ $entry->label }}</td>
                  <td>{{ $entry->credits }}</td>
                  <td>{{ $entry->tooltip }}</td>
                  <td class="td-actions">
                    <a class="btn btn-icon btn-primary" href="{{ route($route_prefix.'options.edit', ['id' => $typeId, 'option' => $entry->id]) }}" title="Edit">
                      <i data-feather="edit"></i>
                    </a>
                    <a class="btn btn-icon btn-success" href="{{ route($route_prefix.'options.sort.up', ['id' => $typeId, 'option' => $entry->id]) }}" title="Move Up">
                      <i data-feather="arrow-up"></i>
                    </a>
                    <a class="btn btn-icon btn-success" href="{{ route($route_prefix.'options.sort.down', ['id' => $typeId, 'option' => $entry->id]) }}" title="Move Down">
                      <i data-feather="arrow-down"></i>
                    </a>
                    <a class="btn btn-icon btn-danger" onclick="onDelete(this)" title="Delete">
                      <i data-feather="trash-2"></i>
                    </a>
                    <form action="{{ route($route_prefix.'options.destroy', ['id' => $typeId, 'option' => $entry->id]) }}" class="delete-form" method="POST" style="display:none">
                      <input type="hidden" name="_method" value="DELETE">
                      <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    </form>
                  </td>
                </tr>
              @endforeach
            @else
              <tr>
                <td colspan="4">No matching records found</td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
    {{ $entries->links() }}
  </div>
</div>
<!-- Basic Tables end -->
@endsection
@section('vendor-script')
  <script src="{{ asset(mix('vendors/js/extensions/sweetalert2.all.min.js')) }}"></script>
  <script src="{{ asset(mix('vendors/js/extensions/polyfill.min.js')) }}"></script>
@endsection
@section('page-script')
<script>
  async function onDelete(obj) {
    var delete_form = $(obj).closest('.td-actions').children('.delete-form')
    var swal_result = await Swal.fire({
      title: 'Warning!',
      text: 'Are you sure to delete?',
      icon: 'warning',
      customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-outline-danger ms-1'
      },
      showCancelButton: true,
      confirmButtonText: 'OK',
      cancelButtonText: 'Cancel',
      buttonsStyling: false
    });
    if (swal_result.isConfirmed) {
      delete_form.submit();
    }
  }
</script>
@endsection
