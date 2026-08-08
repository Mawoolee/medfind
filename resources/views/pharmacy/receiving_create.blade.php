@extends('layouts.app')

@section('content')
<div class="container">
    <h3>Receive Shipment</h3>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="receiving-form" action="{{ route('pharmacy.receiving.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label">Supplier (optional)</label>
            <select name="supplier_id" class="form-select">
                <option value="">-- Select supplier --</option>
                @foreach($suppliers as $sp)
                    <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                @endforeach
            </select>
        </div>

        <h5>Items</h5>
        <div id="items-list">
            <div class="item-row mb-3 row">
                <div class="col-md-4">
                    <input type="text" name="items[0][medicine_name]" class="form-control" placeholder="Medicine name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="items[0][quantity]" class="form-control" placeholder="Qty" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="items[0][price]" class="form-control" placeholder="Price">
                </div>
                <div class="col-md-2">
                    <input type="text" name="items[0][batch_number]" class="form-control" placeholder="Batch #">
                </div>
                <div class="col-md-2">
                    <input type="date" name="items[0][expiry_date]" class="form-control" placeholder="Expiry">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <button type="button" id="add-item" class="btn btn-sm btn-outline-secondary">Add another item</button>
        </div>

        <button class="btn btn-primary">Process Shipment</button>
        <a href="{{ route('pharmacy.inventory') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>

@push('scripts')
<script>
    (function(){
        let idx = 1;
        document.getElementById('add-item').addEventListener('click', function(){
            const list = document.getElementById('items-list');
            const row = document.querySelector('.item-row').cloneNode(true);
            row.querySelectorAll('input').forEach(function(input){
                const name = input.name.replace(/items\[0\]/, `items[${idx}]`);
                input.name = name;
                input.value = '';
            });
            list.appendChild(row);
            idx++;
        });
    })();
</script>
@endpush

@endsection
