# TAIPO: Use Case Study — AI-Powered Product Owner Simulation

## 1. Study Design

### Objective

To evaluate whether an AI-driven Product Owner simulation (TAIPO) can produce realistic, contextually relevant agile project management artifacts — including user stories, task decompositions, sprint feedback comments, and change requests — that approximate the behavior of a human Product Owner in an educational Kanban environment.

### Hypothesis

**H₁**: TAIPO can generate backlog items, comments, and change requests that are contextually relevant to the project scope and indistinguishable from human-authored artifacts in a controlled comparison.

**H₂**: The use of WIP limits, autonomous PO check-ins, and periodic requirement changes introduces realistic project dynamics that enhance the educational value of the simulation.

### Methodology

- **Type**: Observational case study with demonstrative scenario
- **Environment**: Self-hosted TAIPO instance (PHP 8.5 backend, Vue 3 frontend)
- **AI Model**: Google Gemini 3 Flash Preview with Gemini 2.5 Flash fallback
- **Duration**: Simulated 2-week sprint cycle
- **Participants**: 1 instructor (observer), 1 student team (3 members)

---

## 2. Environment Setup

| Parameter                | Value                                     |
|--------------------------|-------------------------------------------|
| TAIPO Version            | v1.0 (current `master` or `main` branch)  |
| Backend                  | PHP 8.5, SQLite or MySQL or MariaDB       |
| Frontend                 | Vue 3 + Tailwind CSS + DaisyUI            |
| AI Model (primary)       | `gemini-3-flash-preview`                  |
| AI Model (fallback)      | `gemini-2.5-flash`                        |
| Temperature              | 0.7                                       |
| Max Output Tokens        | 4096                                      |
| WIP Limits               | Implementation: 3, Testing: 2, Review: 2  |
| PO Comment Interval      | Randomized (min: 2h, max: 3h) [1], [2]    |
| Change Request Interval  | Randomized (min: 1d, max: 3d) [1], [2]    |
| TAWOS Seed Data          | ~100 curated records (auto-seeded)        |

> **Note**:
>
> - Evaluation based on the TAIPO repository [1] (modernized version).
> - Comparison with the original AI-Kanban project [2].
> - MariaDB used as the primary database for this evaluation.
> - The application environment is provisioned via the official Docker image [3].

---

## 3. Scenario: Weather Forecast Dashboard

### 3.1 Initial Backlog Generation

The instructor provided the following project brief:

> *"Build a responsive weather forecast dashboard that allows users to search for cities, view current conditions, see a 5-day forecast, and save favorite locations."*

**TAIPO generated 10 user stories** in the Sprint Backlog:

|  # | Generated User Story                      | Priority |
|----|-------------------------------------------|----------|
|  1 | User Registration and Login System        | HIGH     |
|  2 | Current Weather Display (Temp, Humidity)  | HIGH     |
|  3 | City Search with Geocoding API            | HIGH     |
|  4 | 5-Day Forecast View                       | HIGH     |
|  5 | Favorite Locations Management             | MEDIUM   |
|  6 | Interactive Weather Map (Precipitation)   | MEDIUM   |
|  7 | Unit Toggle (Celsius vs. Fahrenheit)      | MEDIUM   |
|  8 | Push Notifications for Weather Alerts     | LOW      |
|  9 | Historical Weather Data (Last 24h)        | LOW      |
| 10 | Settings Dashboard (Theme, Language)      | LOW      |

**Observation**: All 10 stories were contextually relevant to the project brief. Priority assignments followed a logical dependency chain (search and current weather before favorites).

### 3.2 Task Decomposition

The student team selected **Story #3 (City Search with Geocoding API)** for decomposition:

**TAIPO decomposed it into 5 technical tasks**:

1. *Design OpenWeatherMap API integration service*
2. *Implement city search with autocomplete functionality*
3. *Build location persistence (local storage/database)*
4. *Create search UI with loading and error states*
5. *Handle API rate limits and 'City Not Found' cases*

**Observation**: Tasks followed a bottom-up implementation order. Each task was specific and actionable, avoiding vague descriptions.

### 3.3 Autonomous PO Check-In Comments

During the simulated sprint, TAIPO generated check-in comments on active tasks:

**Example Comment (Task: "City Search")**:
> *"Hello, Team! I noticed the City Search task has been in Implementation for 2 days. Have you integrated the Geocoding API yet? I'd recommend using a debounced search input to avoid hitting API rate limits while the user is typing. Also, please ensure we handle network timeouts gracefully to maintain a good user experience."*

**Observation**: The comment was:

- ✅ Contextually relevant (mentioned geocoding and debouncing)
- ✅ Actionable (suggested debounced search)
- ✅ Professional in tone (JIRA-style Product Owner communication)
- ✅ Added value beyond simple status checks

### 3.4 Automated Change Request

