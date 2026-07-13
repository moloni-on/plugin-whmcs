# Moloni ON WHMCS - Architecture & System Design

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    WHMCS Admin Interface                     │
│  (Login → Company → Orders → Documents → Settings → Logs)   │
└────────────────────┬────────────────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        │                         │
┌───────▼──────────┐    ┌────────▼────────┐
│  moloni_on.php   │    │   hooks.php      │
│  → Admin\        │    │  (WHMCS Hooks)   │
│    Dispatcher    │    │  InvoicePaid     │
└────────┬─────────┘    └──────────────────┘
         │
    ┌────┴───────────────────────────────────┐
    │                                        │
┌──▼──────────────────┐         ┌───────────▼──────────┐
│   Services Layer    │         │  Models & Database   │
│  - DocumentService  │         │  - Order / Document  │
│  - OrderService     │         │  - Config / Auth / Log│
│  - AuthService      │         │  - Whmcs (native rd) │
│  - Settings/Log     │         │                      │
└──┬─────────────────┘          └────────┬─────────────┘
   │                                     │
   │            ┌────────────────────────┘
   │            │
┌──▼────────────▼──────────────────┐
│        API Client Layer           │
│  - MoloniClient (GraphQL)         │
│  - ApiClient (HTTP base)          │
│  - Query/Mutation Builders        │
└──┬───────────────────────────────┘
   │
