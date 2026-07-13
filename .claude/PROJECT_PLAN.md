# Development Plan & Implementation Checklist

## Phase 1: Foundation & Infrastructure

### 1.1 Project Setup
- [ ] Initialize git repository (if not already)
- [ ] Create `.gitignore` for vendor/, .env, IDE files
- [ ] Create `composer.json` with:
  - PSR-4 autoloading for `src/Moloni`
  - GuzzleHttp (or alternative HTTP client)
  - PHPUnit for testing
  - PHP CodeSniffer for linting
  - PSR logging interface
- [ ] Run `composer install`
- [ ] Create `/src` directory structure matching architecture

**Acceptance:** `composer install` runs without errors; structure ready for development

---

### 1.2 Database & Data Models
- [ ] Create database installer in `/src/Moloni/Database/Installer.php`
- [ ] Define four tables:
  - `mod_moloni_on_config` (key-value settings)
  - `mod_moloni_on_orders` (order tracking)
  - `mod_moloni_on_logs` (activity logs)
  - `mod_moloni_on_documents` (created documents: order_id, order_total, invoice_id, invoice_date, invoice_status, invoice_total, value)
- [ ] Create Model classes:
  - `Order` (extends AbstractModel)
  - `Config` (extends AbstractModel)
  - `Log` (extends AbstractModel)
  - `Document` (extends AbstractModel)
- [ ] Implement CRUD methods for each model
- [ ] Create abstract `AbstractModel` base class with common DB methods

**Acceptance:** Models can be instantiated; basic CRUD works; database tables created on module activation

---

### 1.3 API Client Framework
- [ ] Create `ApiClient` class in `/src/Moloni/Api/ApiClient.php`:
  - Constructor: `__construct($apiKey, $baseUrl, $timeout)`
  - Method: `request($query, $variables = [])`
  - Method: `setApiKey($key)`
  - Method: `validateConnection()`
  - Error handling with custom exceptions
- [ ] Create `MoloniClient` wrapper in `/src/Moloni/Api/MoloniClient.php`:
  - Constructor: `__construct(ApiClient $client)`
  - Methods: `getMe()`, `getCompanies()`, `selectCompany($id)`, etc.
  - Wraps low-level API calls with domain logic
- [ ] Create exception classes:
  - `ApiException`
  - `ValidationException`
  - `AuthException`
  - `DocumentException`

**Acceptance:** Can make test API call to Moloni ON; authentication works

---

### 1.4 GraphQL Query Organization
- [ ] Create directory structure `/src/Moloni/GraphQL/Queries/` and `/Mutations/`
- [ ] Create query builder classes (example: `GetMe`, `GetCompanies`)
  - Each contains GraphQL string as class constant
  - Methods: `query()`, `variables($data)`
- [ ] Create at least 5 core queries:
  - GetMe - get authenticated user
  - GetCompanies - list user's companies
  - GetDocumentTypes - supported document types
  - GetCustomer - fetch customer details
  - GetDocument - fetch document details
- [ ] Create at least 3 core mutations:
  - CreateCustomer - upsert customer
  - CreateDocument - create document
  - UpdateDocumentStatus - change status

**Acceptance:** GraphQL queries are organized in files; can be called from PHP classes

---

### 1.5 Logging System
- [ ] Create `LogService` in `/src/Moloni/Services/LogService.php`:
  - Methods: `log()`, `info()`, `warning()`, `error()`, `debug()`
  - Each writes to `mod_moloni_on_logs` table
  - Includes timestamp, level, message, context
- [ ] Create `LoggerFacade` for static access:
  - `LoggerFacade::info("message", $context)`
- [ ] Create `/public/js/logs.js` for log page interactions
- [ ] Ensure all errors are logged throughout application

**Acceptance:** LogService can write to database; logs appear in table; no silent failures

---

### 1.6 Configuration System
- [ ] Create `SettingsService` in `/src/Moloni/Services/SettingsService.php`:
  - Methods: `getSetting($key)`, `setSetting($key, $value)`, `getAll()`
  - Backed by `mod_moloni_on_config` table
