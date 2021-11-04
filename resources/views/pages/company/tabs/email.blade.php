<div class="tab-pane @if($tab == 'email') active @endif" id="email-fill" role="tabpanel" aria-labelledby="email-tab-fill">
  {{ $entry->id
    ? Form::model($entry, array('route' => array('companies.update', $entry->id), 'method' => 'PUT'))
    : Form::model($entry, array('route' => array('companies.store', $entry->id), 'method' => 'POST')) }}
    @csrf
    <input type="hidden" name="tab" value="email" />
    <div class="row mb-1">
      <div class="col-md-6 col-xl-4">
        <label class="form-label" for="main_email_address">Main email address <span class="text-danger">*</span></label>
        <input
          type="text"
          id="main_email_address"
          class="form-control"
          placeholder="xxx@xxx.com"
          name="main_email_address"
          value="{{ $entry->main_email_address }}" />
      </div>
    </div>

    <div class="row mb-1">
      <div class="col-md-6 col-xl-4">
        <label class="form-label" for="support_email_address">Support email address<small class="text-muted">(optional)</small></label>
        <input
          type="text"
          id="support_email_address"
          class="form-control"
          placeholder="xxx@xxx.com"
          name="support_email_address"
          value="{{ $entry->support_email_address }}" />
      </div>
    </div>

    <div class="row mb-1">
      <div class="col-md-6 col-xl-4">
        <label class="form-label" for="billing_email_address">Billing email address<small class="text-muted">(optional)</small></label>
        <input
          type="text"
          id="billing_email_address"
          class="form-control"
          placeholder="xxx@xxx.com"
          name="billing_email_address"
          value="{{ $entry->billing_email_address }}" />
      </div>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary me-1">Submit</button>
    </div>
  {{ Form::close() }}
</div>
