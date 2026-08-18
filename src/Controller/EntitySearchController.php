<?php

declare(strict_types=1);

namespace CoolMS\EntityBundle\Controller;

use CoolMS\Entity\Resolver\EntityResolverChainInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Generic entity-picker search endpoint.
 *
 * `GET /api/entity-search?type=<FQCN>&q=<text>&limit=<n>` dispatches
 * to `EntityResolverChainInterface::search()` and returns a JSON
 * array of `{ id, label, secondary? }` rows. Per-result permission
 * filtering is the matching resolver's responsibility; this
 * controller just routes.
 *
 * Authentication: any authenticated user. The picker is admin-side
 * (template Generate dialog), so requiring authentication keeps
 * the endpoint off the public surface. Entity-type-specific
 * authorization belongs in the resolver layer.
 */
final class EntitySearchController extends AbstractController
{
    public function __construct(
        private readonly EntityResolverChainInterface $resolverChain,
    ) {
    }

    #[Route('/api/entity-search', name: 'coolms_entity_search', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): JsonResponse
    {
        $entityType = (string) $request->query->get('type', '');
        if ('' === $entityType) {
            throw new BadRequestHttpException('Missing required query parameter "type" (entity FQCN).');
        }
        $query = (string) $request->query->get('q', '');
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $results = $this->resolverChain->search($entityType, $query, $limit);

        return new JsonResponse($results);
    }
}
