# Moloni ON WHMCS - Project Journal

## Entry: 2026-07-02 - Project Kickoff

**Status:** 🚀 Kickoff / Planning Phase  
**Duration:** TBD  
**Owner:** Development Team

---

## Summary

Initiating Moloni ON WHMCS addon module project. This addon integrates WHMCS with Moloni ON (new Moloni platform) to automatically sync orders and create business documents (invoices, proformas, etc.).

**Client Requirement:** Create a production-ready WHMCS addon with full UI, order synchronization, document creation, and logging.

---

## Key Decisions Made

### 1. Architecture
- **Layered Architecture:** Presentation → Services → Models → API
- **GraphQL for Moloni:** Use GraphQL queries in separate files for clarity
- **Service Layer:** Isolate business logic from routing
- **Single Responsibility:** Each class has one clear purpose

### 2. Technology Stack
- **Framework:** WHMCS native (no Laravel, Symfony, etc.)
- **HTTP Client:** GuzzleHttp (or native cURL for simplicity)
- **Database:** WHMCS native MySQL
- **Code Standards:** PSR-12 via PHP CodeSniffer
- **Testing:** PHPUnit for unit/integration tests

### 3. Development Approach
- **No Encryption:** API keys stored plaintext (per client spec)
- **On-Demand PDFs:** Fetch PDFs from Moloni ON, don't cache
- **Bulk Operations:** Support creating 5+ documents without timeout
- **Error Resilience:** Failed documents don't block others
- **Logging First:** Every action logged to database for debugging

### 4. UI/UX
- **Bootstrap 4+:** Responsive, professional design
- **Single-Page Workflow:** Tabbed interface (Orders → Documents → Settings → Logs)
- **DataTables:** For order/document lists with filtering & pagination
- **Modals:** Confirm destructive actions, show error details

### 5. Internationalization
- **English (EN):** Default, all UI strings
- **Portuguese (PT):** First-person perspective ("Meu documento", "Eu criei")
- **Implementation:** Only if reasonable overhead; else defer to v2

### 6. Quality Gates
- All tests pass
- CodeSniffer zero violations
- Logs capture all errors
- PDF downloads work
- Bulk operations tested

---

## Project Scope

### In Scope
✅ User authentication with Moloni ON API key  
✅ Company selection and context switching  
✅ Order syncing with bulk creation support  
✅ 5 document types (INVOICE, PRO_FORMA_INVOICE, SIMPLIFIED_INVOICE, INVOICE_RECEIPT, PURCHASE_ORDER)  
✅ Document lifecycle management (create, discard, revert)  
✅ Settings page for document type & status configuration  
✅ PDF downloads from Moloni ON  
✅ Comprehensive logging (errors, actions, timestamps)  
✅ Logs page with filtering and export  
✅ Tools page (placeholder for future utilities)  
✅ EN & PT translations  

### Out of Scope (v2+)
❌ Stock synchronization  
❌ Payment reconciliation  
❌ Webhooks from Moloni ON  
❌ Document template customization  
❌ Automatic retries for failed documents  
❌ Multi-tenant mode  

---

## Folder Structure Overview

```
moloni-on-whmcs/
├── src/Moloni/                # Source code (PSR-4 autoloading)
│   ├── Admin/                 # WHMCS routing
│   ├── Api/                   # API client & GraphQL
│   ├── Services/              # Business logic
│   ├── Models/                # Database models
│   └── Exceptions/            # Custom exceptions
├── templates/                 # UI pages & blocks
├── public/                    # CSS, JS, images
├── lang/                      # i18n (en.php, pt.php)
├── tests/                     # Unit & integration tests
├── .claude/                   # Project metadata (this journal)
├── moloni_on.php              # Main WHMCS entry point
├── hooks.php                  # WHMCS hooks
└── composer.json              # Dependencies
```

---

## Development Phases

### Phase 1: Core Infrastructure (Week 1-2)
- [x] Project structure & setup docs created
- [ ] Composer setup & dependency management
- [ ] Database table creation & installer
- [ ] ApiClient class for GraphQL
- [ ] MoloniClient wrapper
- [ ] Basic authentication (API key validation)
- [ ] Logging system
- **Deliverable:** Can authenticate with Moloni ON API

### Phase 2: Authentication UI (Week 2)
- [ ] Login page template
- [ ] Company select page
- [ ] Settings storage
- [ ] Session management
- **Deliverable:** User can log in and select company

### Phase 3: Order Synchronization (Week 3)
- [ ] Order Model & service
- [ ] Document creation service
- [ ] Orders list page
- [ ] Bulk create operation
- [ ] Error handling & logging
- **Deliverable:** Can create documents from WHMCS orders

### Phase 4: Document Management (Week 4)
- [ ] Documents list page
- [ ] PDF download
- [ ] Discard/revert functionality
- [ ] Status tracking
- **Deliverable:** Full document lifecycle

