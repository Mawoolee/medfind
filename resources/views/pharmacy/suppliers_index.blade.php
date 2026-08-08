@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Suppliers</h3>
        <a href="{{ route('pharmacy.suppliers.create') }}" class="btn btn-primary">Add Supplier</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($suppliers as $s)
            <tr>
                <td>{{ $s->name }}</td>
                <td>{{ $s->contact_person }}</td>
                <td>{{ $s->phone }}</td>
                <td>{{ $s->email }}</td>
                <td>{{ $s->address }}</td>
                <td class="text-end">
                    <a href="{{ route('pharmacy.suppliers.edit', $s->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                    <form action="{{ route('pharmacy.suppliers.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete supplier?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $suppliers->links() }}
</div>
@endsection
