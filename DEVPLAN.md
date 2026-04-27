# TAIPO Development Roadmap

This plan tracks the step-by-step progress of **T**he **AI**-based **P**roduct **O**wner assistant.

## EPICS

[X] Epic 1: Core Kanban Board Infrastructure  
[/] Epic 2: AI-Powered Product Owner Assistant  
[/] Epic 3: Project Simulation Capabilities  
[X] Epic 4: Backend and Integration  
[/] Epic 5: Quality and Safety Features  
[ ] Epic 6: Educational Use Cases  
[/] Epic 7: Technical Debt and Infrastructure  

---

## [X] Story 1.1: Basic Kanban Board Interface (Priority: HIGH)

**As a** developer  
**I want** a Kanban-style user interface with workflow stages  
**So that** I can visualize and manage project tasks in a structured way

**Acceptance Criteria:**

[X] Kanban board displays multiple workflow stages (e.g., Backlog, To Do, In Progress, Done)  
[X] Cards can be moved between stages via drag-and-drop or similar interaction  
[X] Board state persists between sessions  
[X] UI is responsive and user-friendly  

**Story Points:** 8

## [X] Story 1.2: Kanban Card Management (Priority: HIGH)

**As a** developer  
**I want** to create, view, edit, and delete Kanban cards  
**So that** I can manage individual work items on the board

**Acceptance Criteria:**

[X] Users can create new cards with title and description  
[X] Cards display essential information (title, priority, status)  
[X] Cards can be edited after creatio  
[X] Cards can be deleted when no longer neede  
[X] Changes are persisted to the backend  

**Story Points:** 5

## [X] Story 2.1: TAIPO Query Interface (Priority: HIGH)

**As a** developer  
**I want** to query TAIPO with prompt-based questions about specific cards  
**So that** I can get AI-generated clarifications attached to relevant work items

**Acceptance Criteria:**

[X] Developers can submit natural language queries related to a card  
[X] TAIPO generates contextually relevant responses  
[X] Responses are persistently attached to the queried card  
[X] Query history is visible on each card  
[X] Integration with Gemini API is functional  

**Story Points:** 13

## [X] Story 2.2: Automated Backlog Population (Priority: HIGH)

**As a** product owner  
**I want** TAIPO to automatically generate prioritized user stories from requirement documents  
**So that** I can quickly populate the product backlog without manual effort

**Acceptance Criteria:**

[X] System accepts requirement documentation as input (text format)  
[X] TAIPO generates user stories following standard format  
[X] Generated stories include priority assignments  
[X] Stories are added to the Product Backlog automatically  
[X] Quality and relevance of generated stories is acceptable  

**Story Points:** 13

## [X] Story 2.3: Story-to-Task Decomposition (Priority: MEDIUM)

**As a** developer  
**I want** TAIPO to decompose user stories into concrete tasks  
**So that** complex stories become actionable work items

**Acceptance Criteria:**

[X] TAIPO can analyze a user story and identify subtasks  
[X] Generated tasks are specific and actionable  
[X] Tasks are linked to their parent user story  
[X] Tasks maintain traceability to original requirements  
[X] Developers can review and modify generated tasks  

**Story Points:** 8

## [ ] Story 2.4: Card Description Enhancement (Priority: MEDIUM)

**As a** product owner  
**I want** TAIPO to edit and enhance card descriptions  
**So that** requirements are clearer and more complete

**Acceptance Criteria:**

[ ] TAIPO can analyze existing card descriptions  
[ ] System suggests improvements or additions  
[ ] Enhanced descriptions maintain original intent  
[ ] Changes are tracked and reversible  
[ ] Users can accept or reject suggestions  

**Story Points:** 5

## [ ] Story 2.5: Priority Management (Priority: MEDIUM)

**As a** product owner  
**I want** TAIPO to update card priorities based on context  
**So that** the backlog reflects current project needs

**Acceptance Criteria:**

[ ] TAIPO can analyze project context and suggest priority changes  
[ ] Priority updates consider dependencies and business value  
[ ] Changes include rationale/explanation  
[ ] Users can override AI-suggested priorities  
[ ] Priority history is tracked  

