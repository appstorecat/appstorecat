<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Folder\StoreFolderRequest;
use App\Http\Requests\Api\Folder\UpdateFolderRequest;
use App\Http\Resources\Api\Folder\FolderResource;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FolderController extends BaseController
{
    #[OA\Get(
        path: '/folders',
        summary: 'List folders for the authenticated user',
        tags: ['Folders'],
        operationId: 'listFolders',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Folder list with app counts',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FolderResource')),
            ),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        // Count user_apps.folder_id matches directly. withCount on a
        // belongsToMany with wherePivot('folder_id', $this->id) loses the
        // folder id inside the subquery scope and always returns 0.
        $folders = $request->user()
            ->folders()
            ->selectRaw('folders.*, (SELECT COUNT(*) FROM user_apps WHERE user_apps.folder_id = folders.id) AS apps_count')
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get();

        return FolderResource::collection($folders);
    }

    #[OA\Post(
        path: '/folders',
        summary: 'Create a folder',
        tags: ['Folders'],
        operationId: 'storeFolder',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreFolderRequest'),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Folder created', content: new OA\JsonContent(ref: '#/components/schemas/FolderResource')),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreFolderRequest $request): JsonResponse
    {
        $folder = $request->user()->folders()->create([
            'name' => $request->validated('name'),
            'color' => $request->validated('color'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
        ]);

        $folder->apps_count = 0;

        return FolderResource::make($folder)
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Patch(
        path: '/folders/{folder}',
        summary: 'Update a folder',
        tags: ['Folders'],
        operationId: 'updateFolder',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'folder', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateFolderRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Folder updated', content: new OA\JsonContent(ref: '#/components/schemas/FolderResource')),
            new OA\Response(response: 404, description: 'Folder not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(UpdateFolderRequest $request, Folder $folder): FolderResource
    {
        $this->authorizeFolder($folder, $request);

        $folder->fill($request->validated())->save();

        $folder->apps_count = $folder->appsCount();

        return FolderResource::make($folder);
    }

    #[OA\Delete(
        path: '/folders/{folder}',
        summary: 'Delete a folder (tracked apps stay, folder_id becomes NULL)',
        tags: ['Folders'],
        operationId: 'destroyFolder',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'folder', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Folder deleted'),
            new OA\Response(response: 404, description: 'Folder not found'),
        ],
    )]
    public function destroy(Request $request, Folder $folder): Response
    {
        $this->authorizeFolder($folder, $request);

        $folder->delete();

        return response()->noContent();
    }

    /**
     * Ensure the folder belongs to the authenticated user. To avoid leaking
     * the existence of folders owned by other users, return a 404 (not 403).
     */
    private function authorizeFolder(Folder $folder, Request $request): void
    {
        if ($folder->user_id !== $request->user()->id) {
            throw new NotFoundHttpException('Folder not found.');
        }
    }
}
