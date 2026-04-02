<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Product;
use App\Models\Table;
use App\Services\QRCodeService;
use App\Services\TableService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function __construct(
        private TableService $tableService,
        private QRCodeService $qrCodeService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => TableResource::collection(Table::all()),
        ]);
    }

    public function store(CreateTableRequest $request): JsonResponse
    {
        try {
            $table = $this->tableService->create($request->validated());

            return response()->json(['data' => new TableResource($table)], 201);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Table number already exists.'], 409);
        }
    }

    public function show(int $id): JsonResponse
    {
        $table = Table::find($id);

        if ($table === null) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        return response()->json(['data' => new TableResource($table)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $table = Table::find($id);

        if ($table === null) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        $validated = $request->validate([
            'table_number' => ['sometimes', 'string', 'unique:tables,table_number,' . $id],
            'name'         => ['sometimes', 'string'],
            'capacity'     => ['nullable', 'integer', 'min:1'],
            'status'       => ['nullable', 'in:available,occupied,reserved'],
        ]);

        $table->update($validated);

        return response()->json(['data' => new TableResource($table->fresh())]);
    }

    public function qr(int $id): mixed
    {
        $table = Table::find($id);

        if ($table === null) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        $svgContent = $this->qrCodeService->generateQRImage($table);

        return response($svgContent, 200)->header('Content-Type', 'image/svg+xml');
    }

    public function menu(string $identifier): JsonResponse
    {
        $table = Table::where('table_number', $identifier)->first();

        if ($table === null) {
            return response()->json(['message' => 'Table not found.'], 404);
        }

        $products = Product::where('is_available', true)
            ->with(['menuRecipes.rawMaterial', 'category'])
            ->get();

        return response()->json([
            'data' => [
                'table' => [
                    'id'           => $table->id,
                    'table_number' => $table->table_number,
                    'name'         => $table->name,
                    'status'       => $table->status,
                ],
                'menu' => $products->map(fn (Product $p) => [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'description' => $p->description,
                    'sell_price'  => $p->sell_price,
                    'unit'        => $p->unit,
                    'image_path'  => $p->image_path,
                    'category'    => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
                    'recipes'     => $p->menuRecipes->map(fn ($r) => [
                        'id'               => $r->id,
                        'raw_material_id'  => $r->raw_material_id,
                        'raw_material'     => $r->rawMaterial ? [
                            'id'   => $r->rawMaterial->id,
                            'name' => $r->rawMaterial->name,
                            'unit' => $r->rawMaterial->unit,
                        ] : null,
                        'quantity_required' => $r->quantity_required,
                        'unit'              => $r->unit,
                    ]),
                ]),
            ],
        ]);
    }
}