**Story Points:** 5

## [X] Story 3.1: Automated Comment Generation (Priority: MEDIUM)

**As an** instructor  
**I want** TAIPO to automatically add comments to cards at configured intervals  
**So that** student teams receive continuous feedback simulating real PO activity

**Acceptance Criteria:**

[X] TAIPO generates comments every 2 hours during working hours (8AM-4PM, weekdays)  
[X] Comments use TAWOS database and current board context  
[X] Comments are contextually relevant to card content  
[X] Comment frequency is configurable  
[X] Generated comments simulate realistic PO feedback  

**Story Points:** 8

## [X] Story 3.2: Simulated Requirement Changes (Priority: MEDIUM)

**As an** instructor  
**I want** TAIPO to generate new requirements and change requests periodically  
**So that** students experience realistic project dynamics and requirement evolution

**Acceptance Criteria:**

[X] TAIPO generates new requirements/stories/CRs approximately every 3 days  
[X] Generated changes are based on TAWOS data patterns  
[X] Changes simulate realistic project scenarios  
[X] Frequency is configurable (.env only, plan: dashboard)
[X] New items are added to appropriate board sections  

**Story Points:** 8

## [/] Story 3.3: Multi-Team Support (Priority: LOW)

**As an** instructor  
**I want** to manage multiple student teams with separate TAIPO instances  
**So that** each team has independent project simulation

**Acceptance Criteria:**

[X] System supports multiple concurrent projects/boards  
[X] Each team has isolated data and AI context  
[ ] Instructor can monitor all teams from central dashboard  
[ ] Simulation parameters can be set per team  
[ ] Performance remains acceptable with multiple teams  

**Story Points:** 13

## [X] Story 4.1: PHP Backend Infrastructure (Priority: HIGH)

**As a** developer  
**I want** a robust PHP-based backend  
**So that** the system can handle data persistence and API integrations

**Acceptance Criteria:**

[X] Backend handles all CRUD operations for cards and boards  
[X] RESTful API endpoints are well-documented  
[X] Database schema supports all required entities  
[X] Error handling and logging are implemented  
[X] Security best practices are followed  

**Story Points:** 13

## [X] Story 4.2: Gemini API Integration (Priority: HIGH)

**As a** system  
**I want** reliable integration with the Gemini API  
**So that** AI capabilities function consistently

**Acceptance Criteria:**

[X] API key management is secure  
[X] Requests are properly formatted for Gemini API  
[X] Responses are parsed and validated  
[X] Error handling for API failures is implemented  
[X] Rate limiting is respected  
[X] API costs are monitored  

**Story Points:** 8

## [X] Story 4.3: TAWOS Dataset Integration (Priority: MEDIUM)

**As a** system  
**I want** to access and utilize the cleaned TAWOS dataset  
**So that** simulations are based on realistic agile project data

**Acceptance Criteria:**

[X] TAWOS data is imported and accessible  
[X] Query mechanisms retrieve relevant issue records  
[X] Metadata (comments, change logs, story points) is preserved  
[X] Data quality is validated  
[X] Dataset updates can be incorporated  

**Story Points:** 8

## [/] Story 5.1: Traceability and Audit Trail (Priority: MEDIUM)

**As a** product owner  
**I want** complete traceability of requirement evolution  
**So that** I can track how decisions and clarifications emerged over time

**Acceptance Criteria:**

[ ] All AI-generated content is timestamped and attributed  
[ ] Change history for cards is maintained  
[X] Queries and responses are linked to cards permanently  
[ ] Audit log is searchable and exportable  
[X] Original requirements remain accessible  

**Story Points:** 5

## [ ] Story 5.2: Acceptance Decision Support (Priority: MEDIUM)

**As a** product owner  
**I want** TAIPO to provide AI-generated feedback on acceptance decisions  
**So that** I can make informed decisions about completed work

**Acceptance Criteria:**

[ ] TAIPO can analyze completed work against acceptance criteria  
[ ] System generates feedback on acceptance/rejection  
[ ] Rejected items receive specific improvement suggestions  
[ ] Items can be returned to backlog with annotations  
[ ] Decision rationale is documented  

