# Moloni ON - WHMCS Addon Module
## Project Specification & Development Prompt

**Project:** Moloni ON WHMCS Integration Addon  
**Target:** WHMCS 7.x and newer  
**Scope:** Create a WHMCS addon module to sync orders into Moloni ON platform and generate business documents  
**Status:** New Project (Blueprint Phase)

---

## Overview

This project creates a WHMCS addon module that integrates with **Moloni ON** (the new Moloni platform at https://docs.molonion.pt/) to automatically generate and manage business documents from WHMCS orders.

The addon syncs WHMCS order data to Moloni ON, creates documents (invoices, proformas, simplified invoices, etc.), tracks document lifecycle, and provides full administrative UI for managing the integration.

---

## Technical Requirements

### Environment & Dependencies
- **PHP Version:** 7.4+ (WHMCS 7+ standard)
- **WHMCS Version:** 7.0 and newer
- **Package Manager:** Composer
- **Code Standards:** PSR-12 with PHP CodeSniffer
- **API:** Moloni ON GraphQL API

### Key Libraries & Tools
- **composer.json** with:
  - PSR-4 autoloading for `/src` namespace
  - Dev dependencies: phpcs, phpunit
  - HTTP client for API calls (GuzzleHttp or similar)
  
---

## Features & Functional Requirements

### 1. Authentication & Onboarding
**Login Page (`/templates/login.php`)**
- API key input (no encryption needed)
- API key validation against Moloni ON
- Refresh logic for token validation
- Error handling for invalid credentials
- Success message on authenticated connection

**Company Select Page (`/templates/company.php`)**
- Fetch list of user's Moloni ON companies via GraphQL
- Display company information (name, vat, address)
- Allow user to select active company
- Save selected company to database
- Show current active company

### 2. Orders Management
**Orders Page (`/templates/document.php`)**
- List WHMCS orders pending document creation
- Display order info: order ID, customer name, total, date
- **Bulk Creation:** Create documents for multiple orders at once
- **Document Creation:** Sync order to Moloni ON and generate document
  - Support types: INVOICE, PRO_FORMA_INVOICE, SIMPLIFIED_INVOICE, INVOICE_RECEIPT, PURCHASE_ORDER
  - Map WHMCS products to Moloni ON products (all simple, no variants)
  - Map customer to Moloni ON customer (create if doesn't exist)
  - Create document with correct amounts, items, tax info
- **Discard Order:** Mark order as "do not sync" (disappears from pending list)
- **Error Handling:** Failed creations log to database, appear in Logs page, order remains pending

### 3. Settings Page
**Settings/Config Page (`/templates/config.php`)**
- **Document Type Setting:** Select default document type to create
- **Document Status Setting:** Select post-creation status (e.g., draft, finalized, pending)
- **Additional Settings:** Tax exemption settings, payment method mapping, etc.
- **Save Settings:** Persist to database
- **Edit Settings:** Retrieve and display current settings

### 4. Documents Page
**Documents Page (`/templates/documents.php`)**
- List all documents successfully created in Moloni ON
- Display: Document number, type, customer, amount, creation date, status
- **Download PDF:** Fetch PDF from Moloni ON on-demand (don't store locally)
- **Discarded Orders:** Show orders marked as "do not sync"
  - **Revert Action:** Move discarded order back to pending (undo discard)

### 5. Tools Page
**Tools Page (`/templates/tools.php`)**
- Currently empty placeholder
- Future: Bulk actions, data cleanup, sync utilities, etc.

### 6. Logs Page
**Logs Page (`/templates/logs.php`)**
- Display all application logs from database
- Log fields: timestamp, level (error/warning/info), message, order ID (if applicable)
- **Filters:** By date range, log level, order ID
- **Actions:** Clear logs (delete all)
- Real-time display of integration issues

---

## API & Data Integration

### Moloni ON GraphQL
- Base URL: https://api.molonion.pt/graphql
- Authentication: API key in header
- **Queries/Mutations in separate files** (e.g., `/src/Moloni/GraphQL/Queries/*.graphql`)
- IDE support: Generate TypeScript/PHP types from schema for autocomplete
- Wrap queries in PHP classes for type-safe usage

**Key Operations:**
- `getMe()` - Get authenticated user details
- `getCompanies()` - List user's companies
- `selectCompany(id)` - Set active company context
- `getDocumentTypes()` - Fetch available document types
- `createCustomer()` - Upsert customer in Moloni ON
- `createDocument()` - Create invoice/document
- `getDocument()` - Fetch document details & PDF
- `updateDocumentStatus()` - Change document status

### Database Schema
**WHMCS Custom Tables:**
- `mod_moloni_on_config` - Settings storage (api_key, selected_company_id, document_type, etc.)
- `mod_moloni_on_orders` - Tracking synced orders (order_id, moloni_document_id, status, created_at)
- `mod_moloni_on_logs` - Application logs (timestamp, level, message, order_id, context)
- `mod_moloni_on_documents` - Created documents (order_id, order_total, invoice_id, invoice_date, invoice_status, invoice_total, value)

---

## UI/UX & Navigation

### Page Structure
```
Admin Dashboard > Addons > Moloni ON
├── Login (if not authenticated)
├── Company Select (if no company selected)
├── Dashboard (main)
│   ├── Orders (pending documents)
│   ├── Documents (created documents)
│   ├── Settings (config)
│   ├── Tools (utilities)
│   └── Logs (activity)
```

### Design
- **Framework:** Bootstrap 4+ or Materialize (match WHMCS 7+ defaults)
- **Responsive:** Mobile-friendly UI
- **Navigation:** Sidebar or top nav with clear section labels
- **Status Indicators:** Color-coded status badges (pending, synced, failed, etc.)
- **Modals:** Confirm before bulk actions, show error details

---

## Internationalization (i18n)

### Supported Languages
- **English (EN)** - Default, all UI strings
- **Portuguese (PT)** - First-person tone ("Eu", "Meu", "Criar documento meu")

### File Structure
```
/lang/
  en.php      # English strings
  pt.php      # Portuguese (first-person)
```

### Translation Notes
- PT translations use **first-person perspective** (e.g., "Eu criei um documento" vs "Um documento foi criado")
- All UI labels, messages, errors translated
- Implement translations only if low overhead; otherwise defer to v2

---

## Testing & Quality

### Unit Tests
- Test GraphQL query builders
- Test Order→Document mapper
- Test database model methods
- Use PHPUnit; run tests via `composer test`

### Code Quality
- **PHP CodeSniffer:** PSR-12 compliance check
- **Configuration:** `phpcs.xml` at project root
- Run via `composer lint` or `vendor/bin/phpcs`

### Integration Tests
- Mock Moloni ON API calls
- Test end-to-end order creation flow
- Test error handling & logging

---

## Acceptance Criteria

### MVP (Phase 1)
✅ User can authenticate with Moloni ON API key  
✅ User can select a Moloni ON company  
✅ User can create documents from WHMCS orders (one or bulk)  
✅ Documents created successfully in Moloni ON  
✅ Order discarding prevents sync  
✅ Document details and PDF downloadable from Moloni ON  
✅ Logs capture all actions and errors  
✅ All UI pages functional (Login, Company, Orders, Documents, Settings, Tools, Logs)  
✅ English UI fully translated; PT translations in place  
✅ Code passes PHPCodeSniffer with zero warnings  

### Performance & Reliability
✅ Bulk operations (create 10+ documents) complete without timeout  
✅ Failed document creations do NOT block other orders  
✅ All errors logged with context for debugging  
✅ API calls handle rate limiting gracefully  

---

## Folder Structure

```
moloni-on-whmcs/
├── .claude/                          # Project metadata (journal, plans)
│   └── journal/
│       ├── 2026-01-01-kickoff.md
│       └── 2026-01-15-api-integration.md
├── src/
│   └── Moloni/
│       ├── Admin/
│       │   └── Dispatcher.php         # Route requests to pages/actions
│       ├── Api/
│       │   ├── ApiClient.php          # Base HTTP client
│       │   ├── MoloniClient.php        # Moloni ON specific wrapper
│       │   └── Exceptions/
│       │       ├── ApiException.php
│       │       └── ValidationException.php
│       ├── GraphQL/
│       │   ├── Queries/
│       │   │   ├── GetMe.graphql
│       │   │   ├── GetCompanies.graphql
│       │   │   ├── GetDocumentTypes.graphql
│       │   │   └── GetDocument.graphql
│       │   ├── Mutations/
│       │   │   ├── CreateCustomer.graphql
│       │   │   ├── CreateDocument.graphql
│       │   │   └── UpdateDocumentStatus.graphql
│       │   └── QueryBuilder.php       # PHP wrapper for GraphQL
│       ├── Models/
│       │   ├── Order.php
│       │   ├── Document.php
│       │   ├── Company.php
│       │   ├── Customer.php
│       │   └── Log.php
│       ├── Services/
│       │   ├── DocumentService.php    # Create docs from orders
│       │   ├── OrderService.php       # Manage order state
│       │   ├── LogService.php         # Logging
│       │   └── SettingsService.php    # Config management
│       ├── Database/
│       │   ├── Installer.php          # Database setup (tables, migrations)
│       │   └── Migrations/
│       │       ├── CreateConfigTable.php
│       │       ├── CreateOrdersTable.php
│       │       ├── CreateLogsTable.php
│       │       └── CreateDocumentsTable.php
│       ├── Exceptions/
│       │   ├── MoloniException.php
│       │   ├── DocumentException.php
│       │   └── AuthException.php
│       ├── Facades/
│       │   ├── ApiClient.php          # Static access to API
│       │   └── Logger.php             # Static access to logging
│       └── Bootstrap.php              # Module initialization
├── templates/
│   ├── Blocks/
│   │   ├── header.php
│   │   ├── footer.php
│   │   ├── navbar.php
│   │   └── messages.php               # Flash messages / alerts
│   ├── login.php
│   ├── company.php
│   ├── document.php                   # Orders pending sync
│   ├── documents.php                  # Created documents
│   ├── config.php                     # Settings
│   ├── tools.php
│   ├── logs.php
│   └── Modals/
│       ├── confirmBulkCreate.php
│       ├── errorDetails.php
│       └── documentDetails.php
├── public/
│   ├── css/
│   │   ├── style.css                  # Main stylesheet
│   │   ├── tables.css                 # DataTables styling
│   │   └── forms.css
│   ├── js/
│   │   ├── app.js                     # Main app logic
│   │   ├── documents.js               # Document page interactions
│   │   ├── orders.js                  # Order management
│   │   └── logs.js                    # Log filtering/clearing
│   ├── img/
│   │   ├── logo.png
│   │   └── moloni-icon.svg
│   └── lib/
│       ├── datatables.min.js
│       └── bootstrap.min.js
├── lang/
│   ├── en.php
│   └── pt.php
├── tests/
│   ├── Unit/
│   │   ├── ApiClientTest.php
│   │   ├── OrderServiceTest.php
│   │   └── DocumentServiceTest.php
│   ├── Feature/
│   │   └── CreateDocumentTest.php
│   └── bootstrap.php
├── composer.json
├── phpcs.xml
├── phpunit.xml
├── moloni_on.php                      # Main module entry point
├── hooks.php                          # WHMCS hooks
├── README.md
├── SETUP.md
├── ARCHITECTURE.md
└── LICENSE.md
```

---

## Development Workflow

### Getting Started
1. Clone/init repository
2. Run `composer install`
3. Copy to WHMCS `/modules/addons/moloni_on/`
4. Activate in WHMCS admin: Setup > Addon Modules > Moloni ON
5. Run database installer
6. Navigate to Moloni ON module, enter API key

### Daily Development
- Make changes in `/src` and `/templates`
- Run `composer lint` before commits (fix CodeSniffer issues)
- Test in WHMCS admin panel
- Check logs for errors: `mod_moloni_on_logs` table
- Write tests for new features

### Commits & Versioning
- **Branch naming:** `feature/xxx`, `bugfix/xxx`, `improvement/xxx`
- **Commit messages:** Clear, reference issue/feature
- **Versions:** semver (v1.0.0, v1.1.0, etc.)
- **Changelog:** Track in `CHANGELOG.md`

---

## Deployment & Distribution

### Build & Package
1. Clean vendor/ and build folder
2. Run full test suite
3. Run CodeSniffer check
4. Create zip: `moloni-on-whmcs-v{version}.zip`
5. Include in release notes

### WHMCS Marketplace (Future)
- [ ] Submit to WHMCS marketplace
- [ ] Test on multiple WHMCS versions
- [ ] Provide install documentation

---

## Notes & Assumptions

- **No Encryption Required:** API keys stored plaintext in database (per spec)
- **Simple Products:** WHMCS has no variants; all products treated as simple
- **PDF Fetching:** PDFs fetched on-demand from Moloni ON, not cached locally
- **Moloni ON Account:** User provides own Moloni ON API credentials
- **Database Tables:** Created on module activation; removed on uninstall (if desired)
- **Translations:** PT uses first-person ("Meu documento", "Eu criei"), EN standard passive voice

---

## References

- **Moloni ON API:** https://docs.molonion.pt/
- **WHMCS Addon Module Guide:** https://docs.whmcs.com/Addon_Modules
- **PHP Standards:** PSR-12 (https://www.php-fig.org/psr/psr-12/)

---

## Questions to Resolve During Development

- Which HTTP client? (GuzzleHttp, cURL native, etc.)
- Logging level defaults? (error, warning, info, debug)
- Should discarded orders be permanently deleted or soft-deleted?
- PDF storage: cache for a period or always fetch fresh?
- Bulk operation limits: max documents per operation?
- Payment method mapping: automatic or manual config?

---

## Success Metrics

✅ Module installs/activates cleanly in WHMCS 7+  
✅ User can sync orders and create documents in Moloni ON  
✅ All created documents visible in Moloni ON dashboard  
✅ Errors handled gracefully with clear user feedback  
✅ Admin can view logs and troubleshoot issues  
✅ Code is maintainable, testable, and documented  
✅ PT & EN UI fully functional  

---

**Version:** 1.0.0-DRAFT  
**Last Updated:** July 2, 2026  
**Owner:** Development Team
