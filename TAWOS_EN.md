# TAWOS Data Management & Operations Guide (TAIPO)

This document provides a comprehensive guide to the **TAWOS (Tawosi Agile Web-hosted Open-Source Issues)** dataset in TAIPO, explaining the role of labels/categories, how the helper CLI tools work, and the exact **operational sequence** both before and after starting the server.

---

## Table of Contents

1. [The Role of TAWOS in TAIPO](#1-the-role-of-tawos-in-taipo)
2. [Labels and Dimensions in TAWOS](#2-labels-and-dimensions-in-tawos)
3. [Tooling Overview](#3-tooling-overview)
   - [tawos_list_labels.php](#tawos_list_labelsphp)
   - [tawos_seed_manager.php](#tawos_seed_managerphp)
4. [Execution Workflows & Sequence](#4-execution-workflows--sequence)
   - [Scenario A: Preparation BEFORE Server Startup (Offline / Pre-boot)](#scenario-a-preparation-before-server-startup-offline--pre-boot)
   - [Scenario B: Reseeding with RUNNING Server/Database (Live Reseeding)](#scenario-b-reseeding-with-running-serverdatabase-live-reseeding)
5. [Quota Exceeds Warning & Decision Logic (Y/N)](#5-quota-exceeds-warning--decision-logic-yn)
6. [Command Reference & Examples](#6-command-reference--examples)

---

## 1. The Role of TAWOS in TAIPO

TAIPO's AI-driven Product Owner (PO) simulation relies on real-world agile engineering patterns:

- **Tone Calibration:** `Prompts::getPoCheckInPrompt()` incorporates real-world developer comments from TAWOS (`TawosService::getRandomComment()`) to calibrate the Gemini PO assistant to a concise, industrial Jira/GitHub tone.
- **Change Request Patterns:** `Prompts::getChangeRequestPrompt()` references real agile stories and bug templates to formulate realistic sprint change requests.
- **Default Seed File:** `backend/data/tawos_seed.csv`
- **Database Table:** `tawos_issues` (or prefixed, e.g., `taipo_tawos_issues`)

---

## 2. Labels and Dimensions in TAWOS

In the TAWOS dataset, labels and categories are structured across 5 primary dimensions:

| Dimension            | Field Name     | Description & Values                                                                                                                                                                 |
| :------------------- | :------------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Issue Type**       | `type`         | Issue category: `Story` (feature request/user story), `Bug` (defect), `Task` (technical task). *(Full 500k+ dataset also includes: Improvement, New Feature, Sub-task, Epic, Wish).* |
| **Priority**         | `priority`     | Urgency tier: `Critical`, `Major`, `Minor`. *(Full dataset also includes: Blocker, Critical, Major, Minor, Trivial).*                                                                |
| **Status**           | `status`       | Workflow state: `Closed`, `Open`, `In Progress`.                                                                                                                                     |
| **Resolution**       | `resolution`   | Outcome: `Done`, `Fixed`, `Unresolved`.                                                                                                                                              |
| **Project / Module** | `project_name` | Originating project/subsystem: e.g., `AuthService`, `APIGateway`, `WebPortal`, `DevOps`, `DataPlatform`, `SecurityOps`, etc.                                                         |

---

## 3. Tooling Overview

### `tawos_list_labels.php`

Inspects and visualizes dataset labels, distributions, and percentages via formatted ANSI tables or structured JSON.

- **Sources:** Reads directly from CSV files or from the active database (`--db`).
- **Path:** `tools/tawos_list_labels.php`

### `tawos_seed_manager.php`

Configures, generates, and seeds TAWOS records into CSV and/or the database:

- **Configurable count:** Generate any volume (e.g. 80, 500, 1000).
- **Label focus / quotas:** Set specific counts or percentage ratios per issue type and priority.
- **Automatic backup:** Creates a `.bak` copy of the previous seed before overwriting.
- **Database synchronization:** Directly empties and repopulates the `tawos_issues` table.
- **Path:** `tools/tawos_seed_manager.php`

---

## 4. Execution Workflows & Sequence

### Scenario A: Preparation BEFORE Server Startup (Offline / Pre-boot)

> **When to use:** When you want to prepare or adjust the seed dataset (e.g., expanding from 80 to 500 items, focusing on Bugs) before starting Docker containers or the backend server.

```mermaid
flowchart TD
    A["1. Inspect Available Labels\n(tools/tawos_list_labels.php)"] --> B["2. Generate New Seed CSV\n(tools/tawos_seed_manager.php --count=500 ...)"]
    B --> C["backend/data/tawos_seed.csv updated\n(.bak created)"]
    C --> D["3. Start Server / Containers\n(docker compose up or php -S ...)"]
    D --> E["Application::boot() runs"]
    E --> F{"tawos_issues table empty?"}
    F -- Yes --> G["TawosService::autoSeed()\nAutomatically loads CSV into database"]
    F -- No --> H["Keeps existing database contents"]
```

#### Step-by-Step Instructions: A

1. **Inspect existing labels and proportions:**

   ```bash
   php tools/tawos_list_labels.php
   ```

2. **Generate the desired record count and label distribution:**
   *(Run without `--db` so only the seed CSV file is generated)*

   ```bash
   # Example: Generate 500 records (250 Stories, 200 Bugs, 50 Tasks)
   php tools/tawos_seed_manager.php --count=500 --types="Story:250,Bug:200,Task:50"
   ```

3. **Verify the generated CSV file:**

   ```bash
   php tools/tawos_list_labels.php --dimension=type
   ```

4. **Start the backend server / containers:**

   ```bash
   # If using Docker:
   docker compose up -d

   # Or if using native PHP built-in server:
   cd backend && php -S 0.0.0.0:8000 router.php
   ```

5. **How auto-seeding works:**
   When the server receives its first request, `TawosService::autoSeed()` executes:
   - If `tawos_issues` is empty, it **automatically loads** the newly generated `backend/data/tawos_seed.csv`.
   - The application boots up with your customized 500-issue dataset.

---

### Scenario B: Reseeding with RUNNING Server/Database (Live Reseeding)

> **When to use:** When the server and database are already up and running, and you want to reload or adjust the dataset without restarting containers or recreating the database.

```mermaid
flowchart TD
    A["1. Inspect Current DB State\n(tools/tawos_list_labels.php --db)"] --> B["2. Generate & Directly Reseed DB\n(tools/tawos_seed_manager.php --count=500 --db)"]
    B --> C["Writes tawos_seed.csv & creates .bak"]
    C --> D["TawosService::reseedFromCsv() executes"]
    D --> E["Clears tawos_issues table (DELETE)"]
    E --> F["Inserts new records (INSERT transaction)"]
    F --> G["3. Verify Live Database\n(tools/tawos_list_labels.php --db)"]
```

#### Step-by-Step Instructions: B

1. **Check live database state:**

   ```bash
   php tools/tawos_list_labels.php --db
   ```

2. **Generate and directly reseed using the `--db` flag:**

   ```bash
   php tools/tawos_seed_manager.php --count=500 --types="Story:300,Bug:150,Task:50" --db
   ```

   *(In interactive mode, the script prompts: `Szeretnéd azonnal betölteni az új rekordokat a TAIPO adatbázisba? (Y/n)`, which you can confirm with `Y`).*

3. **Verify updated state in the database:**

   ```bash
   php tools/tawos_list_labels.php --db --dimension=type
   ```

---

## 5. Quota Exceeds Warning & Decision Logic (Y/N)

If the sum of specified label quotas exceeds the configured target count, the tool's safeguard is triggered.

### Example

- Configured target count: **500 items**
- Specified quotas: `--types="Story:300,Bug:250"` (Sum: **550 items**)

### Console Output

```text
───────────────────────────────────────────────────────────────────
⚠️  FIGYELMEZTETÉS: A megadott címkekvóták összege túllépi a beállított értéket!
   • Beállított kívánt összérték: 500 db
   • Címkék (kvóták) összege:     550 db (Story: 300, Bug: 250)
   • Különbözet (túllépés):        +50 db
───────────────────────────────────────────────────────────────────

Túllépi a beállított értéket (500). Mindenképpen a magasabb értékkel (550) seed-eljek? (Y/n): 
```

### Possible Actions

1. **`Y` (Yes) response:**
   - Adopts the higher value ($550$ items).
   - Generates and writes 300 Stories and 250 Bugs.

2. **`N` (No) response:**
   The tool offers proportional scaling:

   ```text
   Mit szeretnél tenni?
   Arányosan skálázzam vissza a megadott címkéket az eredeti korlátra (500 db)? (Y/n): 
   ```

   - **If `Y`:** Preserves relative proportions ($54.5\%$ Story, $45.5\%$ Bug) and scales down to exactly 500 items (273 Stories, 227 Bugs).
   - **If `N`:** Cancels execution cleanly without modifying any files.

3. **Non-interactive / CI-CD mode (`-y` or `--yes`):**
   Providing `-y` automatically confirms the higher value if a quota exceed occurs.

---

## 6. Command Reference & Examples

### A) Inspecting Labels (`tawos_list_labels.php`)

```bash
# Full breakdown from CSV
php tools/tawos_list_labels.php

# Full breakdown from database
php tools/tawos_list_labels.php --db

# Filter by a single dimension
php tools/tawos_list_labels.php --dimension=type
php tools/tawos_list_labels.php --dimension=priority
php tools/tawos_list_labels.php --dimension=status
php tools/tawos_list_labels.php --dimension=resolution
php tools/tawos_list_labels.php --dimension=project

# Inspect an external CSV file
php tools/tawos_list_labels.php --csv=/path/to/custom_tawos.csv

# Output as JSON
php tools/tawos_list_labels.php --json
```

### B) Configuring & Seeding (`tawos_seed_manager.php`)

```bash
# 1. Generate 500 records into CSV keeping natural distribution
php tools/tawos_seed_manager.php --count=500

# 2. Generate 500 records with Bug focus and seed directly into database
php tools/tawos_seed_manager.php --count=500 --types="Story:200,Bug:250,Task:50" --db

# 3. Use percentage-based distribution
php tools/tawos_seed_manager.php --count=400 --types="Story:60%,Bug:30%,Task:10%" --db

# 4. Trigger quota exceeds warning check
php tools/tawos_seed_manager.php --count=500 --types="Story:300,Bug:250"

# 5. Launch interactive wizard
php tools/tawos_seed_manager.php -i

# 6. Automated non-interactive seeding with no backup
php tools/tawos_seed_manager.php --count=1000 -y --no-backup --db
```
