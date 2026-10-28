# TAIPO Offline Fonts System / Offline betűtípus-kezelő rendszer

This directory stores all local offline typography assets for the **TAIPO** application.
Because TAIPO is designed for university laboratories and local offline instances, **no external CDN requests** (such as Google Fonts) are made at runtime. All fonts are served directly from this local repository.

Ez a mappa tartalmazza a **TAIPO** alkalmazás összes helyben tárolt, offline betűtípus-állományát. Mivel a TAIPO egyetemi laborokban és offline szervereken is fut, a rendszer **nem támaszkodik külső CDN-ekre** (pl. Google Fonts).

---

## 🔤 Installed Font Families / Elérhető betűtípusok

| Font Family          | Type               | Weights / Axes            | Primary Role in TAIPO                                          |
| :------------------- | :----------------- | :------------------------ | :------------------------------------------------------------- |
| **Inter**            | Variable Sans      | 100–900 (Normal & Italic) | **Primary UI**: Card text, buttons, modals, forms              |
| **Outfit**           | Variable Sans      | 100–900 (Normal)          | **Brand & Headings**: Column titles, breadcrumbs, metrics      |
| **JetBrains Mono**   | Variable Mono      | 100–800 (Normal & Italic) | **Monospace**: Code generation, Ticket `#ID`s, JSON, audit log |
| **Oswald**           | Variable Condensed | 200–700 (Normal)          | *Alternative Display*: Condensed modern headers                |
| **Playfair Display** | Variable Serif     | 400–900 (Normal & Italic) | *Alternative Display*: Classic editorial serif                 |
| **Roboto Serif**     | Variable Serif     | 100–900 (Normal & Italic) | *Alternative Body/Display*: High-legibility serif text         |

All installed font files use the modern **Variable Font format** (`.ttf`), providing continuous font weight and optical sizing in compact, self-contained files.

---

## 🎓 University Customization Guide / Útmutató az egyetem számára

### How to switch active fonts (Hogyan válthatsz aktív betűtípust?)

All font declarations are consolidated in [`frontend/src/fonts.css`](../fonts.css).
To change the active fonts across the entire application, **simply modify the CSS variables in the `:root` block**:

A teljes alkalmazás tipográfiája a [`frontend/src/fonts.css`](../fonts.css) fájlban van központosítva. A betűtípusok cseréjéhez mindössze a `:root` blokk változóit kell átírni:

```css
:root {
    /* Default configuration */
    --font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --font-display: 'Outfit', 'Inter', system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, Menlo, Monaco, Consolas, monospace;

    /* Available alternatives */
    --font-condensed: 'Oswald', sans-serif;
    --font-serif-display: 'Playfair Display', Georgia, serif;
    --font-serif: 'Roboto Serif', Georgia, serif;
}
```

#### Example 1: Use a Serif theme for the whole application

```css
:root {
    --font-sans: 'Roboto Serif', Georgia, serif;
    --font-display: 'Playfair Display', Georgia, serif;
    --font-mono: 'JetBrains Mono', monospace;
}
```

#### Example 2: Use Condensed headings (Oswald)

```css
:root {
    --font-sans: 'Inter', system-ui, sans-serif;
    --font-display: 'Oswald', sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
}
```

---

### How to add a new custom font (Új betűtípus hozzáadása)

1. Create a subfolder inside `frontend/src/fonts/` (e.g. `frontend/src/fonts/MyFont/`).
2. Copy your `.ttf` or `.woff2` files into that folder.
3. Open [`frontend/src/fonts.css`](../fonts.css) and append the `@font-face` definition:

   ```css
   @font-face {
       font-family: 'MyFont';
       font-style: normal;
       font-weight: 400 700;
       font-display: swap;
       src: url('./fonts/MyFont/MyFont.ttf') format('truetype');
   }
   ```

4. Set `--font-sans: 'MyFont', sans-serif;` in `:root`.

---

## 📂 File Structure / Fájlstruktúra

```text
frontend/src/
├── fonts.css               # Central @font-face declarations and :root variables
└── fonts/
    ├── README.md           # Documentation (this file)
    ├── Inter/              # Inter-VariableFont_opsz,wght.ttf (+ Italic)
    ├── JetBrains_Mono/     # JetBrainsMono-VariableFont_wght.ttf (+ Italic)
    ├── Oswald/             # Oswald-VariableFont_wght.ttf
    ├── Outfit/             # Outfit-VariableFont_wght.ttf
    ├── Playfair_Display/   # PlayfairDisplay-VariableFont_wght.ttf (+ Italic)
    └── Roboto_Serif/       # RobotoSerif-VariableFont_GRAD,opsz,wdth,wght.ttf (+ Italic)
```