┌──▼──────────────────────────────────┐
│     Moloni ON GraphQL API           │
│  https://api.molonion.pt/v1    │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│        WHMCS Database                │
│  - tblorders (WHMCS native)          │
│  - tblclients (WHMCS native)         │
│  - mod_moloni_on_config              │
│  - mod_moloni_on_orders              │
│  - mod_moloni_on_logs                │
│  - mod_moloni_on_documents           │
└──────────────────────────────────────┘
```

---

## Layer-by-Layer Architecture

### 1. Presentation Layer (Templates)

**Files:** `/templates/*.php`

**Responsibilities:**
- Render HTML UI pages
- Display data from services
- Handle form submissions
- Show error/success messages

**Pages:**
- `login.php` - API key authentication
- `company.php` - Company selection
- `orders.php` - Orders pending sync (only orders whose WHMCS invoice is `Paid`)
- `documents.php` - Created documents list
- `discarded.php` - Orders marked "do not sync"
- `config.php` - Settings management
- `tools.php` - Utility functions (placeholder)
- `logs.php` - Activity logs
- `Blocks/` - Reusable UI components (header, nav, footer, `pagination.php`)
- `Modals/` - Bootstrap modals for confirmations, details

The list pages (orders, documents, discarded, logs) are paginated server-side at
`Paginator::PER_PAGE` (15) rows per page. Each page owns a single list and reads its
1-based page from the `page` query param, rendering controls via the shared
`Blocks/pagination.php` partial, exposed to templates as the
`$paginate($paginator, $baseParams, $pageParam)` helper. `$baseParams` preserves the
active tab and any filters (e.g. the log level) across page links.

**Data Flow:**
```
User Action (Submit Form)
    ↓
moloni_on.php (Router)
    ↓
Service Layer (Process)
    ↓
Template (Re-render with results)
    ↓
Browser Display
```

---

### 2. Routing Layer

**File:** `/moloni_on.php`

**Responsibilities:**
- Main WHMCS addon entry point
- Route requests to appropriate handlers
- Handle admin dispatcher
- Register hooks

**Key Methods:**
```php
function moloni_on_output($vars) {
    // Main router
    // Determines which page to show
    // Calls appropriate service/template
}

function moloni_on_config($vars) {
    // Module configuration for WHMCS admin
}

function moloni_on_activate() {
    // Initialize module (create tables, install)
}

function moloni_on_deactivate() {
    // Clean up (optional: drop tables)
}
```

---

### 3. Service Layer (Business Logic)

**Directory:** `/src/MoloniOn/Services/`

**Responsibilities:**
- Implement business logic
- Orchestrate API calls and database operations
- Handle transactions and state management
- Validate inputs and outputs

#### Key Services:

**DocumentService**
```php
class DocumentService {
    public function createDocumentFromOrder($orderId, $documentType) {
        // 1. Fetch WHMCS order
        // 2. Create customer in Moloni ON (if new)
        // 3. Create document in Moloni ON
        // 4. Update mod_moloni_on_orders table
        // 5. Log action
    }
    
    public function bulkCreateDocuments($orderIds) {
        // Create multiple documents
        // Continue even if one fails
        // Log results
    }
    
    public function discardOrder($orderId) {
        // Mark order as 'do not sync'
    }
    
    public function revertDiscard($orderId) {
        // Move discarded order back to pending
    }
    
    public function getDocumentDetails($docId) {
        // Fetch document info from Moloni ON
    }
    
    public function downloadPdf($docId) {
        // Resolve a media token, then fetch the PDF via MoloniClient::downloadMedia()
    }
}
```

**OrderService**
```php
class OrderService {
    public function getPendingOrders() {
        // Return WHMCS orders with a Paid invoice, not yet synced/discarded
    }
    
    public function getCreatedDocuments() {
        // Return successfully synced documents
    }
    
    public function getDiscardedOrders() {
        // Return orders marked as 'do not sync'
    }
}
```

**LogService**
```php
class LogService {
    public function log($level, $message, $context = []) {
        // Write to mod_moloni_on_logs table
    }
    
    public function info($msg, $ctx = []) {}
    public function warning($msg, $ctx = []) {}
    public function error($msg, $ctx = []) {}
    
    public function getLogs($filters = []) {
        // Fetch logs with date/level filters
    }
    
    public function clearLogs() {
        // Delete log entries older than one week (recent activity is kept)
    }
}
```

**SettingsService**
```php
class SettingsService {
    public function getSetting($key) {
        // Fetch from mod_moloni_on_config
    }
    
    public function setSetting($key, $value) {
        // Save to mod_moloni_on_config
    }
    
    public function getAll() {
        // Return all settings as array
    }
}
```

---

### 4. API/Client Layer

**Directory:** `/src/MoloniOn/Api/`

**Responsibilities:**
- Handle HTTP requests to Moloni ON
- Build GraphQL queries/mutations
- Parse responses
- Handle authentication and errors

#### Key Classes:

**ApiClient** (native cURL; OAuth2 + GraphQL)
```php
class ApiClient {
    public function __construct(int $timeout = Platform::API_TIMEOUT) {}

    // GraphQL: injects the active companyId + Bearer token from Context.
    public function request(string $operation, string $query, array $variables = []): array {}

    // OAuth2 authorization-code flow.
    public function authorizeUrl(string $clientId, string $redirectUri): string {}
    public function grant(string $clientId, string $clientSecret, string $code): array {}   // code -> tokens
    public function refresh(string $clientId, string $clientSecret, string $refreshToken): ?array {}

    // Binary GET for media (e.g. document PDFs); shares the cURL/SSL setup.
    public function download(string $url): string {}
}
```

**MoloniClient (domain wrapper)**
```php
class MoloniClient {
    public function __construct(ApiClient $apiClient) {}

    public function getCompanies(): array {}
    public function getCompany(int $companyId): array {}
    public function getDocumentSets(): array {}

    public function findCustomerByVat(string $vat): ?array {}
    public function createCustomer(array $data): array {}

    public function createDocument(array $data, string $documentType = 'invoice'): array {}
    public function updateDocumentStatus(int $id, int $status, string $documentType = 'invoice'): array {}
    public function getDocument(int $id): array {}
    public function getDocumentPdfToken(int $id, string $documentType = 'invoice'): array {}

    public function getCountries(): array {}
    public function findProductByReference(string $ref): ?array {}
    public function createProduct(array $data): array {}
    public function findTax(float $rate, string $fiscalZoneCode): ?array {}
    public function createTax(array $data): array {}

    public function downloadMedia(string $url): string {}   // delegates to ApiClient::download()
}
```

> Company selection is handled by `AuthService` (persists the id to `mod_moloni_on_auth`
> and sets `Context::$companyId`), not a `MoloniClient` call.

#### GraphQL Query Builder

**Directory:** `/src/MoloniOn/GraphQL/`

**Pattern:**
```php
namespace MoloniOn\GraphQL\Queries;

class CreateDocument {
    private $query = <<<'GRAPHQL'
        mutation CreateDocument($input: DocumentInput!) {
            documentCreate(input: $input) {
                id
                number
                status
                url
            }
        }
    GRAPHQL;
    
    public function query() {
        return $this->query;
    }
    
    public function variables($data) {
        return [
            'input' => [
                'type' => $data['type'],
                'customer' => $data['customer'],
                'lines' => $data['lines'],
                // ...
            ]
        ];
    }
}
```

**Benefits:**
- Separate query files for readability
- IDE autocomplete (with proper setup)
- Reusable query definitions
- Version control clarity

---

### 5. Model/Database Layer

**Directory:** `/src/MoloniOn/Models/`

**Responsibilities:**
- Represent data entities
- Provide database access
- Validate data integrity

#### Key Models:

**Order Model**
```php
class Order extends AbstractModel {
    protected $table = 'mod_moloni_on_orders';
    
    public function getWhmcsOrder() {
        // Fetch from WHMCS tblorders
    }
    
    public function getCustomer() {
        // Fetch customer from WHMCS tblclients
    }
    
    public function getItems() {
        // Fetch order items from WHMCS
    }
    
    public function markSynced($docId) {
        // Update status to 'synced'
    }
}
```

**Document Model**
```php
class Document extends AbstractModel {
    protected $table = 'mod_moloni_on_documents';
    // Columns: order_id, order_total, invoice_id, invoice_date,
    //          invoice_status, invoice_total, value

    public function fetchFromApi() {
        // Get latest details from Moloni ON
    }
    
    public function getPdf() {
        // Download PDF from Moloni ON
    }
}
```

**Config Model**
```php
class Config extends AbstractModel {
    protected $table = 'mod_moloni_on_config';
    
    // Settings stored as key-value pairs
}
```

---

### 6. Exception Handling

**Directory:** `/src/MoloniOn/Exceptions/`

**Hierarchy:**
```
MoloniException (extends \Exception)
├── ApiException
│   └── ValidationException
├── DocumentException
│   └── DocumentWarning
└── AuthException
```

**Usage:**
```php
try {
    $doc = $this->moloniClient->createDocument($data);
} catch (DocumentException $e) {
    $this->logger->error("Document creation failed: " . $e->getMessage());
} catch (ApiException $e) {
    $this->logger->error("API error: " . $e->getMessage());
}
```

---

## Request Flow Example: Create Document

```
1. User clicks "Create Document" in Orders page
   ↓
2. POST request to moloni_on.php with orderId & documentType
   ↓
3. moloni_on.php Router
   → Validates session/permissions
   → Calls DocumentService->createDocumentFromOrder()
   ↓
4. DocumentService
   → Fetches WHMCS order (via Order Model); refuses orders with no invoice items
   → Checks if customer exists in Moloni ON
   → If not, creates customer via MoloniClient->createCustomer()
   → Resolves the currency exchange (CurrencyResolver) when the client currency
     differs from the company base currency
   → Maps WHMCS order items to Moloni ON format, converting amounts to the base
     currency and stamping currencyExchangeId/currencyExchangeExchange
   → Calls MoloniClient->createDocument()
   → Updates mod_moloni_on_orders table
   → Persists created document to mod_moloni_on_documents table
   → Logs success to mod_moloni_on_logs
   ↓
5. MoloniClient
   → Builds GraphQL mutation (CreateDocument query)
   → Calls ApiClient->request()
   ↓
6. ApiClient
   → Makes HTTPS POST to https://api.molonion.pt/v1
   → Sends API key in Authorization header
   → Returns parsed JSON response
   ↓
7. MoloniClient parses response
   → Extracts document ID, number, status
   → Returns to DocumentService
   ↓
8. DocumentService returns success with docId
   ↓
9. moloni_on.php redirects to Documents page
   ↓
10. User sees document in "Created Documents" list
```

---

## Data Mapping

### WHMCS Order → Moloni ON Document

**Order Header:**
```
WHMCS                          Moloni ON
tblorders.id         →         document.externalId (optional)
tblorders.total      →         document.total
tblorders.date       →         document.date
tblorders.status     →         document.status (may differ)
tblclients.*         →         customer (create/update)
```

**Order Items:**
```
tblorderitems.*      →         document.lines[].productId
tblorderitems.qty    →         document.lines[].quantity
tblorderitems.amount →         document.lines[].unitPrice
```

**Taxes:**
```
WHMCS Tax Rate       →         Moloni ON Tax %
(Mapped in settings)            (Configured during setup)
```

---

## Error Handling Strategy

### Graceful Degradation
- **Single Order Failure:** Don't block others in bulk operations
- **API Timeout:** Log error, add to retry queue (future feature)
- **Missing Data:** Validate before API call; show user-friendly error

### Logging Pattern
```php
try {
    $result = $this->apiClient->createDocument($data);
} catch (ApiException $e) {
    $this->logService->error(
        "Failed to create Moloni document for order {$orderId}",
        [
            'order_id' => $orderId,
            'error' => $e->getMessage(),
            'api_code' => $e->getCode(),
            'timestamp' => time(),
        ]
    );
    
    // Update order status to failed
    $order->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
    
    throw $e; // Re-throw or handle gracefully
}
```

---

## Extensibility (custom hooks)

`Support\Hooks` wraps WHMCS's `run_hook()` so the module can expose its own hook
points; integrators subscribe with `add_hook()` in `/includes/hooks/`. Each call
degrades to a no-op when `run_hook()` is undefined (unit tests / non-WHMCS), so
the wrapper is always safe to invoke. Three shapes:

- **`filter($hook, $value, $vars)`** — callbacks may replace `$value` (passed to
  them under `value`); the last non-empty return wins. Used for
  `MoloniOnProductName` (rename a product at creation) and
  `MoloniOnBeforeCreateDocument` (amend the `<Type>Insert` payload).
- **`doAction($hook, $vars)`** — fire-and-forget notification;
  `MoloniOnAfterCreateDocument`, `MoloniOnAfterCloseDocument`,
  `MoloniOnDocumentFailed`.
- **`allows($hook, $vars)`** — veto gate returning `true` unless a callback
  returns `false`; `MoloniOnBeforeCloseDocument` keeps a matched document a draft.

The product-name hook fires while mapping each line (`LineMapper::map()`), and its
result is used only if/when the product is actually created — a Moloni product
cannot be renamed afterwards, so it is created under a generic, action-describing
name while the order-specific name still appears on the document line.

See the hook table in [CLAUDE.md](CLAUDE.md) for the full list and payloads.

---

## Security Considerations

1. **OAuth2 Credential & Token Storage**
   - Client id/secret and access/refresh tokens stored in the single-row `mod_moloni_on_auth` table
   - Access limited to authenticated WHMCS admins
   - Tokens auto-refresh; when the refresh token is expired or the refresh grant call fails, the session tokens are cleared (`AuthService::logout`) and the admin must run the full login flow again

2. **API Communication**
   - HTTPS only (enforced by Moloni ON); SSL peer + host verification enabled in the cURL client
   - Bearer access token in the `Authorization` header (never in the query string)
   - OAuth grant/refresh uses form-encoded POST to `/v1/auth/grant`

3. **WHMCS Integration**
   - Verify admin session before operations
   - Check addon permissions
   - CSRF tokens on forms (WHMCS native)

4. **Input Validation**
   - Validate order IDs before processing
   - Sanitize customer data before sending to API
   - Validate document types against allowed list

---

## Performance Optimization

1. **Bulk Operations**
   - Batch up to 20 documents per request (configurable)
   - Use non-blocking operations (fire & forget logging)
   - Cache company list for 5 minutes

2. **PDF Downloads**
   - Fetch on-demand from Moloni ON (don't cache)
   - Stream directly to user (don't store)
   - Timeout after 30 seconds

3. **Database Queries**
   - Index `mod_moloni_on_orders.order_id`
   - Index `mod_moloni_on_logs.timestamp`
   - Use prepared statements

---

## Testing Strategy

### Unit Tests
```php
// Test GraphQL query builders
// Test service methods in isolation
// Mock API responses
// Test database model methods
```

### Integration Tests
```php
// Test end-to-end flow (order → document)
// Use mock Moloni ON API
// Verify database state
// Check log entries
```

### Manual Testing
```
1. Create test order in WHMCS
2. Authenticate with Moloni ON
3. Create document
4. Verify in Moloni ON dashboard
5. Download PDF
6. Check logs
```

---

## Deployment Checklist

- [ ] All tests pass
- [ ] CodeSniffer clean (PSR-12)
- [ ] Database migrations tested
- [ ] UI pages responsive
- [ ] PT & EN translations complete
- [ ] Error messages user-friendly
- [ ] Logs appear correctly
- [ ] PDF downloads work
- [ ] Bulk operations tested
- [ ] API timeout handling works
- [ ] Performance acceptable

---

## Future Enhancements

- [ ] Automatic retry for failed documents
- [ ] Stock sync (if Moloni ON supports)
- [ ] Payment reconciliation
- [ ] Multi-company support (switch companies easily)
- [ ] Webhooks from Moloni ON
- [ ] Advanced filtering on Documents page
- [ ] Export logs as CSV
- [ ] Document template customization

---

**Version:** 1.0.0  
**Last Updated:** July 2, 2026