- [ ] Define core settings:
  - `api_key` - Moloni ON authentication
  - `selected_company_id` - Active company
  - `document_type` - Default document type
  - `document_status` - Post-creation status
- [ ] Create facade for easy access: `SettingsService::getSetting('api_key')`

**Acceptance:** Settings can be stored and retrieved; values persist across page loads

---

## Phase 2: Authentication & Onboarding

### 2.1 Login Page
- [ ] Create `/templates/login.php`
- [ ] Design form:
  - API key input field (password type)
  - "Connect" button
  - Error message display area
- [ ] Create login form handler (in moloni_on.php):
  - Validate API key format
  - Call `MoloniClient->getMe()` to validate
  - On success: save API key, redirect to company select
  - On error: show error message, stay on login
- [ ] Add "Remember me" (optional, for WHMCS session)

**Acceptance:** User can enter API key and authenticate; invalid key shows error; valid key redirects

---

### 2.2 Company Selection Page
- [ ] Create `/templates/company.php`
- [ ] Design page:
  - List all user's Moloni ON companies (fetched via API)
  - Display: company name, tax ID, address
  - Radio button or click-to-select each company
  - "Select Company" button
- [ ] Create company selection handler:
  - Call `MoloniClient->getCompanies()` to list
  - Save selected company ID to settings
  - Fetch and cache document types
  - Redirect to main dashboard
- [ ] Show current selected company (if switching)

**Acceptance:** Company list displays correctly; selection saves; page redirects

---

### 2.3 Session & Authentication Middleware
- [ ] Create `AuthService` to check:
  - WHMCS admin session exists
  - User has Moloni addon permission
  - API key is set in settings
  - Company is selected
- [ ] Create middleware/wrapper:
  - All routes check authentication
  - Redirect to login if not authenticated
  - Redirect to company select if not selected
- [ ] Implement in main router (moloni_on.php)

**Acceptance:** Unauthenticated users redirected to login; authenticated users see dashboard

---

## Phase 3: Order Management

### 3.1 Orders Service
- [ ] Create `OrderService` in `/src/Moloni/Services/OrderService.php`:
  - Method: `getPendingOrders()` - fetch WHMCS orders not yet synced
  - Method: `getCreatedDocuments()` - fetch successfully synced orders
  - Method: `getDiscardedOrders()` - fetch orders marked as "do not sync"
  - Method: `discardOrder($orderId)` - mark order status as 'discarded'
  - Method: `revertDiscard($orderId)` - move discarded order back to pending
- [ ] Order query logic:
  - Pending: orders not in `mod_moloni_on_orders` table, or status='pending'
  - Created: status='synced' and has moloni_document_id
  - Discarded: status='discarded'

**Acceptance:** OrderService returns correct lists; state changes persist

---

### 3.2 Document Service - Core
- [ ] Create `DocumentService` in `/src/Moloni/Services/DocumentService.php`
- [ ] Method: `createDocumentFromOrder($orderId, $documentType)`:
  1. Fetch WHMCS order data
  2. Check if customer exists in Moloni ON
  3. If not: create customer via `MoloniClient->createCustomer()`
  4. Map WHMCS order items to Moloni ON format
  5. Call `MoloniClient->createDocument()` with mapped data
  6. Update `mod_moloni_on_orders` (status, moloni_document_id)
  7. Persist created document to `mod_moloni_on_documents` (order_id, order_total, invoice_id, invoice_date, invoice_status, invoice_total, value)
  8. Log success/failure
  9. Return document ID or throw exception
- [ ] Handle errors:
  - Document creation fails: log error, mark order as 'failed', don't throw
  - Customer creation fails: log, try to continue
  - Data validation fails: log and skip order

**Acceptance:** Can create a document from a WHMCS order; Moloni ON shows document

---

