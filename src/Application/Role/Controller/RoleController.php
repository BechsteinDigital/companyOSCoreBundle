<?php

namespace CompanyOS\Bundle\CoreBundle\Application\Role\Controller;

use CompanyOS\Bundle\CoreBundle\Application\Role\Command\CreateRoleCommand;
use CompanyOS\Bundle\CoreBundle\Application\Role\Command\UpdateRoleCommand;
use CompanyOS\Bundle\CoreBundle\Application\Role\Command\DeleteRoleCommand;
use CompanyOS\Bundle\CoreBundle\Application\Role\Command\AssignRoleToUserCommand;
use CompanyOS\Bundle\CoreBundle\Application\Role\Command\RemoveRoleFromUserCommand;
use CompanyOS\Bundle\CoreBundle\Application\Role\Query\GetRoleQuery;
use CompanyOS\Bundle\CoreBundle\Application\Role\Query\GetAllRolesQuery;
use CompanyOS\Bundle\CoreBundle\Application\Role\Query\GetUserRolesQuery;
use CompanyOS\Bundle\CoreBundle\Application\Role\DTO\CreateRoleRequest;
use CompanyOS\Bundle\CoreBundle\Application\Role\DTO\UpdateRoleRequest;
use CompanyOS\Bundle\CoreBundle\Application\Role\DTO\RoleResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Nelmio\ApiDocBundle\Attribute\Model;

#[OA\Tag(
    name: 'Roles',
    description: 'Role management operations'
)]
class RoleController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[OA\Get(
        path: '/api/roles',
        summary: 'Get all roles',
        description: 'Retrieve a list of all roles in the system',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'include_system',
                description: 'Include system-defined roles',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', example: true)
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Search roles by name or display name',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'admin')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of roles retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RoleResponse::class))
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('', methods: ['GET'], name: 'api_roles_list')]
    public function index(Request $request): JsonResponse
    {
        $includeSystem = $request->query->getBoolean('include_system', true);
        $search = $request->query->get('search');
        
        $query = new GetAllRolesQuery($includeSystem, $search);
        $envelope = $this->queryBus->dispatch($query);
        $handled = $envelope->last(HandledStamp::class);
        $roles = $handled ? $handled->getResult() : [];

        return $this->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    #[OA\Get(
        path: '/api/roles/{id}',
        summary: 'Get role by ID',
        description: 'Retrieve a specific role by its unique identifier',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: new Model(type: RoleResponse::class))
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Role not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Role not found')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('/{id}', methods: ['GET'], name: 'api_roles_show')]
    public function show(string $id): JsonResponse
    {
        $query = new GetRoleQuery($id);
        $envelope = $this->queryBus->dispatch($query);
        $handled = $envelope->last(HandledStamp::class);
        $role = $handled ? $handled->getResult() : null;

        if ($role === null) {
            return $this->json([
                'success' => false,
                'message' => 'Role not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $role
        ]);
    }

    #[OA\Post(
        path: '/api/roles',
        summary: 'Create a new role',
        description: 'Create a new role in the system',
        tags: ['Roles'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateRoleRequest::class))
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Role created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Role created successfully'),
                        new OA\Property(property: 'data', ref: new Model(type: RoleResponse::class))
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - validation failed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - role with this name already exists',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Role with this name already exists')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('', methods: ['POST'], name: 'api_roles_create')]
    public function create(Request $request): JsonResponse
    {
        try {
            /** @var CreateRoleRequest $dto */
            $dto = $this->serializer->deserialize($request->getContent(), CreateRoleRequest::class, 'json');

            $command = new CreateRoleCommand(
                $dto->name,
                $dto->displayName,
                $dto->description,
                $dto->permissions
            );

            $role = $this->commandBus->dispatch($command);

            return $this->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Put(
        path: '/api/roles/{id}',
        summary: 'Update a role',
        description: 'Update an existing role in the system',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateRoleRequest::class))
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Role updated successfully'),
                        new OA\Property(property: 'data', ref: new Model(type: RoleResponse::class))
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - validation failed',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Role not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Role not found')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('/{id}', methods: ['PUT'], name: 'api_roles_update')]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            /** @var UpdateRoleRequest $dto */
            $dto = $this->serializer->deserialize($request->getContent(), UpdateRoleRequest::class, 'json');

            $command = new UpdateRoleCommand(
                $id,
                $dto->name,
                $dto->displayName,
                $dto->description,
                $dto->permissions
            );

            $role = $this->commandBus->dispatch($command);

            return $this->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $role
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Delete(
        path: '/api/roles/{id}',
        summary: 'Delete a role',
        description: 'Delete a role from the system (cannot delete system roles)',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Role deleted successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Role not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Role not found')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - cannot delete system role',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Cannot delete system role')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('/{id}', methods: ['DELETE'], name: 'api_roles_delete')]
    public function delete(string $id): JsonResponse
    {
        try {
            $command = new DeleteRoleCommand($id);
            $this->commandBus->dispatch($command);

            return $this->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Get(
        path: '/api/roles/user/{userId}',
        summary: 'Get user roles',
        description: 'Retrieve all roles assigned to a specific user',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                description: 'User\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User roles retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RoleResponse::class))
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'User not found')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('/user/{userId}', methods: ['GET'], name: 'api_roles_list_user_roles')]
    public function getUserRoles(string $userId): JsonResponse
    {
        $query = new GetUserRolesQuery($userId);
        $envelope = $this->queryBus->dispatch($query);
        $handled = $envelope->last(HandledStamp::class);
        $roles = $handled ? $handled->getResult() : [];

        return $this->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    #[OA\Post(
        path: '/api/roles/{roleId}/assign/{userId}',
        summary: 'Assign role to user',
        description: 'Assign a role to a specific user',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'roleId',
                description: 'Role\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            ),
            new OA\Parameter(
                name: 'userId',
                description: 'User\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role assigned successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Role assigned successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Role or user not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Role or user not found')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - role already assigned to user',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Role already assigned to user')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('/{roleId}/assign/{userId}', methods: ['POST'], name: 'api_roles_assign_user')]
    public function assignRole(string $roleId, string $userId): JsonResponse
    {
        try {
            $command = new AssignRoleToUserCommand($roleId, $userId);
            $this->commandBus->dispatch($command);

            return $this->json([
                'success' => true,
                'message' => 'Role assigned successfully'
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Delete(
        path: '/api/roles/{roleId}/remove/{userId}',
        summary: 'Remove role from user',
        description: 'Remove a role from a specific user',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'roleId',
                description: 'Role\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            ),
            new OA\Parameter(
                name: 'userId',
                description: 'User\'s unique identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role removed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Role removed successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Role or user not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Role or user not found')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error')
                    ]
                )
            )
        ]
    )]
    #[Route('/{roleId}/remove/{userId}', methods: ['DELETE'], name: 'api_roles_remove_user')]
    public function removeRole(string $roleId, string $userId): JsonResponse
    {
        try {
            $command = new RemoveRoleFromUserCommand($userId, $roleId);
            $this->commandBus->dispatch($command);

            return $this->json([
                'success' => true,
                'message' => 'Role removed successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 