<?php

/**
 * ApiBaseController — shared foundation for all JSON / token-authenticated endpoints.
 *
 * Extends the main Controller and adds:
 *  - beforeRoute(): Bearer-token authentication (no session, no CSRF)
 *  - jsonOut() / jsonError(): uniform JSON response helpers
 *  - getBody(): JSON request body parser
 *
 * Usage:
 *   class ApiController  extends ApiBaseController { … }
 *   class McpController  extends ApiBaseController { … }
 */
abstract class ApiBaseController extends Controller
{
    /**
     * Authenticate via Bearer token, then expose the resolved user through
     * currentUser() by injecting the user_id into the session slot.
     *
     * Overriding classes that need additional setup should call parent::beforeRoute()
     * first (unless they handle authentication themselves, e.g. for CORS preflight).
     */
    public function beforeRoute(Base $f3): void
    {
        // Skip CSRF check performed by parent — API routes use Bearer tokens.
        // (Do NOT call parent::beforeRoute() here.)

        $userId = $this->authenticateApiRequest();
        if (!$userId) {
            $this->jsonError('Token manquant ou invalide.', 401, 'UNAUTHORIZED');
        }

        // Inject user context so currentUser() works normally downstream.
        $_SESSION['user_id'] = $userId;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Response helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Send a successful JSON response and terminate.
     *
     * @param array $data   Response payload
     * @param int   $status HTTP status code (default 200)
     */
    protected function jsonOut(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-API-Version: 1');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send an error JSON response and terminate.
     *
     * @param string $message Human-readable error description
     * @param int    $status  HTTP status code
     * @param string $code    Machine-readable error code (e.g. 'NOT_FOUND')
     */
    protected function jsonError(string $message, int $status, string $code): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message, 'code' => $code], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Parse the JSON request body. Returns an empty array if body is absent or invalid.
     */
    protected function getBody(): array
    {
        $raw = $this->f3->get('BODY') ?: file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pagination helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Parse ?offset= and ?limit= from the query string.
     * Returns [offset, limit] with sane defaults (0, 50) and hard cap (200).
     */
    protected function getPaginationParams(int $defaultLimit = 50, int $maxLimit = 200): array
    {
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $limit  = min($maxLimit, max(1, (int) ($_GET['limit'] ?? $defaultLimit)));
        return [$offset, $limit];
    }

    /**
     * Wrap a list result with pagination metadata.
     *
     * @param array $items   The page of items
     * @param int   $total   Total count (from COUNT(*) query)
     * @param int   $offset
     * @param int   $limit
     */
    protected function paginatedOut(array $items, int $total, int $offset, int $limit): never
    {
        $this->jsonOut([
            'data'   => $items,
            'meta'   => [
                'total'  => $total,
                'offset' => $offset,
                'limit'  => $limit,
            ],
        ]);
    }
}