**Story Points:** 8

## [X] Story 5.3: External Modification Resilience (Priority: MEDIUM)

**As a** developer  
**I want** TAIPO to handle manual changes to cards gracefully  
**So that** human edits don't break AI context or reasoning

**Acceptance Criteria:**

[X] TAIPO incorporates manual edits into subsequent operations  
[X] No errors occur when cards are modified externally  
[X] AI context adapts to human changes  
[X] Conflicting edits are detected and handled  
[X] System remains stable with mixed human/AI authorship  

**Story Points:** 5

## [X] Story 6.1: Demonstration Project Setup (Priority: LOW)

**As an** instructor  
**I want** a pre-configured weather forecast webpage project  
**So that** I can demonstrate TAIPO capabilities to students

**Acceptance Criteria:**

[X] Sample project includes map with zoom functionality  
[X] Time slider component is specified  
[X] Realistic requirement changes are pre-loaded  
[X] Project demonstrates key TAIPO features  
[X] Documentation explains the demo scenario  

**Story Points:** 5

## [/] Story 6.2: Code Generation Support (Priority: LOW)

**As a** developer  
**I want** TAIPO to optionally generate code based on board context  
**So that** I can accelerate implementation (while understanding this is secondary)

**Acceptance Criteria:**

[X] Code generation considers card context and requirements  
[X] Generated code follows best practices  
[X] Code is provided as suggestions, not auto-committed  
[ ] Multiple implementation approaches may be offered  
[X] Clear disclaimer that this is not primary use case  

**Story Points:** 8

## [ ] Story 6.3: Instructor Dashboard (Priority: LOW)

**As an** instructor  
**I want** visibility into all student team activities  
**So that** I can identify teams needing support

**Acceptance Criteria:**

[ ] Dashboard shows status of all active projects  
[ ] Key metrics are visible (velocity, completion rates)  
[ ] Instructor can view any team's board  
[ ] Alerts flag teams with blocked or stalled work  
[ ] Export functionality for assessment purposes  

**Story Points:** 8

## [/] Story 7.1: Deployment and Hosting (Priority: MEDIUM)

**As a** system administrator  
**I want** clear deployment instructions and hosting setup  
**So that** TAIPO can be reliably deployed and accessed

**Acceptance Criteria:**

[X] Deployment documentation is complete  
[X] System requirements are specified  
[X] Installation process is automated where possible  
[X] Public URL is stable and secure  
[ ] Backup and recovery procedures exist  

**Story Points:** 5

## [X] Story 7.2: Security and Privacy (Priority: HIGH)

**As a** system administrator  
**I want** security best practices implemented  
**So that** user data and API credentials are protected

**Acceptance Criteria:**

[X] Authentication and authorization are implemented  
[X] API keys are stored securely (environment variables/secrets)  
[X] Input validation prevents injection attacks  
[X] HTTPS is enforced  
[X] Data privacy regulations are considered  

**Story Points:** 8

---

## Release Planning

### [X] Release 1.0 (MVP - Core Functionality)

[X] Epic 1: Core Kanban Board Infrastructure  
[X] Epic 2: Stories 2.1, 2.2, 2.3 (AI-Powered PO Assistant basics)  
[X] Epic 4: Stories 4.1, 4.2 (Backend & API)  
[X] Epic 5: Story 5.3 (External Modification Resilience)  
[X] Security implementation (Story 7.2)  

### Release 2.0 (Educational Features)

[/] Epic 3: Project Simulation Capabilities  
[ ] Epic 2: Stories 2.4, 2.5 (Enhanced AI features)  
[/] Epic 5: Stories 5.1, 5.2 (Quality features)  
[X] Epic 4: Story 4.3 (TAWOS integration)  

### Release 3.0 (Scale and Polish)

[/] Epic 6: Educational Use Cases  
[/] Epic 3: Story 3.3 (Multi-team support)  
[/] Deployment optimization (Story 7.1)  

**Approximately 74%** of the whole project is done.