### Phase 5: Settings & Tools (Week 5)
- [ ] Settings page
- [ ] Configuration storage
- [ ] Tools page (placeholder)
- [ ] Settings service
- **Deliverable:** Admin can customize behavior

### Phase 6: Logging & Monitoring (Week 5)
- [ ] Logs page with filters
- [ ] Log clearing
- [ ] Export functionality
- [ ] Dashboard summary
- **Deliverable:** Full visibility into operations

### Phase 7: Testing & Polish (Week 6)
- [ ] Unit tests (70%+ coverage)
- [ ] Integration tests
- [ ] Code review & refactoring
- [ ] CodeSniffer compliance
- [ ] i18n (EN & PT)
- [ ] UI/UX improvements
- [ ] Performance optimization
- **Deliverable:** Production-ready code

---

## Technical Decisions & Rationale

| Decision | Rationale |
|----------|-----------|
| **GraphQL** | Moloni ON API native; cleaner than REST |
| **Separate query files** | IDE autocomplete; version control clarity |
| **Service layer** | Testable; separates concerns; reusable |
| **Database logging** | Real-time visibility; searchable; persistent |
| **PSR-12** | Industry standard; maintainable; team-friendly |
| **No caching for PDFs** | Freshness guaranteed; simple; avoids storage costs |
| **First-person PT** | Client requirement; aligns with branding |

---

## Risks & Mitigation

| Risk | Impact | Mitigation |
|------|--------|-----------|
| **API downtime** | Orders can't sync | Implement retry queue & alert (v2) |
| **Rate limiting** | Bulk operations fail | Test limits; implement backoff (v1.1) |
| **WHMCS version drift** | Incompatibility | Test on WHMCS 7.0, 8.0, latest |
| **GraphQL schema changes** | Queries break | Monitor Moloni ON API changes; version queries |
| **Large bulk operations** | Timeout | Implement pagination; batch size limits |
| **Data mapping issues** | Documents created incorrectly | Comprehensive testing; error logs |

---

## Questions for Stakeholders

1. **Retry Strategy:** Should failed documents be queued for automatic retry?
2. **Bulk Limits:** Max documents per bulk operation? (Suggested: 20)
3. **PDF Storage:** Cache for performance, or always fetch fresh?
4. **Tax Mapping:** Pre-configured mapping or manual per document?
5. **Customer Creation:** Auto-create in Moloni ON if not exists?
6. **Webhook Support:** Listen for Moloni ON events (future)?

---

## Dependencies & Versions

- **WHMCS:** 7.0+
- **PHP:** 7.4+
- **MySQL:** 5.7+
- **Composer:** 2.x+
- **GuzzleHttp:** ^6.0 or ^7.0 (HTTP client)
- **PHPUnit:** ^9.0 (testing)
- **PHP CodeSniffer:** ^3.5 (linting)

---

## Progress Tracking

- [x] Project specification drafted (MOLONI_ON_WHMCS_PROMPT.md)
- [x] Setup guide created (SETUP.md)
- [x] Architecture documented (ARCHITECTURE.md)
- [x] Initial journal started (this file)
- [ ] Composer.json initialized
- [ ] Database schemas created
- [ ] API client implemented
- [ ] Login flow working
- [ ] Orders syncing
- [ ] Documents page functional
- [ ] Tests written
- [ ] Code review complete
- [ ] Ready for testing
- [ ] Ready for production

---

## Next Steps

1. **Review & Confirm** the specification and architecture with team
2. **Set up development environment** (PHP, WHMCS, MySQL)
3. **Initialize composer.json** with dependencies
4. **Create database tables** for configuration, orders, logs
5. **Implement ApiClient** for Moloni ON GraphQL
6. **Build login/auth flow** - first user-facing feature
7. **Implement order sync** - core functionality
8. **Add error handling & logging** throughout
9. **Polish UI/UX** and add i18n
10. **Write comprehensive tests**
11. **Deploy to staging** and validate

---

## Communication

- **Issues/Questions:** Document in journal before implementing
- **Code Reviews:** All PRs require at least 1 review
- **Testing:** Test each feature before moving on
- **Documentation:** Keep README.md and ARCHITECTURE.md updated
- **Logging:** Use LogService for all important actions

---

## Glossary

- **API Key:** Authentication credential for Moloni ON
- **Company:** User's Moloni ON company context
- **Document:** Created invoice/proforma/receipt in Moloni ON
- **Order:** WHMCS order to be synced
- **GraphQL:** API query language used by Moloni ON
- **Bulk:** Creating multiple documents at once
- **Discard:** Marking an order to never sync

---

## Lessons Learned (will update)

*To be populated as project progresses*

---

**Status:** 🟡 Planning → In Progress  
**Last Updated:** 2026-07-02 14:30 UTC  
**Next Review:** After Phase 1 Complete

---

### Attachments
- MOLONI_ON_WHMCS_PROMPT.md - Full project specification
- SETUP.md - Installation and setup guide
- ARCHITECTURE.md - System design and technical details
