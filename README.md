# Moloni ON WHMCS - Documentation Index

Welcome to the Moloni ON WHMCS addon project! This index guides you through all project documentation.

---

## 📚 Quick Navigation

### For Project Managers & Stakeholders
1. **[MOLONI_ON_WHMCS_PROMPT.md](./MOLONI_ON_WHMCS_PROMPT.md)** - Complete project specification
   - Overview, requirements, acceptance criteria
   - Timeline and deliverables
   - Risks and questions

2. **[.claude/journal/001_PROJECT_KICKOFF.md](./.claude/journal/001_PROJECT_KICKOFF.md)** - Project kickoff summary
   - Key decisions made
   - Project scope
   - Development phases

### For Developers
1. **[SETUP.md](./SETUP.md)** - Installation & setup guide
   - Prerequisites
   - Step-by-step installation
   - Environment configuration
   - Troubleshooting

2. **[ARCHITECTURE.md](./ARCHITECTURE.md)** - System design
   - Layered architecture overview
   - Component descriptions
   - Data flow diagrams
   - API integration patterns
   - Error handling strategy

3. **[.claude/PROJECT_PLAN.md](./.claude/PROJECT_PLAN.md)** - Implementation checklist
   - Detailed feature breakdown by phase
   - Acceptance criteria for each phase
   - Testing strategy
   - Daily development workflow

4. **[.claude/journal/](./claude/journal/)** - Development journal
   - Progress notes
   - Decision logs
   - Technical insights

---

## 📋 Document Descriptions

### MOLONI_ON_WHMCS_PROMPT.md (11,000+ words)
**Purpose:** Complete project specification  
**Audience:** PM, developers, stakeholders  
**Contains:**
- Project overview and goals
- Technical requirements (PHP, WHMCS, dependencies)
- Feature specifications for each UI page
- API & data integration details
- UI/UX guidelines
- Internationalization approach
- Testing & quality standards
- Acceptance criteria
- Deployment strategy
- Questions to resolve

**Read this when:** Understanding project scope, requirements, acceptance criteria

---

### SETUP.md (6,000+ words)
**Purpose:** Installation and configuration guide  
**Audience:** DevOps, developers  
**Contains:**
- Prerequisites (WHMCS, PHP, composer, etc.)
- Step-by-step setup (10 steps)
- Database table creation
- WHMCS activation
- Module configuration
- Development tool setup
- GraphQL queries directory setup
- Environment configuration
- Testing the integration
- Deployment checklist
- Troubleshooting guide

**Read this when:** Setting up development environment, deploying to production

---

### ARCHITECTURE.md (8,000+ words)
**Purpose:** System design and technical architecture  
**Audience:** Developers, architects  
**Contains:**
- System overview diagram
- Layer-by-layer architecture explanation
- Service descriptions & responsibilities
- API/client layer design
- Database schema & models
- Exception hierarchy
- Security considerations
- Performance optimization strategies
- Testing approaches
- Deployment checklist
- Future enhancements

**Read this when:** Understanding how components interact, designing new features, debugging

---

### .claude/PROJECT_PLAN.md (10,000+ words)
**Purpose:** Detailed implementation checklist and development workflow  
**Audience:** Developers, technical leads  
**Contains:**
- 11 development phases with sub-tasks
- Acceptance criteria for each phase
- Code examples and patterns
- Testing strategy (unit, integration, manual)
- UI/UX implementation details
- i18n (English & Portuguese) approach
- Code quality standards
- Documentation requirements
- Deployment process
- Daily development checklist
- Success metrics

**Read this when:** Starting development, planning sprints, checking off tasks

---

### .claude/journal/001_PROJECT_KICKOFF.md (4,000+ words)
**Purpose:** Project kickoff summary and decision log  
**Audience:** Everyone  
**Contains:**
- Project summary
- Key decisions made (with rationale)
- Project scope (in/out of scope)
- Development phases overview
- Technical decisions & rationale
- Identified risks & mitigation
- Questions for stakeholders
- Progress tracking
- Next steps
- Communication guidelines

**Read this when:** Onboarding to project, understanding key decisions

---

## 📂 Project Structure

