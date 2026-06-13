<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $authors = Author::latest()->paginate(15);

        return Inertia::render('Admin/Authors/Index', [
            'authors' => $authors,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Authors/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            // Add validation for photo upload if you implement it
        ]);

        Author::create($validated);

        return redirect(route('admin.authors.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Author created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Author $author)
    {
        // We will not be using this method for this project.
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Author $author): Response
    {
        return Inertia::render('Admin/Authors/Edit', [
            'author' => $author,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Author $author): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
        ]);

        $author->update($validated);

        return redirect(route('admin.authors.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Author updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Author $author): RedirectResponse
    {
        $author->delete();

        return redirect(route('admin.authors.index'))->with('toast', [
            'type' => 'success',
            'message' => 'Author deleted successfully.',
        ]);
    }
}
