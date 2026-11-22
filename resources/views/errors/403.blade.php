@extends('layouts.error')

@section('code', '403')
@section('title', 'Access Denied')
@section('message', $exception->getMessage() ?: 'You do not have permission to open this page.')