```
moloni-on-whmcs/
│
├── README.md (you are here)
├── SETUP.md (go here to set up)
├── ARCHITECTURE.md (go here to understand design)
├── MOLONI_ON_WHMCS_PROMPT.md (go here for full spec)
│
├── .claude/
│   ├── PROJECT_PLAN.md (detailed implementation plan)
│   └── journal/
│       ├── 001_PROJECT_KICKOFF.md (project summary)
│       ├── 002_PHASE_1_COMPLETE.md (future progress notes)
│       └── ... (additional journal entries as project progresses)
│
├── src/Moloni/
│   ├── Admin/ (WHMCS routing)
│   ├── Api/ (Moloni ON client)
│   ├── GraphQL/ (queries & mutations)
│   ├── Services/ (business logic)
│   ├── Models/ (database models)
│   ├── Database/ (table schemas)
│   └── Exceptions/ (error classes)
│
├── templates/
│   ├── Blocks/ (reusable UI components)
│   ├── Modals/ (Bootstrap modals)
│   ├── login.php
│   ├── company.php
│   ├── document.php (orders list)
│   ├── documents.php (created documents)
│   ├── config.php (settings)
│   ├── tools.php (utilities)
│   └── logs.php (activity log)
│
├── public/
│   ├── css/ (stylesheets)
│   ├── js/ (JavaScript)
│   └── img/ (images & icons)
│
├── lang/
│   ├── en.php (English translations)
│   └── pt.php (Portuguese translations)
│
├── tests/
│   ├── Unit/ (unit tests)
│   ├── Feature/ (integration tests)
│   └── bootstrap.php
│
├── moloni_on.php (main WHMCS entry point)
├── hooks.php (WHMCS hooks)
├── composer.json (dependencies)
├── phpcs.xml (code standards)
└── phpunit.xml (test config)
```

---

## 🚀 Getting Started

### First Time? Follow This Order:

1. **Understand the Project**
   - Read: MOLONI_ON_WHMCS_PROMPT.md (overview section)
   - Read: .claude/journal/001_PROJECT_KICKOFF.md

2. **Understand the Design**
   - Read: ARCHITECTURE.md (system overview & layers)
   - Review: Folder structure above

3. **Set Up Development**
   - Follow: SETUP.md (step by step)
   - Run: `composer install`

4. **Start Development**
   - Open: .claude/PROJECT_PLAN.md (Phase 1 checklist)
   - Follow: Daily development checklist in PROJECT_PLAN.md

5. **Track Progress**
   - Update: .claude/journal/ with daily progress
   - Check off: Tasks in PROJECT_PLAN.md

---

## 🎯 Key Files by Role

### Project Manager
- [ ] MOLONI_ON_WHMCS_PROMPT.md - Requirements & acceptance criteria
- [ ] .claude/journal/001_PROJECT_KICKOFF.md - Scope & decisions
- [ ] .claude/PROJECT_PLAN.md - Timeline & phases

### Developer (New)
- [ ] SETUP.md - Installation
- [ ] ARCHITECTURE.md - Design overview
- [ ] .claude/PROJECT_PLAN.md - What to build (Phase 1 first)

### Developer (Experienced)
- [ ] ARCHITECTURE.md - Design patterns
- [ ] .claude/PROJECT_PLAN.md - Implementation checklist
- [ ] Code in `/src/` - Implementation details

### DevOps / System Admin
- [ ] SETUP.md - Full installation & deployment
- [ ] MOLONI_ON_WHMCS_PROMPT.md - Requirements
- [ ] ARCHITECTURE.md - System dependencies

### QA / Tester
- [ ] MOLONI_ON_WHMCS_PROMPT.md - Acceptance criteria
- [ ] .claude/PROJECT_PLAN.md - Testing section
- [ ] SETUP.md - Test environment setup

---

## 📖 How to Use These Documents

### MOLONI_ON_WHMCS_PROMPT.md
```
Use for:
  - Understanding what to build
  - Defining acceptance criteria
  - Identifying requirements
  - Planning sprints
  
Read when:
  - Clarifying requirements
  - Writing tests
  - Reviewing features
  - Planning releases

Structure:
  - Overview (5 min read)
  - Technical requirements (10 min)
  - Features breakdown (20 min)
  - Acceptance criteria (5 min)
  - Full checklist (10 min)
```

### SETUP.md
```
Use for:
  - Installing for first time
  - Setting up development environment
  - Deploying to production
  - Troubleshooting issues

Read when:
  - Need to install module
  - Setting up new dev machine
  - Going to production
  - Stuck on error

Structure:
  - Prerequisites (5 min)
  - Step-by-step (30 min)
  - Configuration (10 min)
  - Troubleshooting (as needed)
```

### ARCHITECTURE.md
```
Use for:
  - Understanding how code is organized
  - Designing new features
  - Debugging issues
  - Writing tests
  - Performance optimization

Read when:
  - Starting development
  - Need to understand a layer
  - Adding new feature
  - Investigating bug

Structure:
  - Overview diagram (5 min)
  - Layer descriptions (20 min)
  - Service details (15 min)
  - Data flows (10 min)
```

### .claude/PROJECT_PLAN.md
```
Use for:
  - Detailed implementation tasks
  - Acceptance criteria per phase
  - Daily development checklist
  - Test strategy
  - Code examples

Read when:
  - Starting a new feature
  - Need acceptance criteria
  - Planning daily work
  - Writing tests
  - Checking code quality

Structure:
  - 11 phases with checklists (60 min total)
  - Testing strategies (10 min)
  - Daily workflow (5 min)
```

