<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GenreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Genres/Index', [
            'genres' => Genre::latest()->paginate(15),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Genres/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:genres',
        ]);

        Genre::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect(route('admin.genres.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Genre created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Genre $genre)
    {
        // Not used for this project
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Genre $genre): Response
    {
        return Inertia::render('Admin/Genres/Edit', [
            'genre' => $genre,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Genre $genre): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:genres,name,'.$genre->id,
        ]);

        $genre->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect(route('admin.genres.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Genre updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        $genre->delete();

        return redirect(route('admin.genres.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Genre deleted successfully.',
        ]);
    }
}
