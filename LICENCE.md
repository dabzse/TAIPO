# Licences and Attributions

## TAIPO Project

**TAIPO** (Teaching AI Product Owner) is developed at Eszterházy Károly Catholic University as part of academic research in AI-assisted software engineering education.

- **Authors:** Judit Szabó, Mihaly Nyilas
- **Supervisor:** Dr. Gábor Kusper
- **Repositories:**
  - [github.com/szabojuci/AIKanban](https://github.com/szabojuci/AIKanban)
  - [github.com/dabzse/TAIPO](https://github.com/dabzse/TAIPO)

---

## Third-Party Dataset: TAWOS

TAIPO integrates a curated subset of the **TAWOS (Tawosi Agile Web-hosted Open-Source Issues)** dataset for enriching its Product Owner simulation with real-world agile project patterns.

### TAWOS Dataset Information

- **Full Name:** The Tawosi Agile Web-hosted Open-Source Issues Dataset
- **Authors:** Vali Tawosi, Afnan Al-Subaihin, Rebecca Moussa, Federica Sarro
- **Institution:** University College London (UCL), SOLAR Research Group
- **Published In:** Proceedings of the 19th International Conference on Mining Software Repositories (MSR 2022)
- **DOI:** [10.1145/3524842.3528029](https://doi.org/10.1145/3524842.3528029)
- **Dataset DOI:** [10.5522/04/21308124](http://doi.org/10.5522/04/21308124)
- **Repository:** [github.com/SOLAR-group/TAWOS](https://github.com/SOLAR-group/TAWOS)
- **Licence:** Apache License, Version 2.0

### Citation

```bibtex
@INPROCEEDINGS{9796320,
  author={Tawosi, Vali and Al-Subaihin, Afnan and Moussa, Rebecca and Sarro, Federica},
  booktitle={2022 IEEE/ACM 19th International Conference on Mining Software Repositories (MSR)},
  title={A Versatile Dataset of Agile Open Source Software Projects},
  year={2022},
  pages={707-711},
  doi={10.1145/3524842.3528029}
}
```

### Terms of Use

By using the TAWOS data included in TAIPO, you agree to the following terms (as specified by the TAWOS authors):

1. This dataset is published for **research purposes only**. Usage that may cause harm to contributing users or project owners is prohibited.
2. The data has been cleared of personally identifiable information. Tracing information back to identify individual contributors is **strongly discouraged**.
3. All projects included in the TAWOS dataset are publicly available online under open-source licences. The TAWOS dataset itself is shared under the **Apache License, Version 2.0**.

### TAIPO's Usage

TAIPO ships a **curated, representative subset** (~100 records) of the TAWOS dataset in `backend/data/tawos_seed.csv`. This subset is used to:

- Enrich AI-generated Product Owner comments with real-world agile feedback tone and style
- Ground change request generation in realistic Jira-style issue patterns
- Provide statistical context for the simulation engine

The full TAWOS dataset (458,232 issues from 39 projects) can be optionally imported from the [original source](http://doi.org/10.5522/04/21308124).

---

## Apache License, Version 2.0 (TAWOS Dataset)

```text
Copyright (c) 2022 The TAWOS Dataset.

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```