### .claude/journal/001_PROJECT_KICKOFF.md
```
Use for:
  - Quick project overview
  - Key decisions made
  - Development phases
  - Risk mitigation
  - Questions for stakeholders

Read when:
  - Onboarding to project
  - Need quick context
  - Understanding decisions
  - Checking scope

Structure:
  - Summary (2 min)
  - Decisions (5 min)
  - Scope (5 min)
  - Phases (5 min)
```

---

## 💬 Communication & Decisions

### How to Add Notes
1. When making a design decision → add entry to `.claude/journal/`
2. When completing a phase → add progress note to journal
3. When discovering an issue → log in journal with solution
4. File naming: `NNN_DESCRIPTION.md` (e.g., `002_API_INTEGRATION_COMPLETE.md`)

### Example Journal Entry
```markdown
# 2026-07-15 - API Integration Complete

## What Was Done
- Implemented ApiClient class
- Created 5 core GraphQL queries
- Tested API connectivity

## Decisions Made
- Using GuzzleHttp over cURL (easier to test)
- Queries in separate files for organization

## Blockers Resolved
- API rate limiting: added exponential backoff

## Next Steps
- Start Phase 2: Authentication UI
```

---

## 🔍 Finding Information

### "How do I set up the project?"
→ **SETUP.md** (Step 1-3)

### "What are the acceptance criteria?"
→ **MOLONI_ON_WHMCS_PROMPT.md** (Acceptance Criteria section)

### "How do I create a feature?"
→ **ARCHITECTURE.md** (relevant layer) + **.claude/PROJECT_PLAN.md** (implementation details)

### "What's the database schema?"
→ **ARCHITECTURE.md** (Model/Database Layer section)

### "What are the API integrations?"
→ **ARCHITECTURE.md** (API/Client Layer) + **MOLONI_ON_WHMCS_PROMPT.md** (API & Data Integration)

### "How do I test this?"
→ **.claude/PROJECT_PLAN.md** (Phase 9: Testing section)

### "What are the key decisions?"
→ **.claude/journal/001_PROJECT_KICKOFF.md** (Key Decisions Made)

### "What should I do today?"
→ **.claude/PROJECT_PLAN.md** (Daily Development Checklist)

### "What phase are we in?"
→ **.claude/journal/001_PROJECT_KICKOFF.md** (Progress Tracking)

---

## ✅ Checklist: Before You Start

- [ ] Read MOLONI_ON_WHMCS_PROMPT.md (overview section)
- [ ] Read .claude/journal/001_PROJECT_KICKOFF.md
- [ ] Read ARCHITECTURE.md (overview section)
- [ ] Read SETUP.md (prereq section)
- [ ] Follow SETUP.md installation steps
- [ ] Run `composer install`
- [ ] Create database tables
- [ ] Test API connection
- [ ] Review .claude/PROJECT_PLAN.md Phase 1

---

## 📞 Support & Questions

### Questions About Requirements?
→ See **MOLONI_ON_WHMCS_PROMPT.md** (Acceptance Criteria section)

### Stuck on Implementation?
→ See **ARCHITECTURE.md** (relevant layer) + ask in team chat

### Need to Know Why a Decision Was Made?
→ See **.claude/journal/** (search for topic)

### How to Report Issues?
→ Add to `.claude/journal/` with problem & proposed solution

---

## 📊 Version History

| Version | Date | Status | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-07-02 | ✅ Complete | Initial project documentation |
| - | TBD | 🟡 In Progress | Phase 1 development |
| - | TBD | ⏳ Upcoming | Phase 2-11 |

---

## 🎓 Learning Resources

### PHP/PSR-12
- https://www.php-fig.org/psr/psr-12/ (PSR-12 standard)

### WHMCS Development
- https://docs.whmcs.com/Addon_Modules (WHMCS addon guide)
- https://docs.whmcs.com/Database_Tables (WHMCS DB schema)

### GraphQL
- https://docs.molonion.pt/ (Moloni ON API docs)
- https://graphql.org/learn/ (GraphQL intro)

### Testing
- https://phpunit.de/ (PHPUnit docs)
- https://phpunit.readthedocs.io/ (PHPUnit guide)

### Code Standards
- https://www.php-fig.org/psr/psr-12/ (PSR-12)
- https://github.com/squizlabs/PHP_CodeSniffer (CodeSniffer)

---

**Last Updated:** July 2, 2026  
**Status:** 🟡 Kickoff Phase Complete  
**Next:** Phase 1 Development  

---

**Questions?** Check the documentation above or add a note to `.claude/journal/`
