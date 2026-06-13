<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private SearchService $search) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('search.search'), 403);

        $query   = (string) $request->input('q', '');
        $types   = (array) $request->input('types', []);

        $results = [];
        $counts  = [];

        if ($query !== '') {
            $results = $this->search->search($request->user(), $query, $types);
            foreach ($results as $type => $items) {
                $counts[$type] = count($items);
            }
        }

        return Inertia::render('Search/Index', [
            'query'   => $query,
            'types'   => $types,
            'results' => $results,
            'counts'  => $counts,
        ]);
    }

    public function quick(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('search.search'), 403);

        $query = (string) $request->input('q', '');

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $results = $this->search->suggest($request->user(), $query);

        return response()->json(['results' => $results]);
    }
}
