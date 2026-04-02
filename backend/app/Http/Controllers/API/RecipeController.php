<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipeController extends Controller
{
    public function index(int $productId): AnonymousResourceCollection|JsonResponse
    {
        $product = Product::find($productId);

        if ($product === null) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $recipes = Recipe::where('menu_product_id', $productId)
            ->with('rawMaterial')
            ->get();

        return RecipeResource::collection($recipes);
    }

    public function store(Request $request, int $productId): JsonResponse
    {
        $product = Product::find($productId);

        if ($product === null) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $validated = $request->validate([
            'raw_material_id'   => ['required', 'exists:products,id'],
            'quantity_required' => ['required', 'numeric', 'min:0.0001'],
            'unit'              => ['required', 'string'],
        ]);

        $recipe = Recipe::create([
            'menu_product_id'   => $productId,
            'raw_material_id'   => $validated['raw_material_id'],
            'quantity_required' => $validated['quantity_required'],
            'unit'              => $validated['unit'],
        ]);

        return response()->json(new RecipeResource($recipe->load('rawMaterial')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $recipe = Recipe::find($id);

        if ($recipe === null) {
            return response()->json(['message' => 'Recipe not found.'], 404);
        }

        $validated = $request->validate([
            'raw_material_id'   => ['sometimes', 'exists:products,id'],
            'quantity_required' => ['sometimes', 'numeric', 'min:0.0001'],
            'unit'              => ['sometimes', 'string'],
        ]);

        $recipe->update($validated);

        return response()->json(new RecipeResource($recipe->load('rawMaterial')));
    }

    public function destroy(int $id): JsonResponse
    {
        $recipe = Recipe::find($id);

        if ($recipe === null) {
            return response()->json(['message' => 'Recipe not found.'], 404);
        }

        $recipe->delete();

        return response()->json(null, 204);
    }
}
