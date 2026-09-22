# TAWOS adatkezelési és működési útmutató (TAIPO)

Ez a dokumentum részletesen bemutatja a **TAWOS (Tawosi Agile Web-hosted Open-Source Issues)** adathalmaz szerepét a TAIPO rendszerben, a rendelkezésre álló címkéket (labels), a segédeszközök működését, valamint a pontos **végrehajtási sorrendet** a szerver indítása előtt és után.

---

## Tartalomjegyzék

1. [A TAWOS szerepe a TAIPO rendszerben](#1-a-tawos-szerepe-a-taipo-rendszerben)
2. [Címkék és dimenziók a TAWOS adatkészletben](#2-címkék-és-dimenziók-a-tawos-adatkészletben)
3. [Eszközök áttekintése](#3-eszközök-áttekintése)
   - [tawos_list_labels.php](#tawos_list_labelsphp)
   - [tawos_seed_manager.php](#tawos_seed_managerphp)
   - [tawos_sql_sampler.php (Valódi SQL dump mintavételező)](#tawos_sql_samplerphp-valódi-sql-dump-mintavételező)
   - [setup.php és setup.sh](#setupphp-és-setupsh)
4. [Működési forgatókönyvek és sorrend](#4-működési-forgatókönyvek-és-sorrend)
   - [A) forgatókönyv: Előkészítés a szerver indítása ELŐTT (Offline / Pre-boot)](#a-forgatókönyv-előkészítés-a-szerver-indítása-előtt-offline--pre-boot)
   - [B) forgatókönyv: Frissítés futó szerver / adatbázis MELLETT (Live Reseeding)](#b-forgatókönyv-frissítés-futó-szerver--adatbázis-mellett-live-reseeding)
   - [C) forgatókönyv: Valódi adathalmaz mintavételezése nyers TAWOS SQL dumpból (Zero-Decompression Streaming)](#c-forgatókönyv-valódi-adathalmaz-mintavételezése-nyers-tawos-sql-dumpból-zero-decompression-streaming)
5. [Túllépési figyelmeztetés és döntési logika (Y/N)](#5-túllépési-figyelmeztetés-és-döntési-logika-yn)
6. [Helyi adatbázis és offline működés (Offline-First Architecture)](#6-helyi-adatbázis-és-offline-működés-offline-first-architecture)
7. [Parancsreferencia és példák](#7-parancsreferencia-és-példák)

---

## 1. A TAWOS szerepe a TAIPO rendszerben

A TAIPO mesterséges intelligencia által vezérelt Product Owner (PO) szimulációja valós ipari agilis mintákra épül:

- **Tone Calibration (Hangnem kalibrálás):** A `Prompts::getPoCheckInPrompt()` valós TAWOS kommenteket használ fel (`TawosService::getRandomComment()`), hogy a Gemini PO modell professzionális, ipari Jira/GitHub stílusban adjon visszajelzést a fejlesztőknek.
- **Change Request minták:** A `Prompts::getChangeRequestPrompt()` valós felhasználói sztorik és hiba leírások mintázatát használja fel az életszerű módosítási kérések (Change Request) generálásához.
- **Beépített seed fájlok:**
  - `backend/data/tawos_seed.csv` (80 rekord - kompakt alapértelmezett készlet, első indításkor automatikusan betöltődik)
  - `backend/data/tawos_seed_350.csv` (350 rekord - beépített kibővített készlet 249 sztorival, 74 hibával és 27 feladattal 14 projektből, azonnal használható a 4GB SQL dump nélkül)
- **Adatbázistábla:** `tawos_issues` (vagy táblaprefixszel, pl. `taipo_tawos_issues`)

---

## 2. Címkék és dimenziók a TAWOS adatkészletben

A TAWOS adathalmazban a címkék 5 fő dimenzióra tagolódnak:

| Dimenzió            | Mezőnév        | Leírás és lehetséges értékek                                                                                                                                                   |
| :------------------ | :------------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Issue Típus**     | `type`         | A feladat jellege: `Story` (funkció/sztori), `Bug` (hiba), `Task` (technikai feladat). *(A teljes 500k+ TAWOS-ban továbbiak: Improvement, New Feature, Sub-task, Epic, Wish).* |
| **Prioritás**       | `priority`     | Sürgősségi szint: `Critical`, `Major`, `Minor`. *(A teljes adathalmazban: Blocker, Critical, Major, Minor, Trivial).*                                                          |
| **Státusz**         | `status`       | Munkafolyamat állapot: `Closed`, `Open`, `In Progress`.                                                                                                                        |
| **Megoldás**        | `resolution`   | Lezárás eredménye: `Done`, `Fixed`, `Unresolved`.                                                                                                                              |
| **Projekt / Modul** | `project_name` | A feladat modulja / forrásprojektje: pl. `AuthService`, `APIGateway`, `WebPortal`, `DevOps`, `DataPlatform`, `SecurityOps` stb.                                                |

---

## 3. Eszközök áttekintése

### `tawos_list_labels.php`

Címkék, megoszlások és arányok lekérdezése és formázott megjelenítése terminálban vagy JSON formátumban.

- **Forrás:** Képes közvetlenül CSV fájlból vagy élő adatbázisból (`--db`) olvasni.
- **Elérési út:** `tools/tawos_list_labels.php`

### `tawos_seed_manager.php`

A seedelendő rekordok számának és címkearányainak beállítása, mintavételezése és kiírása:

- **Seed rekordszám beállítása:** tetszőleges elemszám generálása (pl. 80, 350, 500, 1000).
- **Valódi SQL mintavételezés (`--sql`):** Képes közvetlenül a nyers TAWOS adatbázis-dumpból (`TAWOS.sql` vagy `.zip`) kinyerni valós rekordokat a szintetikus sokszorosítás helyett.
- **Címkefókusz / kvóták:** arányok vagy konkrét darabszámok megadása típusonként és prioritásonként.
- **Biztonsági mentés:** a meglévő CSV-ről automatikusan `.bak` másolatot készít.
- **Adatbázis szinkronizáció:** közvetlenül képes betölteni a generált adatokat a `tawos_issues` táblába.
- **Elérési út:** `tools/tawos_seed_manager.php`

### `tawos_sql_sampler.php` (Valódi SQL dump mintavételező)

Önálló, stream-alapú mintavételező motor a hivatalos, kb. 4GB-os nyers `TAWOS.sql` (vagy `TAWOS.sql.zip`) MySQL dump feldolgozására:

- **Nulla lemezterület-igény (Zero-Decompression Streaming):** A 600MB-os tömörített `.zip` fájlból közvetlenül a memóriába olvassa a bájtokat a PHP `zip://` stream vagy `unzip -p` pipe segítségével, így **nem szükséges a 4GB-os fájlt a lemezre kicsomagolni**!
- **Alapértelmezett forrás útvonal:** `backend/data/TAWOS.sql` (ha nincs megadva, automatikusan keresi a `.zip` változatot is).
- **Korai leállítás ($O(N)$ scanning):** A dump elején lévő `Project`, `Issue` és `Comment` táblákat olvassa; amint eléri a kért kvótákat és a kommentek párosítását, a többi hatalmas táblát (pl. a 3GB+ méretű `Change_Log`-ot) azonnal átugorja, így másodpercek alatt végez.
- **Valós adatok kinyerése:** Eredeti Jira kulcsok (`MESOS-1234`, `SPARK-5678`), valódi issue leírások, típusok, prioritások, valós story pointok, valódi fejlesztői kommentek és eredeti projektnevek.
- **Elérési út:** `tools/tawos_sql_sampler.php`

### `setup.php` és `setup.sh`

Kibővített, interaktív rendszer- és TAWOS konfigurációs varázsló:

- **Beépített készletek és mintavételezés:** Választási lehetőség a beépített 80 és 350 rekordos készletek (`tawos_seed.csv` / `tawos_seed_350.csv`), illetve a nyers `TAWOS.sql` / `.zip` dumpból történő kinyerés között.
- **Közvetlen adatbázis-szinkronizáció:** Lehetőséget biztosít az új minták azonnali betöltésére a helyi `tawos_issues` táblába (`--db`).
- **Elérési út:** `tools/setup.php` és a gyökérben `./setup.sh`

---

## 4. Működési Forgatókönyvek és Sorrend

### A) forgatókönyv: Előkészítés a szerver indítása ELŐTT (Offline / Pre-boot)

> **Mikor használandó?** Ha a konténerek vagy a backend szerver indítása előtt szeretnénk előkészíteni a kívánt méretű és címkefókuszú seed adatot.

```mermaid
flowchart TD
    A["1. Címkék megtekintése\n(tools/tawos_list_labels.php)"] --> B["2. Új Seed generálása CSV-be\n(tools/tawos_seed_manager.php --count=500 ...)"]
    B --> C["backend/data/tawos_seed.csv frissítve\n(.bak mentéssel)"]
    C --> D["3. Szerver indítása\n(docker compose up vagy php -S ...)"]
    D --> E["Application::boot() lefut"]
    E --> F{"tawos_issues tábla üres?"}
    F -- Igen --> G["TawosService::autoSeed()\nAutomatikusan betölti a CSV-t az adatbázisba"]
    F -- Nem --> H["Nem módosítja az adatbázist"]
```

#### Lépések: A

1. **Címkék és kiinduló eloszlás ellenőrzése:**

   ```bash
   php tools/tawos_list_labels.php
   ```

2. **Kívánt rekordszám és címkefókusz beállítása a seed fájlba:**
   *(Adatbázis kapcsoló nélkül, így csak a CSV fájl frissül)*

   ```bash
   # Példa: 500 rekord generálása (250 Story, 200 Bug, 50 Task)
   php tools/tawos_seed_manager.php --count=500 --types="Story:250,Bug:200,Task:50"
   ```

3. **Eredmény ellenőrzése a CSV-ben:**

   ```bash
   php tools/tawos_list_labels.php --dimension=type
   ```

4. **Szerver indítása:**

   ```bash
   # Docker esetén:
   docker compose up -d

   # Vagy natív PHP backend esetén:
   cd backend && php -S 0.0.0.0:8000 router.php
   ```

5. **Automatikus betöltés működése:**
   Amikor a backend elindul és az első kérés megérkezik, a `TawosService::autoSeed()` ellenőrzi, hogy a `tawos_issues` tábla tartalmaz-e adatot:
   - Ha a tábla üres, **automatikusan importálja** a frissen generált `backend/data/tawos_seed.csv` fájlt.
   - Így az adatbázis azonnal az új, 500 elemes adatkészlettel áll fel.

---

### B) forgatókönyv: Frissítés futó szerver / adatbázis MELLETT (Live Reseeding)

> **Mikor használandó?** Ha a rendszer már fut, de meg szeretnénk változtatni a seedelt adatok számát vagy címkeeloszlását.

```mermaid
flowchart TD
    A["1. Élő adatbázis állapot ellenőrzése\n(tools/tawos_list_labels.php --db)"] --> B["2. Generálás és azonnali betöltés\n(tools/tawos_seed_manager.php --count=500 --db)"]
    B --> C["tawos_seed.csv mentése (.bak létrehozása)"]
    C --> D["TawosService::reseedFromCsv()"]
    D --> E["tawos_issues tábla kiürítése (DELETE)"]
    E --> F["Új rekordok beillesztése (INSERT tranzakció)"]
    F --> G["3. Élő ellenőrzés\n(tools/tawos_list_labels.php --db)"]
```

#### Lépések: B

1. **Jelenlegi adatbázis állapot lekérése:**

   ```bash
   php tools/tawos_list_labels.php --db
   ```

2. **Generálás és azonnali adatbázisba seedelés a `--db` kapcsolóval:**

   ```bash
   php tools/tawos_seed_manager.php --count=500 --types="Story:300,Bug:150,Task:50" --db
   ```

   *(Interaktív módban a szkript a végén megkérdezi: `Szeretnéd azonnal betölteni az új rekordokat a TAIPO adatbázisba? (Y/n)`, így elég jóváhagyni).*

3. **Frissített állapot ellenőrzése az adatbázisban:**

   ```bash
   php tools/tawos_list_labels.php --db --dimension=type
   ```

---

### C) forgatókönyv: Valódi adathalmaz mintavételezése nyers TAWOS SQL dumpból (Zero-Decompression Streaming)

> **Mikor használandó?** Ha nem a meglévő 80 sablonrekordot szeretnéd sokszorosítani, hanem a hivatalos, 4GB-os nyers `TAWOS.sql` vagy `TAWOS.sql.zip` adatbázis-dumpból szeretnél valós, reprezentatív agilis feladatokat (Story, Bug, Task), kommenteket és projekteket kinyerni anélkül, hogy a lemezre ki kellene csomagolni a teljes 4GB-ot.

```mermaid
flowchart TD
    A["TAWOS.sql vagy TAWOS.sql.zip letöltve\n(alap: backend/data/TAWOS.sql[.zip])"] --> B["Streaming olvasás indítása\n(zip:// wrapper vagy pipe)"]
    B --> C["1. Project tábla beolvasása (névfeloldás)"]
    C --> D["2. Issue tábla szűrése & mintavételezés\n(kvóták: Story, Bug, Task)"]
    D --> E["3. Comment tábla párosítása a kinyert Issue-khoz"]
    E --> F["4. Korai leállítás (Change_Log átugorva)"]
    F --> G["5. Új valódi seed CSV kiírása\n(backend/data/tawos_seed.csv)"]
    G --> H{"--db opció megadva?"}
    H -- Igen --> I["TawosService::reseedFromCsv()\nAzonnal betölti az adatbázisba"]
    H -- Nem --> J["Kész, következő szerverindításkor töltődik be"]
```

#### Lépések: C

1. **A nyers fájl elhelyezése:**
   Helyezd a letöltött TAWOS adathalmazt a `backend/data/` könyvtárba:
   - `backend/data/TAWOS.sql` (ha ki van csomagolva)
   - vagy `backend/data/TAWOS.sql.zip` (közvetlen tömörített fájl, 0 extra lemezterülettel!)

2. **Mintavételezés futtatása (pl. 350 valódi rekord kinyerése):**
   Közvetlenül a samplerrel:

   ```bash
   php tools/tawos_sql_sampler.php --count=350 --db
   ```

   Vagy a beállító varázslón keresztül:

   ```bash
   php tools/setup.php --count=350 --sql=backend/data/TAWOS.sql --db -y
   ```

   Vagy a seed kezelővel:

   ```bash
   php tools/tawos_seed_manager.php --count=350 --sql=backend/data/TAWOS.sql --db
   ```

3. **Eredmény ellenőrzése:**

   ```bash
   php tools/tawos_list_labels.php --db
   ```

---

## 5. Túllépési figyelmeztetés és döntési logika (Y/N)

Ha a megadott típus- vagy címkekvóták összege túllépi az előre beállított összértéket, a rendszer védelmi mechanizmusa aktiválódik.

### Példa

- Beállított célméret: **500 db**
- Megadott kvóták: `--types="Story:300,Bug:250"` (Összeg: **550 db**)

### Megjelenő figyelmeztetés a konzolon

```text
───────────────────────────────────────────────────────────────────
⚠️  FIGYELMEZTETÉS: A megadott címkekvóták összege túllépi a beállított értéket!
   • Beállított kívánt összérték: 500 db
   • Címkék (kvóták) összege:     550 db (Story: 300, Bug: 250)
   • Különbözet (túllépés):        +50 db
───────────────────────────────────────────────────────────────────

Túllépi a beállított értéket (500). Mindenképpen a magasabb értékkel (550) seed-eljek? (Y/n): 
```

### Lehetséges válaszok

1. **`Y` (Igen) válasz:**
   - A rendszer elfogadja a magasabb értéket ($550$ db).
   - 300 Story és 250 Bug kerül kiírásra és beillesztésre.

2. **`N` (Nem) válasz:**
   A szkript megkérdezi a visszaskálázást:

   ```text
   Mit szeretnél tenni?
   Arányosan skálázzam vissza a megadott címkéket az eredeti korlátra (500 db)? (Y/n):
   ```

   - **`Y` esetén:** Megtartja a címkék egymáshoz viszonyított arányát (54.5% Story, 45.5% Bug), és visszaskálázza pontosan 500 darabra (273 Story, 227 Bug).
   - **`N` esetén:** Megszakítja a futást módosítás nélkül (`Művelet megszakítva a felhasználó kérésére`).

3. **Nem-interaktív / CI-CD futtatás (`-y` vagy `--yes`):**
   Ha a parancssorban megadod a `-y` kapcsolót, figyelmeztetés esetén automatikusan a magasabb értéket hagyja jóvá kérdés nélkül.

---

## 6. Helyi adatbázis és offline működés (Offline-First Architecture)

A TAWOS adathalmaz kezelése a TAIPO-ban **100%-ban helyi és offline-first** architektúrára épül:

### A működés szabályai

1. **Helyi adatbázis (`tawos_issues` tábla):**
   - A rendszer a működéséhez kizárólag a helyi adatbázist (`tawos_issues` táblát) és a minta CSV-t (`backend/data/tawos_seed.csv`) használja.
   - 0 hálózati forgalom, 0 késleltetés, teljes offline működőképesség.
2. **Keresés és szűrés:**
   - A Dashboard felületén lévő TAWOS kereső és a backend `?action=search_tawos` végpont közvetlenül a helyi adatbázisban indexelt rekordok között végez teljes szöveges keresést (cím, leírás, projekt és kulcs alapján).
3. **Méretezhetőség és frissítés:**
   - Tetszőleges számú valós rekord mintavételezhető a nyers `TAWOS.sql` vagy `.zip` dumpból a `tools/tawos_sql_sampler.php` vagy a `setup.sh` segítségével (pl. 80, 350, 500 vagy több ezer rekord).
   - Nincs szükség külső API kulcsokra vagy hálózati függőségekre.

---

## 7. Parancsreferencia és példák

### A) Rendszer és TAWOS Setup Varázsló (`setup.sh` / `setup.php`)

```bash
# Interaktív beállító varázsló indítása (választható 80, 350 rekord vagy SQL dump):
./setup.sh
# vagy:
php tools/setup.php -i

# Beépített 350 rekordos készlet (tawos_seed_350.csv) betöltése azonnal adatbázisba:
php tools/setup.php --count=350 --db -y
# vagy explicit forrással:
php tools/setup.php --source=backend/data/tawos_seed_350.csv --count=350 --db -y

# 500 rekord mintavételezése valódi SQL dumpból és azonnali adatbázis seedeléssel:
php tools/setup.php --count=500 --sql=backend/data/TAWOS.sql --db -y
```

### B) Címkék listázása (`tawos_list_labels.php`)

```bash
# Teljes statisztika a seed CSV-ből
php tools/tawos_list_labels.php

# Teljes statisztika az adatbázisból
php tools/tawos_list_labels.php --db

# Csak egy adott dimenzió lekérése
php tools/tawos_list_labels.php --dimension=type
php tools/tawos_list_labels.php --dimension=priority
php tools/tawos_list_labels.php --dimension=status
php tools/tawos_list_labels.php --dimension=resolution
php tools/tawos_list_labels.php --dimension=project

# Adott külső CSV elemzése
php tools/tawos_list_labels.php --csv=/eleresi/ut/egyedi_tawos.csv

# JSON kimenet
php tools/tawos_list_labels.php --json
```

### C) Helyi seed generálás (`tawos_seed_manager.php`)

```bash
# 1. 500 rekord generálása CSV-be az alapértelmezett megoszlás szerint
php tools/tawos_seed_manager.php --count=500

# 2. 500 rekord generálása, erősebb hiba (Bug) fókusszal és adatbázisba írással
php tools/tawos_seed_manager.php --count=500 --types="Story:200,Bug:250,Task:50" --db

# 3. Százalékos fókusz megadása
php tools/tawos_seed_manager.php --count=400 --types="Story:60%,Bug:30%,Task:10%" --db

# 4. Túllépés tesztelése figyelmeztetéssel
php tools/tawos_seed_manager.php --count=500 --types="Story:300,Bug:250"

# 5. Teljes interaktív varázsló indítása
php tools/tawos_seed_manager.php -i

# 6. Biztonsági mentés kihagyása és automatikus megerősítés
php tools/tawos_seed_manager.php --count=1000 -y --no-backup --db
```

### D) Valódi TAWOS SQL Streaming Mintavételezés (`tawos_sql_sampler.php`)

```bash
# 1. 350 valódi rekord kinyerése az alapértelmezett forrásból (backend/data/TAWOS.sql vagy .zip)
php tools/tawos_sql_sampler.php --count=350

# 2. Mintavételezés közvetlenül tömörített ZIP-ből, azonnali adatbázis-feltöltéssel
php tools/tawos_sql_sampler.php --sql=backend/data/TAWOS.sql.zip --count=350 --db

# 3. Speciális típus és prioritás kvóták érvényesítése
php tools/tawos_sql_sampler.php --count=500 --types="Story:60%,Bug:30%,Task:10%" --priorities="Critical:50,Major:250,Minor:50" --db

# 4. Egyedi célfájl megadása biztonsági mentés nélkül
php tools/tawos_sql_sampler.php --sql=backend/data/TAWOS.sql --count=200 --output=backend/data/custom_sample.csv --no-backup
```