### 3.3 Bulk Document Creation
- [ ] Extend DocumentService with:
  - Method: `bulkCreateDocuments($orderIds, $documentType)`
  - Loop through orders
  - Call `createDocumentFromOrder()` for each
  - Catch exceptions per-order (don't block others)
  - Log results (X created, Y failed)
  - Return summary with successful & failed lists
- [ ] Implement with configurable limits:
  - Max 20 documents per operation (configurable)
  - Timeout per document: 30 seconds
  - Overall timeout: 5 minutes

**Acceptance:** Bulk create handles 10+ orders; failed orders don't block others; summary returned

---

### 3.4 Orders Page (UI)
- [ ] Create `/templates/document.php` (pending orders)
- [ ] Design table:
  - Columns: Order ID, Customer, Amount, Date, Status, Actions
  - DataTables for sorting/filtering/pagination
  - DataTables with AJAX (optional, for large lists)
- [ ] Actions:
  - Individual "Create Document" button (with document type selector)
  - Bulk "Create All" button
  - "Discard" button (mark as do-not-sync)
  - Error indicator (if creation failed)
- [ ] Modals:
  - Document type selector modal (dropdown, default from settings)
  - Confirm bulk creation modal
  - Error details modal (show error message)
- [ ] Form handlers:
  - Single create: calls DocumentService->createDocumentFromOrder()
  - Bulk create: calls DocumentService->bulkCreateDocuments()
  - Discard: calls OrderService->discardOrder()

**Acceptance:** UI displays pending orders; buttons trigger correct actions; feedback shown

---

## Phase 4: Document Management

### 4.1 Documents Service
- [ ] Extend DocumentService with:
  - Method: `getDocumentDetails($documentId)` - fetch from Moloni ON
  - Method: `downloadPdf($documentId)` - fetch PDF from Moloni ON
  - Method: `updateDocumentStatus($documentId, $status)` - change status
  - Method: `revertDiscard($orderId)` - move discarded order back to pending
- [ ] PDF handling:
  - Fetch on-demand from Moloni ON
  - Stream directly to user
  - Don't cache locally
  - Handle timeouts gracefully

**Acceptance:** Can fetch document details and PDFs; PDFs download correctly

---

### 4.2 Documents Page (UI)
- [ ] Create `/templates/documents.php` (created documents)
- [ ] Design table:
  - Columns: Document #, Type, Customer, Amount, Date, Status, Actions
  - Same DataTables treatment as orders page
- [ ] Display discarded orders in separate section:
  - Columns: Order ID, Customer, Discarded Date, Actions
  - "Revert" button to move back to pending
- [ ] Actions:
  - "Download PDF" - calls DocumentService->downloadPdf()
  - "View Details" - shows modal with document info
  - "Revert" (for discarded orders)
- [ ] Modals:
  - Document details modal (number, status, customer, total)
  - PDF viewer (if integration available, else direct download)

**Acceptance:** Documents list displays correctly; PDFs download; discarded orders show with revert option

---

## Phase 5: Settings & Configuration

### 5.1 Settings Page
- [ ] Create `/templates/config.php`
- [ ] Form fields:
  - Document Type dropdown (fetch from API)
  - Document Status dropdown (fetch from API)
  - Tax Exemption checkbox (optional)
  - Other settings as identified
- [ ] Form handler:
  - Validate inputs
  - Call `SettingsService->setSetting()` for each
  - Show success message
  - Persist to database

**Acceptance:** Settings form displays; changes save; values persist

---

### 5.2 Tools Page (Placeholder)
- [ ] Create `/templates/tools.php`
- [ ] Add placeholder content: "Tools coming soon"
- [ ] Design space for future utilities:
  - Test connection button
  - Data sync status
  - Cache clearing
  - etc.

**Acceptance:** Page loads; placeholder visible

---

## Phase 6: Logging & Monitoring

### 6.1 Logs Page
- [ ] Create `/templates/logs.php`
- [ ] Design:
  - Table with columns: Timestamp, Level, Message, Order ID, Action
  - DataTables with sorting/filtering
  - Filter by: date range, level (error/warning/info), order ID
  - "Clear All Logs" button with confirmation
  - Pagination (show 50 per page)
- [ ] Form handlers:
  - Filter/search: reload table with filters applied
  - Clear logs: delete all from `mod_moloni_on_logs`, confirm first

**Acceptance:** Logs display correctly; filters work; clear logs removes all entries

---

### 6.2 Log Cleanup & Maintenance
- [ ] Auto-cleanup old logs (optional):
  - Keep logs for 90 days by default
  - Delete older logs via cron or on-demand
- [ ] Log export (future):
  - Export logs as CSV
  - Date range export

**Acceptance:** Logs persist; can be filtered and cleared

---

## Phase 7: UI/UX & Navigation

### 7.1 Layout & Navigation
- [ ] Create main layout template `/templates/Blocks/header.php`:
  - WHMCS admin header
  - Module name & logo
- [ ] Create navigation template `/templates/Blocks/navbar.php`:
  - Tabbed or sidebar navigation
  - Tabs: Orders, Documents, Settings, Tools, Logs
  - Highlight active tab
  - Show user info & company
- [ ] Create footer template `/templates/Blocks/footer.php`:
  - Copyright, version, links
- [ ] Create messages template `/templates/Blocks/messages.php`:
  - Flash messages (success, error, info, warning)
  - Auto-dismiss after 5 seconds

**Acceptance:** Navigation works; messages display correctly; layout is professional

---

### 7.2 Styling & Responsiveness
- [ ] Create main stylesheet `/public/css/style.css`:
  - Bootstrap 4+ integration
  - Custom colors & branding
  - Form styling
  - Table styling
- [ ] Create responsive design:
  - Mobile-friendly on tablet/phone
  - Sidebar collapses on small screens
- [ ] Create `/public/js/app.js` for interactions:
  - Modal handling
  - Form validation
  - Bulk action handling
  - AJAX calls

**Acceptance:** UI looks professional; responsive on all devices; interactions work

---

## Phase 8: Internationalization (i18n)

### 8.1 English Translations
- [ ] Create `/lang/en.php` with all UI strings:
  - Page titles, button labels, form labels
  - Error messages, success messages
  - Help text and tooltips
  - Navigation labels

**Acceptance:** All UI text in `/lang/en.php`; no hardcoded strings in templates

---

### 8.2 Portuguese Translations
- [ ] Create `/lang/pt.php` with Portuguese translations
- [ ] Use first-person perspective:
  - "Meu documento" instead of "O documento"
  - "Eu criei" instead of "Foi criado"
  - "Minhas configurações" instead of "Configurações"
- [ ] Translation context method in templates:
  - `_('string_key')` or `trans('module::key')`

**Acceptance:** PT translations complete; first-person perspective used consistently

---

## Phase 9: Testing

### 9.1 Unit Tests
- [ ] Create test files in `/tests/Unit/`:
  - `ApiClientTest.php` - test HTTP client
  - `OrderServiceTest.php` - test order queries
  - `DocumentServiceTest.php` - test document creation
  - `LogServiceTest.php` - test logging
  - `SettingsServiceTest.php` - test settings management
- [ ] Use PHPUnit fixtures and mocks
- [ ] Mock Moloni ON API responses
- [ ] Test error scenarios

**Acceptance:** All unit tests pass; 70%+ code coverage

---

### 9.2 Integration Tests
- [ ] Create test files in `/tests/Feature/`:
  - `CreateDocumentTest.php` - end-to-end order→document flow
  - `AuthenticationTest.php` - login and company selection
  - `BulkOperationTest.php` - bulk document creation
- [ ] Use test database (isolated)
- [ ] Mock API calls but test real service logic
- [ ] Verify database state after operations

**Acceptance:** Integration tests pass; real workflows validated

---

### 9.3 Manual Testing
- [ ] Test on real WHMCS instance (dev environment)
- [ ] Create test orders and sync documents
- [ ] Verify PDFs download correctly
- [ ] Test error scenarios (invalid API key, network timeout, etc.)
- [ ] Test UI on multiple browsers
- [ ] Test responsive design on mobile

**Acceptance:** All features work end-to-end; no bugs in manual testing

---

## Phase 10: Code Quality & Documentation

### 10.1 Code Standards
- [ ] Run PHP CodeSniffer:
  - `composer lint` checks PSR-12 compliance
  - Fix all violations: `composer lint:fix`
  - Zero warnings before release
- [ ] Review code:
  - Class naming conventions
  - Method organization
  - Error handling
  - Code comments where needed

**Acceptance:** `composer lint` shows zero violations

---

### 10.2 Documentation
- [ ] Update `/README.md`:
  - Project overview
  - Installation steps
  - Configuration
  - Usage examples
  - Support links
- [ ] Keep `/ARCHITECTURE.md` current
- [ ] Update `/SETUP.md` with any changes
- [ ] Document database schema in `/ARCHITECTURE.md`
- [ ] Add inline code comments for complex logic

**Acceptance:** README is complete; architecture documented; code is readable

---

### 10.3 Changelog
- [ ] Create `/CHANGELOG.md`:
  - Version 1.0.0 - Initial release
  - List all features
  - Known limitations
  - Breaking changes (if any)

**Acceptance:** Changelog documents all changes from initial to release

---

## Phase 11: Deployment & Release

### 11.1 Final Testing
- [ ] Test on WHMCS 7.0, 7.5, 8.0, latest version
- [ ] Test on PHP 7.4, 8.0, 8.1
- [ ] Performance testing (10+ documents, 100+ logs)
- [ ] Security review:
  - No SQL injection
  - No XSS vulnerabilities
  - API key handling secure
  - CSRF tokens present

**Acceptance:** No issues found in testing

---

### 11.2 Build & Package
- [ ] Clean vendor/ and build artifacts
- [ ] Run full test suite: `composer test`
- [ ] Run linter: `composer lint`
- [ ] Create zip package: `moloni-on-whmcs-v1.0.0.zip`
- [ ] Include in archive:
  - All source code
  - composer.json (users will run composer install)
  - README.md, CHANGELOG.md
  - LICENSE.md
  - Exclude: .git, .idea, node_modules, vendor (to be installed)

**Acceptance:** Zip file created; all necessary files included

---

### 11.3 Release & Documentation
- [ ] Create release notes
- [ ] Update support documentation
- [ ] Create installation guide
- [ ] Add to WHMCS marketplace (optional)
- [ ] Announce release to users

**Acceptance:** Package ready for distribution; documentation complete

---

## Daily Development Checklist

### Before Starting Work
- [ ] Pull latest from git (if team project)
- [ ] Review project journal for context
- [ ] Check for outstanding issues/questions

### During Development
- [ ] Write code following PSR-12 standards
- [ ] Add comments for complex logic
- [ ] Test changes locally
- [ ] Write tests for new features
- [ ] Update documentation

### Before Committing
- [ ] Run `composer lint` (check for style issues)
- [ ] Run `composer test` (all tests pass)
- [ ] Test in WHMCS admin
- [ ] Check logs for errors
- [ ] Write clear commit message

### End of Day
- [ ] Commit and push changes
- [ ] Update journal entry with progress
- [ ] Note any blockers or questions
- [ ] Document decisions made

---

## Success Criteria

✅ All features from Phase 1-6 complete and tested  
✅ Code passes linter (zero CodeSniffer violations)  
✅ 70%+ unit test coverage  
✅ All integration tests pass  
✅ Manual testing successful on real WHMCS  
✅ EN & PT translations complete and verified  
✅ Documentation complete and current  
✅ Ready for production deployment  

---

**Version:** 1.0.0-PLAN  
**Last Updated:** July 2, 2026  
**Owner:** Development Team
