@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Protected Page</div>

                <div class="card-body">
                    <h4>Welcome!</h4>
                    
                    <p>This page is protected by OAuth authentication.</p>
                    
                    @if(isset($userData))
                        <h5>Your User Information:</h5>
                        <pre>{{ json_encode($userData, JSON_PRETTY_PRINT) }}</pre>
                    @else
                        <p>User data not available.</p>
                    @endif
                    
                    <a href="{{ route('oauth.logout') }}" class="btn btn-primary">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection