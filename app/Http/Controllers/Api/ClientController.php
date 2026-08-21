<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organization = $request->user()->organization;
        abort_unless($organization, 403, 'User is not assigned to an organization.');

        $allowedSorts = ['name', 'company_name', 'email', 'created_at'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $search = trim($request->string('search')->toString());

        $clients = $organization->clients()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('company_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    public function store(ClientRequest $request): ClientResource
    {
        $client = $request->user()->organization->clients()->create($request->validated());

        return new ClientResource($client);
    }

    public function show(Request $request, int $client): ClientResource
    {
        return new ClientResource($this->findClient($request, $client));
    }

    public function update(ClientRequest $request, int $client): ClientResource
    {
        $clientModel = $this->findClient($request, $client);
        $clientModel->update($request->validated());

        return new ClientResource($clientModel->refresh());
    }

    public function destroy(Request $request, int $client): Response
    {
        $this->findClient($request, $client)->delete();

        return response()->noContent();
    }

    private function findClient(Request $request, int $client): Client
    {
        $organization = $request->user()->organization;
        abort_unless($organization, 403, 'User is not assigned to an organization.');

        return $organization->clients()->findOrFail($client);
    }
}