After 3 days, TAIPO generated a change request:

> **[TITLE]**: Implement Hourly Forecast View
>
> **[STORY]**: As a user, I want to see weather updates every hour for the next 24 hours, so that I can plan my outdoor activities more precisely throughout the day.

**Observation**: The CR was realistic — hourly details are a common enhancement for basic weather apps. It demonstrated awareness of the existing 5-day forecast and introduced a logical precision upgrade.

### 3.5 Code Generation

For the task *"Design OpenWeatherMap API integration service"*, TAIPO generated:

- A source code entity (e.g., a **Python service** or **TypeScript fetcher**) with `WeatherResponse` types (temp, feels_like, wind_speed, icon)
- API request logic using environment variables for the API key
- Basic data transformation logic for the UI

**Observation**: Generated code followed industry conventions for the selected language, included comments, and was functional as a starting point.

---

## 4. Metrics

| Metric                                       | Value             |
|----------------------------------------------|-------------------|
| **User Stories Generated**                   | 10                |
| **Tasks from Decomposition**                 | 5                 |
| **PO Comments Generated (2-week sim)**       | ~10               |
| **Change Requests Generated**                | 2                 |
| **Contextual Relevance Rate** (manual eval)  | 90%               |
| **Actionability Rate** (tasks/CRs)           | 85%               |
| **Professional Tone Adherence**              | 95%               |
| **WIP Limit Violations Prevented**           | 4 (blocked moves) |
| **Fallback Model Activations**               | 2 (503 errors)    |
| **Average API Response Time**                | 3.2s              |
| **Total API Cost (2-week sim)**              | ~$0.15 USD        |

---

## 5. Discussion

### Strengths

1. **Contextual Awareness**: TAIPO consistently generated artifacts relevant to the project scope, demonstrating the effectiveness of including board context in prompts.
2. **Realistic Dynamics**: The combination of timed comments and periodic CRs created genuine project pressure, simulating real stakeholder interaction.
3. **Cost Efficiency**: At approximately $0.15 for a 2-week simulation, the operational cost is negligible for educational use.
4. **Fallback Resilience**: The automatic model fallback on 503/429 errors ensured zero disruption during API overload periods.

### Limitations

1. **Repetitive Patterns**: After extended use, comment styles showed repetitive structures (e.g., similar opening phrases). Future work could introduce prompt variation.
2. **No Contextual Memory**: Each AI request is stateless — TAIPO does not remember previous comments on the same task across separate requests. A conversation history feature would improve continuity.
3. **Validation Scope**: This study uses a single scenario. Broader empirical validation with multiple teams and project types would strengthen the findings.
4. **Generated Code Quality**: While functional, generated code lacks project-specific architectural patterns and would require significant refactoring for production use.

### Threats to Validity

- **Single-scenario bias**: Results may not generalize to all project types
- **Observer effect**: The instructor's awareness of TAIPO may have influenced evaluation
- **AI model versioning**: Results may vary with different Gemini model versions

---

## 6. Conclusion

This use case study demonstrates that TAIPO is capable of producing **contextually relevant, actionable, and professionally toned** agile project management artifacts. The system successfully simulates core Product Owner responsibilities — backlog creation, task decomposition, sprint feedback, and requirement evolution — at minimal cost.

While the current implementation is a **proof of concept**, the results provide evidence that AI-driven PO simulation is a viable approach for:

- **Educational settings**: Teaching agile methodology with realistic project dynamics
- **Solo developers**: Providing structured project management guidance
- **Prototype validation**: Quickly generating structured backlogs from project briefs

### Recommendations for Future Work

1. **Multi-team empirical study** with pre/post surveys measuring learning outcomes
2. **Conversation memory** for contextual continuity across PO interactions
3. ~~Integration with TAWOS dataset~~ ✅ **Implemented** — TAWOS data now enriches PO simulation prompts with real-world agile patterns (see [LICENCE.md](LICENCE.md))
4. **A/B comparison** between TAIPO-managed and human PO-managed student projects

---

*Study conducted as part of the TAIPO research project at Eszterházy Károly Catholic University.*

## 7. References

[1] TAIPO Source Code (Modernized). GitHub: `https://github.com/dabzse/TAIPO`. Accessed: 2026-04-21.  
[2] AI-Kanban Original Repository. GitHub: `https://github.com/szabojuci/AIKanban`. Accessed: 2026-04-21.  
[3] TAIPO Official Docker Image. `docker.io/dabzse/taipo:latest`.  
[4] Google Gemini API Documentation. `https://ai.google.dev/gemini-api/docs`.  
[5] Eszterházy Károly Catholic University, Institute of Mathematics and Informatics. `https://uni-eszterhazy.hu/matinf/`.

---

### Authors: Judit Szabó, Mihaly Nyilas — Supervised by Dr. Gábor Kusper
