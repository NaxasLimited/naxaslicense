@extends('admin.layout') @section('content')<h1 class="text-3xl font-bold">Dashboard</h1><p>Recent activation requests: {{$requests->count()}}</p>@endsection
