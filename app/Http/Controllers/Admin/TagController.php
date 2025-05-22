<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\TagService;
use App\DataTransferObjects\Tag\TagData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Exception;

class TagController extends Controller
{
    protected TagService $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
        // Add middleware for authorization if needed, e.g.:
        // $this->middleware('can:manage_tags')->except(['index', 'show']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Consider adding search/filtering capabilities
        $tags = $this->tagService->getPaginatedTags();
        return Inertia::render('Admin/Tag/Index', [
            'pageTitle' => 'Tags',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Tags']
            ],
            'tags' => $tags,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Tag/Create', [
            'pageTitle' => 'Create New Tag',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Tags', 'href' => route('admin.tags.index')],
                ['text' => 'Create New Tag']
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $tagData = TagData::from($request->all());
            $this->tagService->createTag($tagData);
            return redirect()->route('admin.tags.index')->with('success', 'Tag created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'An unexpected error occurred.')->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Tag $tag)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag)
    {
        return Inertia::render('Admin/Tag/Edit', [
            'pageTitle' => 'Edit Tag',
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Tags', 'href' => route('admin.tags.index')],
                ['text' => 'Edit Tag']
            ],
            'tag' => TagData::from($tag->toArray()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag)
    {
        try {
            $tagData = TagData::from($request->all());
            $this->tagService->updateTag($tag->id, $tagData);
            return redirect()->route('admin.tags.index')->with('success', 'Tag updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'An unexpected error occurred.')->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        try {
            $this->tagService->deleteTag($tag->id);
            return redirect()->route('admin.tags.index')->with('success', 'Tag deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error deleting tag: ' . $e->getMessage());
        }
    }
}
